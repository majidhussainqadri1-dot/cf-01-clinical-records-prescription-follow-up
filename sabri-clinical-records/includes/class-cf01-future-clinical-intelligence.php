<?php
defined('ABSPATH') || exit;

/**
 * Future Clinical Intelligence 24.
 *
 * These capabilities are source-complete but governance-gated.  They reuse CF-01's
 * canonical encrypted records instead of creating a second clinical source of truth.
 * Every feature defaults to disabled and must be accepted separately after the core
 * runtime has been activated.  No future capability may autonomously diagnose,
 * prescribe, change a prescription, bypass consent/relationship controls, or train on
 * raw production clinical data.
 */
final class CF01_Future_Clinical_Intelligence {
    private const OPTION = 'cf01_future_clinical_intelligence_24';

    private const FEATURES = array(
        'CF01-FUT-001' => array('key' => 'clinical_sidecar_membership_assurance', 'title' => 'Clinical Sidecar & Membership Assurance', 'mode' => 'assurance'),
        'CF01-FUT-002' => array('key' => 'longitudinal_clinical_timeline', 'title' => 'Longitudinal Clinical Timeline', 'mode' => 'timeline'),
        'CF01-FUT-003' => array('key' => 'problem_list_diagnosis_tracking', 'title' => 'Problem List & Diagnosis Tracking', 'mode' => 'observation'),
        'CF01-FUT-004' => array('key' => 'allergy_intolerance_management', 'title' => 'Allergy & Intolerance Management', 'mode' => 'observation'),
        'CF01-FUT-005' => array('key' => 'medication_therapy_management', 'title' => 'Medication & Therapy Management', 'mode' => 'observation'),
        'CF01-FUT-006' => array('key' => 'lab_results_trends', 'title' => 'Lab Results & Trends', 'mode' => 'observation'),
        'CF01-FUT-007' => array('key' => 'imaging_diagnostic_reports', 'title' => 'Imaging & Diagnostic Reports', 'mode' => 'attachment'),
        'CF01-FUT-008' => array('key' => 'clinical_documents_attachments', 'title' => 'Clinical Documents & Attachments', 'mode' => 'attachment'),
        'CF01-FUT-009' => array('key' => 'vitals_measurements', 'title' => 'Vitals & Measurements', 'mode' => 'observation'),
        'CF01-FUT-010' => array('key' => 'immunization_preventive_care', 'title' => 'Immunization & Preventive Care', 'mode' => 'observation'),
        'CF01-FUT-011' => array('key' => 'care_plans_goals', 'title' => 'Care Plans & Goals', 'mode' => 'observation'),
        'CF01-FUT-012' => array('key' => 'encounters_clinical_notes', 'title' => 'Encounters & Clinical Notes', 'mode' => 'encounter'),
        'CF01-FUT-013' => array('key' => 'referrals_care_coordination', 'title' => 'Referrals & Care Coordination', 'mode' => 'observation'),
        'CF01-FUT-014' => array('key' => 'orders_results_routing', 'title' => 'Orders & Results Routing', 'mode' => 'observation'),
        'CF01-FUT-015' => array('key' => 'clinical_decision_support', 'title' => 'Clinical Decision Support', 'mode' => 'advisory'),
        'CF01-FUT-016' => array('key' => 'patient_reported_outcomes', 'title' => 'Patient-Reported Outcomes', 'mode' => 'patient_reported'),
        'CF01-FUT-017' => array('key' => 'family_social_history', 'title' => 'Family & Social History', 'mode' => 'observation'),
        'CF01-FUT-018' => array('key' => 'reproductive_maternal_health', 'title' => 'Reproductive & Maternal Health', 'mode' => 'sensitive_observation'),
        'CF01-FUT-019' => array('key' => 'behavioral_mental_health', 'title' => 'Behavioral & Mental Health', 'mode' => 'sensitive_observation'),
        'CF01-FUT-020' => array('key' => 'genomics_precision_medicine', 'title' => 'Genomics & Precision Medicine', 'mode' => 'special_consent_observation'),
        'CF01-FUT-021' => array('key' => 'research_consent_registry', 'title' => 'Research & Consent Registry', 'mode' => 'research'),
        'CF01-FUT-022' => array('key' => 'institutional_support_api_webhooks', 'title' => 'Institutional Support API + Webhooks', 'mode' => 'provider_contract'),
        'CF01-FUT-023' => array('key' => 'agent_training_simulation_lab', 'title' => 'Agent Training & Simulation Lab', 'mode' => 'synthetic_only'),
        'CF01-FUT-024' => array('key' => 'transparency_fairness_center', 'title' => 'Transparency & Fairness Center', 'mode' => 'transparency'),
    );

