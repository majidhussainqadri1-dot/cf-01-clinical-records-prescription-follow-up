import unittest
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1] / 'sabri-clinical-records' / 'includes'
AUTH = (ROOT / 'class-cf01-authorization.php').read_text(encoding='utf-8')
ROLE = (ROOT / 'class-cf01-role-context.php').read_text(encoding='utf-8')
REL = (ROOT / 'class-cf01-relationships.php').read_text(encoding='utf-8')
CONSENT = (ROOT / 'class-cf01-consents.php').read_text(encoding='utf-8')
BREAK = (ROOT / 'class-cf01-break-glass.php').read_text(encoding='utf-8')
RIGHTS = (ROOT / 'class-cf01-rights.php').read_text(encoding='utf-8')


class FreshR2AuthorizationRightsIntegrityTests(unittest.TestCase):
    def test_native_policy_hooks_cannot_broaden_authorization(self):
        self.assertIn('return $allowed && $filtered;', AUTH)
        self.assertIn('$allowed = array_values(array_intersect($baseline, $filtered));', AUTH)
        self.assertIn("'activate_relationship', 'transition_relationship', 'withdraw_own_consent'", AUTH)

    def test_scoped_role_contracts_are_version_and_authority_bound(self):
        self.assertGreaterEqual(ROLE.count("hash_equals(CF01_CONTRACT_VERSION"), 3)
        self.assertIn("(int) ($assertion['authority_version'] ?? 0) !== $stored_authority_version", ROLE)
        self.assertIn('CF01_Contracts::guardian_authority', ROLE)

    def test_relationship_access_grants_revalidate_source_and_mutate_atomically(self):
        activate = REL.split('public static function activate', 1)[1].split('public static function transition', 1)[0]
        transition = REL.split('public static function transition', 1)[1].split('public static function get', 1)[0]
        self.assertIn('self::require_current_source($actor_id, $row);', activate)
        self.assertIn("if ($next === 'active')", transition)
        self.assertIn('self::require_current_source($actor_id, $row);', transition)
        self.assertIn('CF01_DB::transaction', REL.split('public static function propose', 1)[1].split('public static function activate', 1)[0])
        self.assertGreaterEqual(transition.count('CF01_DB::transaction'), 2)

    def test_consent_changes_are_atomic_and_withdrawal_is_subject_controlled(self):
        record = CONSENT.split('public static function record', 1)[1].split('public static function withdraw', 1)[0]
        withdraw = CONSENT.split('public static function withdraw', 1)[1].split('public static function get', 1)[0]
        helper = CONSENT.split('private static function authorize_withdrawal_subject', 1)[1].split('private static function validate_subject_identity', 1)[0]
        self.assertIn('CF01_DB::transaction', record)
        self.assertIn('CF01_DB::transaction', withdraw)
        self.assertIn("'withdraw_own_consent'", helper)
        self.assertNotIn('CF01_Authorization::clinician', helper)
        self.assertIn("authority_version'] ?? 0) !== $stored_authority_version", CONSENT)

    def test_break_glass_state_and_audit_share_transactions(self):
        for marker, next_marker in [
            ('public static function request', 'public static function assertion'),
            ('public static function revoke', 'public static function review'),
            ('public static function review', 'public static function get'),
            ('private static function expire', 'private static function sanitize'),
        ]:
            section = BREAK.split(marker, 1)[1].split(next_marker, 1)[0]
            self.assertIn('CF01_DB::transaction', section)
            self.assertIn('CF01_Audit::', section)

    def test_rights_staff_are_patient_scoped_and_mutations_are_audit_atomic(self):
        decide = RIGHTS.split('public static function decide', 1)[1].split('public static function fulfill_export', 1)[0]
        fulfill = RIGHTS.split('public static function fulfill_export', 1)[1].split('public static function consume_export', 1)[0]
        consume = RIGHTS.split('public static function consume_export', 1)[1].split('public static function correction_addendum', 1)[0]
        access = RIGHTS.split('public static function access_history', 1)[1].split('public static function get', 1)[0]
        self.assertIn('self::require_records_scope', decide)
        self.assertIn('CF01_DB::transaction', decide)
        self.assertIn('self::require_records_scope', fulfill)
        self.assertIn('CF01_DB::transaction', fulfill)
        self.assertIn('CF01_DB::transaction', consume)
        self.assertIn('self::require_records_scope', access)

    def test_case_export_scope_is_bound_to_request_or_decision(self):
        manifest = RIGHTS.split('public static function export_manifest_for_case', 1)[1].split('private static function build_export_manifest', 1)[0]
        self.assertIn('self::approved_export_scope($case)', manifest)
        self.assertIn('array_diff($requested_scope, $approved_scope)', manifest)
        approved = RIGHTS.split('private static function approved_export_scope', 1)[1].split('private static function normalize_export_scope', 1)[0]
        self.assertIn("'approved_scope'", approved)
        self.assertIn("'own_record'", approved)
        self.assertIn('Partially approved export is missing its bounded approved scope.', approved)


if __name__ == '__main__':
    unittest.main()
