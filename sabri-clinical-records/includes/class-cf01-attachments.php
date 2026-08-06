<?php
defined('ABSPATH') || exit;

final class CF01_Attachments {
    private const STATES = array('quarantined', 'scanning', 'ready', 'rejected', 'revoked', 'purge_pending', 'purged');

    public static function attach(int $actor_id, string $patient_uuid, string $encounter_uuid, array $asset): array {
        CF01_Authorization::clinician($actor_id, 'attach_clinical_asset');
        CF01_Authorization::relationship($patient_uuid, $actor_id, 'clinical_care', 'attach_clinical_asset');
        CF01_Authorization::consent($patient_uuid, 'images');
        $encounter = CF01_Encounters::get($encounter_uuid);
        if ((string) $encounter['patient_uuid'] !== $patient_uuid) {
            throw new RuntimeException('Wrong-patient attachment relation was blocked.');
        }
        CF01_Authorization::relationship_for_record($patient_uuid, $actor_id, 'clinical_care', (string) $encounter['relationship_uuid'], 'attach_clinical_asset');
        foreach (array('asset_reference', 'sha256', 'declared_type', 'source') as $field) {
            if (empty($asset[$field])) {
                throw new InvalidArgumentException('Attachment provenance is incomplete.');
            }
        }
        if (!preg_match('/^[a-f0-9]{64}$/i', (string) $asset['sha256'])) {
            throw new InvalidArgumentException('Attachment checksum is invalid.');
        }
        if (str_contains((string) $asset['asset_reference'], '://') || preg_match('/[?&](token|key|secret|signature|session|authorization)=/i', (string) $asset['asset_reference'])) {
            throw new InvalidArgumentException('Attachment reference must be opaque and non-bearer.');
        }
        $provider = CF01_Contracts::secure_media('register', array(
            'asset_reference' => (string) $asset['asset_reference'],
            'sha256' => strtolower((string) $asset['sha256']),
            'purpose' => 'clinical_attachment',
            'patient_uuid' => $patient_uuid,
            'encounter_uuid' => $encounter_uuid,
        ));
        if (empty($provider['valid']) || empty($provider['accepted']) || ($provider['privacy_class'] ?? '') !== 'C5') {
            throw new RuntimeException('Secure clinical media provider did not accept the asset.');
        }
        $uuid = CF01_DB::uuid();
        CF01_DB::insert('attachments', array(
            'attachment_uuid' => $uuid,
            'patient_uuid' => $patient_uuid,
            'encounter_uuid' => $encounter_uuid,
            'asset_reference_cipher' => CF01_Crypto::encrypt((string) $asset['asset_reference'], 'attachment-reference'),
            'sha256' => strtolower((string) $asset['sha256']),
            'declared_type' => sanitize_text_field((string) $asset['declared_type']),
            'detected_type' => null,
            'source_cipher' => CF01_Crypto::encrypt((string) $asset['source'], 'attachment-source'),
            'scan_status' => 'quarantined',
            'interpretation_status' => 'unreviewed',
            'author_user_id' => $actor_id,
            'row_version' => 1,
            'created_at' => CF01_DB::now(),
            'updated_at' => CF01_DB::now(),
        ));
        CF01_Audit::record($actor_id, 'ClinicalAttachmentQuarantined', 'clinical_attachment', $uuid, 'images', array('sha256' => strtolower((string) $asset['sha256'])));
        return self::get($uuid);
    }

    public static function review_scan(int $actor_id, string $uuid, string $status, array $scanner_evidence, int $expected_version): array {
        $row = self::get($uuid);
        CF01_Authorization::actor($actor_id, 'review_attachment', array('patient_uuid' => (string) $row['patient_uuid'], 'attachment_uuid' => $uuid));
        CF01_Authorization::expected_version($row, $expected_version);
        if (!in_array($status, array('ready', 'rejected'), true)) {
            throw new InvalidArgumentException('Scanner result must be ready or rejected.');
        }
        if (empty($scanner_evidence['scanner']) || empty($scanner_evidence['signature']) || empty($scanner_evidence['completed_at'])) {
            throw new InvalidArgumentException('Signed scanner evidence is required.');
        }
        $ok = CF01_DB::update_versioned('attachments', array(
            'scan_status' => $status,
            'detected_type' => sanitize_text_field((string) ($scanner_evidence['detected_type'] ?? '')),
            'scanner_evidence_cipher' => CF01_Crypto::encrypt($scanner_evidence, 'scanner-evidence'),
        ), array('attachment_uuid' => $uuid), $expected_version);
        if (!$ok) {
            throw new RuntimeException('Attachment changed concurrently.');
        }
        CF01_Audit::record($actor_id, 'ClinicalAttachmentScanCompleted', 'clinical_attachment', $uuid, 'attachment_security', array('status' => $status));
        return self::get($uuid);
    }