    private const OBSERVATION_TYPES = array(
        'CF01-FUT-003' => 'fci_problem',
        'CF01-FUT-004' => 'fci_allergy_intolerance',
        'CF01-FUT-005' => 'fci_medication_therapy',
        'CF01-FUT-006' => 'fci_lab_result',
        'CF01-FUT-009' => 'fci_vital_measurement',
        'CF01-FUT-010' => 'fci_immunization_prevention',
        'CF01-FUT-011' => 'fci_care_plan_goal',
        'CF01-FUT-013' => 'fci_referral_coordination',
        'CF01-FUT-014' => 'fci_order_result_route',
        'CF01-FUT-017' => 'fci_family_social_history',
        'CF01-FUT-018' => 'fci_reproductive_maternal',
        'CF01-FUT-019' => 'fci_behavioral_mental',
        'CF01-FUT-020' => 'fci_genomics_precision',
    );

    private const FEATURE_STATES = array('disabled', 'shadow', 'enabled');
    private const SENSITIVE = array('CF01-FUT-018', 'CF01-FUT-019', 'CF01-FUT-020');

    public static function registry(): array {
        return self::FEATURES;
    }

    public static function catalogue(): array {
        $items = array();
        foreach (self::FEATURES as $id => $definition) {
            $items[] = array_merge(array('id' => $id, 'state' => self::state($id)), $definition);
        }
        return $items;
    }

    public static function state(string $feature_id): string {
        $feature_id = self::feature_id($feature_id);
        $states = get_option(self::OPTION, array());
        $state = is_array($states) ? sanitize_key((string) ($states[$feature_id] ?? 'disabled')) : 'disabled';
        return in_array($state, self::FEATURE_STATES, true) ? $state : 'disabled';
    }

    public static function configure_state(int $actor_id, string $feature_id, string $state, array $evidence): array {
        $feature_id = self::feature_id($feature_id);
        $state = sanitize_key($state);
        if (!in_array($state, self::FEATURE_STATES, true)) {
            throw new InvalidArgumentException('Future clinical feature state is invalid.');
        }
        CF01_Authorization::actor($actor_id, 'activate_module');
        if ($state !== 'disabled') {
            $required = array('founder_approval', 'privacy_review', 'clinical_safety_review', 'security_review', 'staging_acceptance', 'rollback_ready');
            if (in_array($feature_id, array('CF01-FUT-020', 'CF01-FUT-021', 'CF01-FUT-022', 'CF01-FUT-023'), true)) {
                $required[] = 'data_governance_approval';
            }
            foreach ($required as $field) {
                if (empty($evidence[$field])) {
                    throw new RuntimeException('Future clinical feature gate is incomplete: ' . $field);
                }
            }
        }
        $states = get_option(self::OPTION, array());
        $states = is_array($states) ? $states : array();
        $states[$feature_id] = $state;
        update_option(self::OPTION, $states, false);
        update_option('cf01_future_evidence_' . strtolower(str_replace('-', '_', $feature_id)), CF01_Crypto::encrypt(self::sanitize($evidence), 'future-feature-evidence'), false);
        CF01_Audit::record($actor_id, 'FutureClinicalFeatureStateChanged', 'future_clinical_feature', $feature_id, 'platform_governance', array('state' => $state));
        return array('feature_id' => $feature_id, 'state' => $state, 'effective_at' => CF01_DB::now());
    }

