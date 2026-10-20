"""2026-10-21 source-level regressions from the fresh CF-01 cross-file audit.

These checks establish repository invariants, not live/staging or legal acceptance.
The separate 40-round review runner and PHP runtime suites remain mandatory.
"""
from pathlib import Path
import re
import unittest

ROOT = Path(__file__).resolve().parents[1]
INC = ROOT / "sabri-clinical-records" / "includes"


def php(name):
    return (INC / ("class-cf01-" + name + ".php")).read_text(encoding="utf-8")


class FreshCrossFileClinicalReview(unittest.TestCase):
    def test_command_receipt_is_atomic_with_mutation(self):
        source = php("db")
        self.assertRegex(source, r"return self::transaction\(function \(\) use \(\$operation, \$receipt_uuid\)")
        tx = source.split("return self::transaction(function () use ($operation, $receipt_uuid)", 1)[1]
        self.assertLess(tx.find("$response = $operation();"), tx.find("'status' => 'completed'"))

    def test_idempotency_binds_concrete_resource_not_only_body(self):
        source = php("db")
        self.assertIn("string $resource_scope = ''", source)
        self.assertIn("$command . '|' . $resource_scope . '|' . $key", source)
        self.assertIn("'resource_scope' => $resource_scope", source)
        self.assertIn("strlen($resource_scope) > 512", source)

    def test_both_mutation_controllers_pass_rest_route_scope(self):
        for controller in ("rest", "lifecycle-rest"):
            with self.subTest(controller=controller):
                source = php(controller)
                self.assertRegex(source, r"CF01_DB::idempotent\([^;]+\(string\) \$request->get_route\(\)\);")

    def test_outbox_preserves_contract_event_case(self):
        source = php("audit-outbox")
        self.assertIn("'event_name' => $event,", source)
        self.assertNotIn("'event_name' => sanitize_key($event)", source)
        self.assertIn("Clinical outbox event name is invalid.", source)

    def test_breakglass_alert_does_not_depend_on_event_case(self):
        source = php("audit-outbox")
        self.assertIn("str_contains(strtolower((string) $row['event_name']), 'breakglass')", source)
        self.assertIn("str_contains(strtolower($event), 'breakglass')", source)

    def test_notification_destination_is_backward_compatible(self):
        source = php("audit-outbox")
        self.assertIn("$event = strtolower((string) ($payload['event_name'] ?? $row['event_name']));", source)
        self.assertIn("str_contains($event, 'prescription')", source)

    def test_own_record_access_history_is_real_minimized_data(self):
        source = php("rest")
        self.assertIn("if (in_array('access_history', $fields, true))", source)
        self.assertIn("$result['access_history'] = array_map", source)
        self.assertIn("'break_glass' =>", source)
        self.assertNotIn("'actor_pseudonym' =>", source.split("$result['access_history'] = array_map",1)[1].split("}, $rows);",1)[0])

    def test_retention_projection_uses_existing_schema(self):
        source = php("lifecycle-rest")
        self.assertNotIn("'retention' => array('retention', 'ledger_uuid')", source)
        self.assertIn("private static function retention_rows_for_patient", source)
        self.assertIn("WHERE object_uuid IN (", source)
        self.assertIn("'retention_uuid'", source)

    def test_retention_projection_is_patient_bounded(self):
        source = php("lifecycle-rest")
        section = source.split("private static function retention_rows_for_patient", 1)[1].split("private static function access_page", 1)[0]
        self.assertIn("WHERE patient_uuid = %s", section)
        self.assertIn("count($rows) > 500", section)
        self.assertIn("count($rows) > 100", section)
        self.assertIn("array_intersect_key($row", section)

    def test_concurrent_command_is_409_not_forbidden(self):
        for controller in ("rest", "lifecycle-rest"):
            with self.subTest(controller=controller):
                source = php(controller)
                self.assertIn("'code' => 'clinical_conflict'", source)
                self.assertRegex(source, r"'clinical_conflict'.*?409, self::private_headers\(\)", re.DOTALL)
                self.assertIn("private static function is_conflict", source)

    def test_parent_clinical_boundaries_preserved(self):
        source = php("contracts")
        for method in ("membership", "recent_auth", "practitioner", "care_context", "notify",
                       "communication_context", "shell_routes", "visual_components", "secure_media",
                       "register_assurance"):
            self.assertIn("function " + method + "(", source)

    def test_legacy_file08_and_deployment_remain_gated(self):
        source = php("release-orchestrator")
        self.assertIn("validate_native_owner_contracts", source)
        self.assertIn("extract_file08_batch", source)
        self.assertIn("rollback_migration", source)
        plugin = (ROOT / "sabri-clinical-records" / "sabri-clinical-records.php").read_text(encoding="utf-8")
        self.assertIn("update_option('cf01_activation_state', 'disabled'", plugin)

    def test_future24_cannot_self_activate(self):
        source = php("future24-governance")
        self.assertIn("evidence_matches_current_activation", source)
        self.assertIn("guard_states", source)
        source = php("future24")
        self.assertIn("governance_ready", source)
        self.assertIn("require_enabled", source)

    def test_release_proof_separates_repository_from_live(self):
        status = (ROOT / "docs" / "RELEASE-STATUS.md").read_text(encoding="utf-8")
        for claim in ("Staging-Accepted: pending", "Live-Deployed: pending", "Operational: pending",
                      "database/schema state and migration state are separate realities"):
            self.assertIn(claim, status)


if __name__ == "__main__":
    unittest.main()