    public static function delivery_reference(int $actor_id, string $uuid): string {
        $row = self::get($uuid);
        $context = CF01_Role_Context::resolve($actor_id, (string) $row['patient_uuid'], 'clinical_care');
        $allowed = CF01_Role_Context::fields($context, array('attachments'));
        if (!in_array('attachments', $allowed, true)) {
            throw new RuntimeException('Clinical attachment access is unavailable for this patient context.');
        }
        if (($row['scan_status'] ?? '') !== 'ready') {
            throw new RuntimeException('Unscanned or quarantined clinical attachment cannot be delivered.');
        }
        $reference = CF01_Crypto::decrypt((string) $row['asset_reference_cipher'], 'attachment-reference');
        if (!is_string($reference) || $reference === '') {
            throw new RuntimeException('Secure attachment provider reference is unavailable.');
        }
        $delivery = CF01_Contracts::secure_media('delivery', array(
            'asset_reference' => $reference,
            'purpose' => 'clinical_attachment_view',
            'actor_user_id' => $actor_id,
            'actor_role' => (string) $context['role'],
            'object_uuid' => $uuid,
            'patient_uuid' => $row['patient_uuid'],
            'ttl_seconds' => 300,
            'no_store' => true,
        ));
        if (empty($delivery['valid']) || empty($delivery['accepted']) || empty($delivery['delivery_grant'])) {
            throw new RuntimeException('Secure attachment delivery is unavailable.');
        }
        CF01_Audit::access($actor_id, (string) $row['patient_uuid'], 'ClinicalAttachmentViewed', 'clinical_attachment', $uuid, 'clinical_care', 'success');
        return (string) $delivery['delivery_grant'];
    }

    public static function relink(int $actor_id, string $uuid, string $new_patient_uuid, string $new_encounter_uuid, int $expected_version): array {
        $row = self::get($uuid);
        CF01_Authorization::actor($actor_id, 'relink_attachment', array('patient_uuid' => (string) $row['patient_uuid'], 'attachment_uuid' => $uuid));
        CF01_Role_Context::resolve($actor_id, (string) $row['patient_uuid'], 'clinical_integrity', 'records');
        CF01_Authorization::expected_version($row, $expected_version);
        $encounter = CF01_Encounters::get($new_encounter_uuid);
        if ((string) $encounter['patient_uuid'] !== $new_patient_uuid || (string) $row['patient_uuid'] !== $new_patient_uuid) {
            CF01_Audit::record($actor_id, 'ClinicalAttachmentRelinkBlocked', 'clinical_attachment', $uuid, 'clinical_integrity', array());
            throw new RuntimeException('Cross-patient attachment relink is prohibited.');
        }
        if (($encounter['status'] ?? '') === 'entered_in_error') {
            throw new RuntimeException('Attachment cannot be relinked to an entered-in-error encounter.');
        }
        $ok = CF01_DB::update_versioned('attachments', array('encounter_uuid' => $new_encounter_uuid), array('attachment_uuid' => $uuid), $expected_version);
        if (!$ok) {
            throw new RuntimeException('Attachment changed concurrently.');
        }
        return self::get($uuid);
    }

    public static function get(string $uuid): array {
        $row = CF01_DB::row('SELECT * FROM ' . CF01_DB::table('attachments') . ' WHERE attachment_uuid = %s LIMIT 1', array($uuid));
        if (!$row) {
            throw new RuntimeException('Clinical attachment is unavailable.');
        }
        return $row;
    }

    public static function states(): array {
        return self::STATES;
    }
}