    public static function sidecar_assurance(int $actor_id): array {
        self::require_feature('CF01-FUT-001', true);
        CF01_Authorization::actor($actor_id, 'view_clinical_record');
        $dependencies = CF01_Contracts::dependency_report();
        $result = array();
        foreach ((array) $dependencies as $key => $value) {
            $result[sanitize_key((string) $key)] = (bool) $value;
        }
        return array(
            'feature_id' => 'CF01-FUT-001',
            'activation_state' => CF01_DB::activation_state(),
            'version' => CF01_VERSION,
            'schema_version' => CF01_SCHEMA_VERSION,
            'contract_version' => CF01_CONTRACT_VERSION,
            'dependency_assurance' => $result,
            'source_of_truth_takeover' => false,
        );
    }

    public static function record_fact(int $actor_id, string $feature_id, string $patient_uuid, string $encounter_uuid, array $value, array $provenance): array {
        $feature_id = self::feature_id($feature_id);
        self::require_feature($feature_id);
        if (!isset(self::OBSERVATION_TYPES[$feature_id])) {
            throw new RuntimeException('This Future Clinical Intelligence capability does not use the clinical-fact writer.');
        }
        $encounter = CF01_Encounters::get($encounter_uuid);
        if (!hash_equals((string) $encounter['patient_uuid'], $patient_uuid)) {
            throw new RuntimeException('Future clinical fact patient/encounter mismatch.');
        }
        self::assert_no_autonomous_care($value);
        if ($feature_id === 'CF01-FUT-020') {
            CF01_Authorization::consent($patient_uuid, 'genomics');
        }
        if (empty($provenance['source']) || empty($provenance['observed_at'])) {
            throw new InvalidArgumentException('Future clinical facts require source and observed_at provenance.');
        }
        $provenance['future_feature_id'] = $feature_id;
        $provenance['clinician_reviewed'] = true;
        $row = CF01_Encounters::add_observation($actor_id, $encounter_uuid, self::OBSERVATION_TYPES[$feature_id], self::sanitize($value), self::sanitize($provenance));
        CF01_Audit::record($actor_id, 'FutureClinicalFactRecorded', 'clinical_observation', (string) $row['observation_uuid'], 'clinical_care', array('feature_id' => $feature_id));
        return self::public_observation($row, true);
    }

    public static function facts(int $actor_id, string $feature_id, string $patient_uuid, int $limit = 50): array {
        $feature_id = self::feature_id($feature_id);
        self::require_feature($feature_id, true);
        if (!isset(self::OBSERVATION_TYPES[$feature_id])) {
            throw new RuntimeException('This Future Clinical Intelligence capability does not expose clinical facts.');
        }
        self::authorize_patient_read($actor_id, $patient_uuid, $feature_id);
        $limit = max(1, min(100, $limit));
        $rows = CF01_DB::rows(
            'SELECT * FROM ' . CF01_DB::table('observations') . ' WHERE patient_uuid = %s AND observation_type = %s ORDER BY created_at DESC, id DESC LIMIT ' . $limit,
            array($patient_uuid, self::OBSERVATION_TYPES[$feature_id])
        );
        $result = array();
        foreach ($rows as $row) {
            $result[] = self::public_observation($row, true);
        }
        CF01_Audit::access($actor_id, $patient_uuid, 'FutureClinicalFactsViewed', 'future_clinical_feature', $feature_id, 'clinical_care', 'success');
        return array('feature_id' => $feature_id, 'patient_uuid' => $patient_uuid, 'items' => $result);
    }

