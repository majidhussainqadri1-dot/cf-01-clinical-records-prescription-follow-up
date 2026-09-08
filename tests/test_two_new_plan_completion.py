from pathlib import Path
import unittest

ROOT = Path(__file__).resolve().parents[1]
INCLUDES = ROOT / "sabri-clinical-records" / "includes"


class TwoNewPlanCompletionTests(unittest.TestCase):
    def source(self, name: str) -> str:
        return (INCLUDES / name).read_text(encoding="utf-8")

    def test_mutating_idempotent_command_is_atomic_with_completion_receipt(self):
        source = self.source("class-cf01-db.php")
        self.assertIn("return self::transaction(function () use ($operation, $receipt_uuid): array", source)
        self.assertIn("'status' => 'completed'", source)
        self.assertIn("Clinical database rollback failed.", source)

    def test_audit_chain_append_is_serialized_and_event_case_is_preserved(self):
        source = self.source("class-cf01-audit-outbox.php")
        self.assertIn("ORDER BY id DESC LIMIT 1 FOR UPDATE", source)
        self.assertIn("canonical_event_name", source)
        self.assertIn("'event_name' => $event", source)
        self.assertIn("$event_key = strtolower($event_name);", source)
        self.assertNotIn("'event_name' => sanitize_key($event)", source)

    def test_relationship_termination_uses_real_schema_and_reconciles_followups(self):
        source = self.source("class-cf01-relationships.php")
        self.assertIn("p INNER JOIN", source)
        self.assertIn("e ON e.encounter_uuid = p.encounter_uuid", source)
        self.assertIn("open_followups", source)
        self.assertIn("open_followups_resolved", source)
        self.assertIn("transfer_retention_reconciled", source)
        self.assertNotIn("FROM ' . CF01_DB::table('prescriptions') . ' WHERE relationship_uuid", source)

    def test_longitudinal_timeline_cursor_is_stable_on_time_and_uuid(self):
        source = self.source("class-cf01-rest.php")
        self.assertIn("AND (' . $time_field . ' < %s OR (' . $time_field . ' = %s AND ' . $id_field . ' < %s))", source)
        self.assertIn("AND (occurred_at < %s OR (occurred_at = %s AND event_uuid < %s))", source)
        self.assertIn("private static function decode_timeline_cursor(string $cursor, string $patient_uuid): ?array", source)
        self.assertIn("'uuid' => (string) $payload['uuid']", source)

    def test_own_record_really_projects_access_history(self):
        source = self.source("class-cf01-rest.php")
        self.assertIn("if (in_array('access_history', $fields, true))", source)
        self.assertIn("$result['access_history']", source)
        self.assertIn("'break_glass' =>", source)

    def test_conflicting_mutations_return_safe_409_semantics(self):
        rest = self.source("class-cf01-rest.php")
        lifecycle = self.source("class-cf01-lifecycle-rest.php")
        for source in (rest, lifecycle):
            self.assertIn("'code' => 'clinical_conflict'", source)
            self.assertIn("), 409, self::private_headers());", source)
            self.assertIn("private static function is_conflict(Throwable $error): bool", source)

    def test_retention_projection_follows_object_ownership_not_nonexistent_patient_column(self):
        source = self.source("class-cf01-lifecycle-rest.php")
        self.assertIn("retention_rows_for_patient", source)
        self.assertIn("WHERE object_uuid IN (", source)
        self.assertIn("'retention_uuid'", source)
        self.assertNotIn("'retention' => array('retention', 'ledger_uuid')", source)


if __name__ == "__main__":
    unittest.main()
