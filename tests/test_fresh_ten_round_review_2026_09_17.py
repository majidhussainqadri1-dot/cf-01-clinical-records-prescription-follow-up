from pathlib import Path
import unittest

ROOT = Path(__file__).resolve().parents[1]
LEDGER = (ROOT / "docs/TEN-ROUND-FRESH-REVIEW-2026-09-17.md").read_text(encoding="utf-8")
STATUS = (ROOT / "docs/RELEASE-STATUS.md").read_text(encoding="utf-8")


class FreshTenRoundReviewEvidenceTests(unittest.TestCase):
    def test_all_fresh_rounds_are_recorded_once(self):
        for number in range(1, 11):
            self.assertEqual(LEDGER.count(f"## Round {number} —"), 1)

    def test_audit_first_protocol_is_explicit(self):
        self.assertIn("audited to completion first", LEDGER)
        self.assertIn("defect set was frozen before", LEDGER)
        self.assertIn("No numbered next round began before", LEDGER)

    def test_defect_rounds_are_explicit(self):
        self.assertIn("Rounds 2, 7 and 10", LEDGER)
        self.assertIn("Rounds **1, 3, 4, 5, 6, 8 and 9**", LEDGER)

    def test_release_status_tracks_reviewed_runtime_baseline(self):
        baseline = "b8deab13c8aebf6cfdfb407a47f2e0c634ea4996"
        self.assertIn(f"Reviewed implementation head: `{baseline}`", STATUS)
        self.assertIn("Successful implementation-release GitHub Actions run: `35182433635`", STATUS)
        self.assertIn("Python tests in release job: `146 PASS`", STATUS)
        self.assertIn("Installable ZIP SHA-256: `5477ae58c9516e4a08e132fdfe95879fa4e671e042fb336b1e0daff5b71bf0a3`", STATUS)
        self.assertIn("SPDX 2.3 SBOM SHA-256: `f9b54e480b29ace15c2ebd9995a4c19482f21c2992f4d121a757b51693da9baa`", STATUS)

    def test_live_evidence_boundary_is_preserved(self):
        self.assertIn("does not prove staging or live deployment", LEDGER)
        self.assertIn("Staging-Accepted: pending", STATUS)
        self.assertIn("Live-Deployed: pending", STATUS)


if __name__ == "__main__":
    unittest.main()