    public static function longitudinal_timeline(int $actor_id, string $patient_uuid, int $limit = 100): array {
        self::require_feature('CF01-FUT-002', true);
        self::authorize_patient_read($actor_id, $patient_uuid, 'CF01-FUT-002');
        $limit = max(1, min(200, $limit));
        $items = array();
        foreach (CF01_DB::rows('SELECT encounter_uuid AS object_uuid, status, starts_at AS occurred_at FROM ' . CF01_DB::table('encounters') . ' WHERE patient_uuid = %s ORDER BY starts_at DESC LIMIT ' . $limit, array($patient_uuid)) as $row) {
            $items[] = array('type' => 'encounter', 'object_uuid' => $row['object_uuid'], 'status' => $row['status'], 'occurred_at' => $row['occurred_at']);
        }
        foreach (CF01_DB::rows('SELECT prescription_uuid AS object_uuid, status, effective_from AS occurred_at FROM ' . CF01_DB::table('prescriptions') . ' WHERE patient_uuid = %s ORDER BY effective_from DESC LIMIT ' . $limit, array($patient_uuid)) as $row) {
            $items[] = array('type' => 'prescription', 'object_uuid' => $row['object_uuid'], 'status' => $row['status'], 'occurred_at' => $row['occurred_at']);
        }
        foreach (CF01_DB::rows('SELECT followup_uuid AS object_uuid, status, created_at AS occurred_at FROM ' . CF01_DB::table('followups') . ' WHERE patient_uuid = %s ORDER BY created_at DESC LIMIT ' . $limit, array($patient_uuid)) as $row) {
            $items[] = array('type' => 'follow_up', 'object_uuid' => $row['object_uuid'], 'status' => $row['status'], 'occurred_at' => $row['occurred_at']);
        }
        foreach (CF01_DB::rows('SELECT observation_uuid AS object_uuid, observation_type, status, created_at AS occurred_at FROM ' . CF01_DB::table('observations') . ' WHERE patient_uuid = %s ORDER BY created_at DESC LIMIT ' . $limit, array($patient_uuid)) as $row) {
            $items[] = array('type' => 'observation', 'subtype' => $row['observation_type'], 'object_uuid' => $row['object_uuid'], 'status' => $row['status'], 'occurred_at' => $row['occurred_at']);
        }
        usort($items, static fn(array $a, array $b): int => strcmp((string) $b['occurred_at'], (string) $a['occurred_at']));
        $items = array_slice($items, 0, $limit);
        CF01_Audit::access($actor_id, $patient_uuid, 'FutureLongitudinalTimelineViewed', 'clinical_patient', $patient_uuid, 'clinical_care', 'success');
        return array('feature_id' => 'CF01-FUT-002', 'patient_uuid' => $patient_uuid, 'items' => $items, 'limit' => $limit);
    }

    public static function attachment_index(int $actor_id, string $feature_id, string $patient_uuid, int $limit = 50): array {
        $feature_id = self::feature_id($feature_id);
        if (!in_array($feature_id, array('CF01-FUT-007', 'CF01-FUT-008'), true)) {
            throw new InvalidArgumentException('Attachment index is limited to imaging/diagnostic or clinical-document features.');
        }
        self::require_feature($feature_id, true);
        self::authorize_patient_read($actor_id, $patient_uuid, $feature_id);
        $limit = max(1, min(100, $limit));
        $rows = CF01_DB::rows(
            'SELECT attachment_uuid, encounter_uuid, declared_type, detected_type, scan_status, interpretation_status, created_at, updated_at FROM ' . CF01_DB::table('attachments') . ' WHERE patient_uuid = %s ORDER BY created_at DESC, id DESC LIMIT ' . $limit,
            array($patient_uuid)
        );
        CF01_Audit::access($actor_id, $patient_uuid, 'FutureClinicalAttachmentIndexViewed', 'future_clinical_feature', $feature_id, 'clinical_care', 'success');
        return array('feature_id' => $feature_id, 'patient_uuid' => $patient_uuid, 'items' => $rows);
    }

