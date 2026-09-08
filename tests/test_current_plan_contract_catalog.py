from pathlib import Path
import unittest

ROOT = Path(__file__).resolve().parents[1]
PLUGIN = ROOT / "sabri-clinical-records"
INCLUDES = PLUGIN / "includes"


class CurrentPlanContractCatalogueTests(unittest.TestCase):
    def read(self, path: Path) -> str:
        return path.read_text(encoding="utf-8")

    def corpus(self) -> str:
        return "\n".join(p.read_text(encoding="utf-8") for p in INCLUDES.glob("*.php"))

    def test_new_plan_surfaces_are_loaded_and_registered(self):
        plugin = self.read(PLUGIN / "sabri-clinical-records.php")
        self.assertIn("'class-cf01-plan-contracts.php'", plugin)
        self.assertIn("'class-cf01-plan-rest.php'", plugin)
        self.assertIn("array('CF01_Plan_REST', 'register_routes')", plugin)

    def test_explicit_command_catalogue_is_present(self):
        corpus = self.corpus()
        commands = [
            "LinkPlatformIdentity",
            "MergeOrQuarantineDuplicatePatient",
            "ProposeCareRelationship",
            "ActivateCareRelationship",
            "PresentConsent",
            "RecordConsent",
            "WithdrawClinicalConsent",
            "RenewConsent",
            "AddClinicalObservation",
            "ReviewAttachment",
            "SignEncounter",
            "SignPrescription",
            "SupersedePrescription",
            "DiscontinuePrescription",
            "PlanFollowUp",
            "SubmitPatientOutcome",
            "ReviewFollowUp",
            "RescheduleFollowUp",
            "CloseFollowUp",
            "RequestBreakGlass",
            "GrantBreakGlass",
            "RevokeBreakGlass",
            "CloseBreakGlassReview",
            "AppealRightsDecision",
            "FulfillClinicalExport",
            "RequestCorrectionResolve",
            "TriggerRetention",
            "PlaceClinicalHold",
            "ReleaseClinicalHold",
            "PurgeEligibleRecord",
        ]
        for command in commands:
            with self.subTest(command=command):
                self.assertIn(command, corpus)

    def test_explicit_query_catalogue_has_callable_coverage(self):
        plan = self.read(INCLUDES / "class-cf01-plan-contracts.php")
        plan_rest = self.read(INCLUDES / "class-cf01-plan-rest.php")
        core = self.read(INCLUDES / "class-cf01-rest.php")
        lifecycle = self.read(INCLUDES / "class-cf01-lifecycle-rest.php")
        required_new = [
            "my_consents",
            "my_active_instructions",
            "relationship_eligibility",
            "field_authorization",
            "clinical_record_version",
            "safety_alerts",
            "unsigned_encounters",
            "pending_followups",
            "care_transfer_status",
            "access_anomalies",
            "break_glass_review_queue",
            "retention_due",
            "restore_reconciliation",
            "export_package_status",
        ]
        for query in required_new:
            with self.subTest(query=query):
                self.assertIn(f"function {query}", plan)
        for route_fragment in [
            "my-consents",
            "my-active-instructions",
            "relationship-eligibility",
            "field-authorization",
            "record-version",
            "safety-alerts",
            "unsigned-encounters",
            "pending-followups",
            "care-transfer-status",
            "access-anomalies",
            "break-glass-review-queue",
            "retention-due",
            "restore-reconciliation",
            "export-status",
        ]:
            with self.subTest(route=route_fragment):
                self.assertIn(route_fragment, plan_rest)
        # Existing canonical query surfaces remain the implementation of the rest of the plan catalogue.
        self.assertIn("'/me'", core)  # GetMyClinicalRecord
        self.assertIn("'/patients/(?P<patient>[a-f0-9-]{36})'", core)  # GetPatientClinicalSummary
        self.assertIn("'/encounters/(?P<id>[a-f0-9-]{36})'", core)  # GetEncounter
        self.assertIn("timeline_rows", core)  # GetLongitudinalTimeline
        self.assertIn("access_history", lifecycle)  # GetAccessHistory

    def test_canonical_integration_events_are_emitted(self):
        corpus = self.corpus()
        events = [
            "ClinicalPatientLinked",
            "CareRelationshipActivated",
            "CareRelationshipEnded",
            "ClinicalConsentGranted",
            "ClinicalConsentWithdrawn",
            "GuardianAuthorityChanged",
            "EncounterSigned",
            "EncounterAddended",
            "PrescriptionSigned",
            "PrescriptionSuperseded",
            "FollowUpPlanned",
            "FollowUpOverdue",
            "FollowUpReviewed",
            "ClinicalBreakGlassGranted",
            "ClinicalBreakGlassUsed",
            "ClinicalBreakGlassReviewed",
            "ClinicalCorrectionResolved",
            "ClinicalExportFulfilled",
            "ClinicalRetentionHoldApplied",
        ]
        for event in events:
            with self.subTest(event=event):
                self.assertIn(event, corpus)

    def test_break_glass_request_grant_use_review_close_are_separate(self):
        source = self.read(INCLUDES / "class-cf01-break-glass.php")
        for method in ["begin_request", "grant", "assertion", "review", "close_review"]:
            self.assertIn(f"function {method}", source)
        for state in ["'requested'", "'granted'", "'used'", "'reviewed'", "'closed'"]:
            self.assertIn(state, source)

    def test_consent_presentation_and_reconsent_preserve_history(self):
        source = self.read(INCLUDES / "class-cf01-consents.php")
        self.assertIn("function present", source)
        self.assertIn("function renew", source)
        self.assertIn("ClinicalConsentPresented", source)
        self.assertIn("ClinicalConsentRenewed", source)
        self.assertIn("'renewal_of'", source)

    def test_pre_activation_migration_restore_commands_remain_disabled_runtime_safe(self):
        source = self.read(INCLUDES / "class-cf01-plan-rest.php")
        self.assertIn("'/governance/file08-extraction'", source)
        self.assertIn("'/governance/migrations/(?P<id>[a-f0-9-]{36})/rollback'", source)
        self.assertIn("'/governance/restore/verify'", source)
        self.assertIn("governance_mutate", source)
        plugin = self.read(PLUGIN / "sabri-clinical-records.php")
        self.assertIn("update_option('cf01_activation_state', 'disabled'", plugin)


if __name__ == "__main__":
    unittest.main()
