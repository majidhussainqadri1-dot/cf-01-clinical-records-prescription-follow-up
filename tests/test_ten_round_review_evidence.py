import unittest
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
LEDGER = (ROOT / 'docs' / 'TEN-ROUND-REVIEW-2026-09-16.md').read_text(encoding='utf-8')
STATUS = (ROOT / 'docs' / 'RELEASE-STATUS.md').read_text(encoding='utf-8')
WORKFLOW = (ROOT / '.github' / 'workflows' / 'governance.yml').read_text(encoding='utf-8')


class TenRoundReviewEvidenceTests(unittest.TestCase):
    def test_all_ten_rounds_are_recorded_once(self):
        for number in range(1, 11):
            self.assertEqual(LEDGER.count(f'## Round {number} —'), 1)
        self.assertIn('Rounds 1, 2, 3, 4, 5, 6, 7, 8, 9 and 10', LEDGER)

    def test_review_process_freezes_audit_before_correction(self):
        self.assertIn('audited to completion first', LEDGER)
        self.assertIn('defect set frozen before', LEDGER)
        self.assertIn('No numbered next round began before', LEDGER)

    def test_release_status_distinguishes_base_and_future24_scope(self):
        self.assertIn('Base functional requirements traced: `32/32`', STATUS)
        self.assertIn('Stable Future24 capability IDs present: `24/24`', STATUS)
        self.assertIn('Reviewed implementation head: `30af7a4ad061a6778825a0d986beba3a77096205`', STATUS)
        self.assertIn('Successful implementation-release GitHub Actions run: `35053829202`', STATUS)
        self.assertIn('Staging-Accepted: pending', STATUS)
        self.assertIn('Live-Deployed: pending', STATUS)

    def test_workflow_labels_are_not_frozen_to_old_r1_r4_scope(self):
        self.assertIn('name: CF-01 Unified Governance and Runtime Gates', WORKFLOW)
        self.assertNotIn('Unified R1-R4 Governance and Runtime Gates', WORKFLOW)
        self.assertNotIn('PHP ${{ matrix.php }} R1-R4 clinical review', WORKFLOW)
        self.assertIn('canonical security regressions', WORKFLOW)


if __name__ == '__main__':
    unittest.main()