    public static function encounter_index(int $actor_id, string $patient_uuid, int $limit = 50): array {
        self::require_feature('CF01-FUT-012', true);
        self::authorize_patient_read($actor_id, $patient_uuid, 'CF01-FUT-012');
        $limit = max(1, min(100, $limit));
        $rows = CF01_DB::rows(
            'SELECT encounter_uuid, parent_encounter_uuid, encounter_type, mode, starts_at, ends_at, status, template_key, template_version, row_version FROM ' . CF01_DB::table('encounters') . ' WHERE patient_uuid = %s ORDER BY starts_at DESC, id DESC LIMIT ' . $limit,
            array($patient_uuid)
        );
        CF01_Audit::access($actor_id, $patient_uuid, 'FutureClinicalEncounterIndexViewed', 'future_clinical_feature', 'CF01-FUT-012', 'clinical_care', 'success');
        return array('feature_id' => 'CF01-FUT-012', 'patient_uuid' => $patient_uuid, 'items' => $rows);
    }

    public static function medication_therapy(int $actor_id, string $patient_uuid, int $limit = 50): array {
        self::require_feature('CF01-FUT-005', true);
        self::authorize_patient_read($actor_id, $patient_uuid, 'CF01-FUT-005');
        $limit = max(1, min(100, $limit));
        $prescriptions = CF01_DB::rows(
            'SELECT prescription_uuid, parent_prescription_uuid, status, effective_from, effective_until, superseded_by_uuid, row_version FROM ' . CF01_DB::table('prescriptions') . ' WHERE patient_uuid = %s ORDER BY effective_from DESC, id DESC LIMIT ' . $limit,
            array($patient_uuid)
        );
        return array('feature_id' => 'CF01-FUT-005', 'patient_uuid' => $patient_uuid, 'prescriptions' => $prescriptions, 'therapy_facts' => self::facts($actor_id, 'CF01-FUT-005', $patient_uuid, $limit)['items']);
    }

    public static function decision_support(int $actor_id, string $patient_uuid, array $request): array {
        self::require_feature('CF01-FUT-015');
        CF01_Authorization::clinician($actor_id, 'clinical_decision_support');
        CF01_Authorization::relationship($patient_uuid, $actor_id, 'clinical_care', 'clinical_decision_support');
        CF01_Authorization::consent($patient_uuid, 'clinical_care');
        self::assert_no_autonomous_care($request);
        $safe_request = self::sanitize($request);
        $safe_request['patient_reference_hash'] = hash('sha256', $patient_uuid);
        unset($safe_request['patient_uuid']);
        $result = apply_filters('cf01_clinical_decision_support', null, $safe_request, array('actor_id' => $actor_id, 'patient_uuid' => $patient_uuid));
        if (!is_array($result)
            || empty($result['advisory'])
            || empty($result['clinician_review_required'])
            || !array_key_exists('autonomous_diagnosis', $result)
            || !array_key_exists('automatic_prescription', $result)
            || !empty($result['autonomous_diagnosis'])
            || !empty($result['automatic_prescription'])) {
            throw new RuntimeException('Decision-support provider must return a bounded advisory response requiring clinician review.');
        }
        self::assert_no_forbidden_output($result);
        $result = self::sanitize($result);
        $result['feature_id'] = 'CF01-FUT-015';
        $result['advisory'] = true;
        $result['clinician_review_required'] = true;
        $result['autonomous_diagnosis'] = false;
        $result['automatic_prescription'] = false;
        CF01_Audit::record($actor_id, 'ClinicalDecisionSupportViewed', 'clinical_patient', $patient_uuid, 'clinical_decision_support', array('advisory' => true));
        return $result;
    }

    public static function patient_reported_outcome(int $actor_id, string $followup_uuid, array $response, int $expected_version): array {
        self::require_feature('CF01-FUT-016');
        $row = CF01_Followups::submit_outcome($actor_id, $followup_uuid, $response, $expected_version);
        if (($row['review_status'] ?? '') !== 'pending') {
            throw new RuntimeException('Patient-reported outcome must remain pending until clinician review.');
        }
        return array_merge($row, array('feature_id' => 'CF01-FUT-016', 'clinician_review_required' => true, 'automatic_treatment_change' => false));
    }

    public static function research_consents(int $actor_id, string $patient_uuid): array {
        self::require_feature('CF01-FUT-021', true);
        self::authorize_patient_read($actor_id, $patient_uuid, 'CF01-FUT-021');
        $rows = CF01_DB::rows(
            'SELECT consent_uuid, notice_version, status, granted_at, withdrawn_at, expires_at, row_version, created_at, updated_at FROM ' . CF01_DB::table('consents') . ' WHERE patient_uuid = %s AND purpose = %s ORDER BY id DESC LIMIT 100',
            array($patient_uuid, 'research')
        );
        $external = apply_filters('cf01_research_registry_status', array('available' => false, 'registrations' => array()), $patient_uuid, $actor_id);
        $external = is_array($external) ? self::sanitize($external) : array('available' => false, 'registrations' => array());
        CF01_Audit::access($actor_id, $patient_uuid, 'ResearchConsentRegistryViewed', 'future_clinical_feature', 'CF01-FUT-021', 'research', 'success');
        return array('feature_id' => 'CF01-FUT-021', 'patient_uuid' => $patient_uuid, 'consents' => $rows, 'registry' => $external);
    }

    public static function institutional_webhook(int $actor_id, string $event_name, array $payload): array {
        self::require_feature('CF01-FUT-022');
        CF01_Authorization::actor($actor_id, 'institutional_api');
        $event_name = trim($event_name);
        $allowed = array('ClinicalRecordSummaryUpdated', 'ClinicalCareCoordinationRequested', 'ClinicalResearchConsentChanged', 'ClinicalSafetyAlertRaised');
        if (!in_array($event_name, $allowed, true)) {
            throw new InvalidArgumentException('Institutional webhook event is not allowlisted.');
        }
        $contract = apply_filters('cf01_institutional_webhook_contract', null, $event_name, $actor_id);
        if (!is_array($contract) || empty($contract['valid']) || empty($contract['approved']) || empty($contract['minimum_necessary']) || empty($contract['signed_transport']) || empty($contract['idempotent_receiver'])) {
            throw new RuntimeException('Approved institutional webhook contract is unavailable.');
        }
        $allowed_payload = array_intersect_key($payload, array_flip(array('status', 'category', 'occurred_at', 'correlation_id', 'patient_reference')));
        if (isset($allowed_payload['patient_reference'])) {
            $allowed_payload['patient_reference_hash'] = hash('sha256', (string) $allowed_payload['patient_reference']);
            unset($allowed_payload['patient_reference']);
        }
        $envelope = array(
            'contract_version' => CF01_CONTRACT_VERSION,
            'event_name' => $event_name,
            'event_uuid' => CF01_DB::uuid(),
            'occurred_at' => CF01_DB::now(),
            'payload' => self::sanitize($allowed_payload),
            'contains_raw_clinical_data' => false,
            'source_of_truth_takeover' => false,
        );
        $envelope['signature'] = CF01_Crypto::sign($envelope, 'institutional-webhook');
        $delivery = apply_filters('cf01_institutional_webhook_dispatch', null, $envelope, $contract);
        if (!is_array($delivery) || empty($delivery['accepted']) || empty($delivery['reference'])) {
            throw new RuntimeException('Institutional webhook provider did not accept the signed minimum-necessary envelope.');
        }
        CF01_Audit::record($actor_id, 'InstitutionalClinicalWebhookDispatched', 'institutional_webhook', $envelope['event_uuid'], 'care_coordination', array('event_name' => $event_name));
        return array('feature_id' => 'CF01-FUT-022', 'event_uuid' => $envelope['event_uuid'], 'accepted' => true, 'reference' => sanitize_text_field((string) $delivery['reference']));
    }

    public static function simulation(int $actor_id, array $scenario): array {
        self::require_feature('CF01-FUT-023');
        CF01_Authorization::actor($actor_id, 'clinical_simulation_lab');
        if (empty($scenario['synthetic_case']) || !empty($scenario['contains_real_patient_data']) || isset($scenario['patient_uuid']) || isset($scenario['platform_uuid'])) {
            throw new RuntimeException('Agent Training & Simulation Lab accepts synthetic/de-identified governed scenarios only.');
        }
        $scenario = self::sanitize($scenario);
        $scenario['synthetic_case'] = true;
        $scenario['contains_real_patient_data'] = false;
        $result = apply_filters('cf01_clinical_simulation_execute', null, $scenario, $actor_id);
        if (!is_array($result) || empty($result['simulation_only']) || ($result['source_data'] ?? '') !== 'synthetic') {
            throw new RuntimeException('Simulation provider must attest synthetic-only execution.');
        }
        self::assert_no_forbidden_output($result, true);
        $result = self::sanitize($result);
        $result['feature_id'] = 'CF01-FUT-023';
        $result['simulation_only'] = true;
        $result['not_for_patient_care'] = true;
        CF01_Audit::record($actor_id, 'ClinicalSimulationExecuted', 'simulation_run', CF01_DB::uuid(), 'training_simulation', array('synthetic_only' => true));
        return $result;
    }

    public static function transparency(int $actor_id, string $decision_reference): array {
        self::require_feature('CF01-FUT-024', true);
        CF01_Authorization::actor($actor_id, 'view_clinical_record');
        $decision_reference = sanitize_text_field($decision_reference);
        if ($decision_reference === '') {
            throw new InvalidArgumentException('Decision reference is required.');
        }
        $explanation = apply_filters('cf01_clinical_transparency_explanation', array('available' => false), $decision_reference, $actor_id);
        if (!is_array($explanation)) {
            $explanation = array('available' => false);
        }
        self::assert_no_bias_fields($explanation);
        return array(
            'feature_id' => 'CF01-FUT-024',
            'decision_reference' => $decision_reference,
            'explanation' => self::sanitize($explanation),
            'governing_laws' => array(
                'no_donor_or_payment_bias' => true,
                'no_covert_health_profiling' => true,
                'minimum_necessary' => true,
                'human_clinical_authority_preserved' => true,
                'autonomous_diagnosis' => false,
                'automatic_prescription' => false,
            ),
        );
    }

    public static function feature_query(int $actor_id, string $feature_id, string $patient_uuid, int $limit = 50): array {
        $feature_id = self::feature_id($feature_id);
        return match ($feature_id) {
            'CF01-FUT-002' => self::longitudinal_timeline($actor_id, $patient_uuid, $limit),
            'CF01-FUT-005' => self::medication_therapy($actor_id, $patient_uuid, $limit),
            'CF01-FUT-007', 'CF01-FUT-008' => self::attachment_index($actor_id, $feature_id, $patient_uuid, $limit),
            'CF01-FUT-012' => self::encounter_index($actor_id, $patient_uuid, $limit),
            'CF01-FUT-021' => self::research_consents($actor_id, $patient_uuid),
            default => self::facts($actor_id, $feature_id, $patient_uuid, $limit),
        };
    }

    private static function require_feature(string $feature_id, bool $allow_shadow = false): void {
        CF01_Authorization::require_enabled();
        $state = self::state($feature_id);
        if ($state !== 'enabled' && !($allow_shadow && $state === 'shadow')) {
            throw new RuntimeException('Future clinical capability is not enabled for this runtime.');
        }
    }

    private static function feature_id(string $feature_id): string {
        $feature_id = strtoupper(trim($feature_id));
        if (!isset(self::FEATURES[$feature_id])) {
            throw new InvalidArgumentException('Unknown Future Clinical Intelligence feature.');
        }
        return $feature_id;
    }

    private static function authorize_patient_read(int $actor_id, string $patient_uuid, string $feature_id): void {
        if (CF01_Authorization::patient_owner($actor_id, $patient_uuid)) {
            CF01_Authorization::actor($actor_id, 'view_own_clinical_record');
            return;
        }
        CF01_Authorization::clinician($actor_id, 'view_clinical_record');
        CF01_Authorization::relationship($patient_uuid, $actor_id, 'clinical_care', 'view_clinical_record');
        CF01_Authorization::consent($patient_uuid, 'clinical_care');
        if (in_array($feature_id, self::SENSITIVE, true)) {
            CF01_Audit::record($actor_id, 'SensitiveFutureClinicalContextAuthorized', 'clinical_patient', $patient_uuid, 'minimum_necessary', array('feature_id' => $feature_id));
        }
    }

    private static function public_observation(array $row, bool $include_value): array {
        $result = array(
            'observation_uuid' => (string) $row['observation_uuid'],
            'patient_uuid' => (string) $row['patient_uuid'],
            'encounter_uuid' => (string) $row['encounter_uuid'],
            'observation_type' => (string) $row['observation_type'],
            'status' => (string) $row['status'],
            'row_version' => (int) $row['row_version'],
            'created_at' => (string) $row['created_at'],
            'updated_at' => (string) $row['updated_at'],
        );
        if ($include_value) {
            $value = CF01_Crypto::decrypt((string) $row['value_cipher'], 'observation-value');
            $provenance = CF01_Crypto::decrypt((string) $row['provenance_cipher'], 'observation-provenance');
            $result['value'] = is_array($value) ? $value : $value;
            $result['provenance'] = is_array($provenance) ? $provenance : array();
        }
        return $result;
    }

    private static function assert_no_autonomous_care(array $payload): void {
        foreach (array('autonomous_diagnosis', 'autonomous_prescription', 'automatic_prescription', 'automatic_prescription_change', 'dose_recommendation', 'potency_recommendation') as $key) {
            if (!empty($payload[$key])) {
                throw new RuntimeException('Autonomous diagnosis, prescription, dose or treatment mutation is prohibited.');
            }
        }
    }

    private static function assert_no_forbidden_output(array $payload, bool $simulation = false): void {
        $forbidden = array('dose_recommendation', 'potency_recommendation', 'automatic_prescription', 'autonomous_diagnosis');
        foreach ($forbidden as $key) {
            if (!empty($payload[$key])) {
                throw new RuntimeException($simulation
                    ? 'Simulation output cannot be promoted into autonomous patient care.'
                    : 'Decision-support output exceeds the advisory clinical boundary.');
            }
        }
    }

    private static function assert_no_bias_fields(array $payload): void {
        $walk = function ($value) use (&$walk): void {
            if (!is_array($value)) {
                return;
            }
            foreach ($value as $key => $child) {
                $key = strtolower((string) $key);
                foreach (array('donor', 'donation', 'payment', 'paid_rank', 'financial_priority') as $blocked) {
                    if (str_contains($key, $blocked)) {
                        throw new RuntimeException('Transparency output contains a prohibited financial/donor ranking signal.');
                    }
                }
                $walk($child);
            }
        };
        $walk($payload);
    }

    private static function sanitize($value) {
        if (is_array($value)) {
            $result = array();
            foreach ($value as $key => $child) {
                $safe_key = is_int($key) ? $key : sanitize_key((string) $key);
                $result[$safe_key] = self::sanitize($child);
            }
            return $result;
        }
        if (is_bool($value) || is_numeric($value) || $value === null) {
            return $value;
        }
        return sanitize_textarea_field((string) $value);
    }
}
