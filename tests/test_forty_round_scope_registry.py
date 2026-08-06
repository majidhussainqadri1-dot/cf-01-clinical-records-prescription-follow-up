from pathlib import Path
import sys
import unittest

ROOT = Path(__file__).resolve().parents[1]
sys.path.insert(0, str(ROOT / "tools"))

import run_40_reviews as review_registry


class FortyRoundScopeRegistryTests(unittest.TestCase):
    def test_all_forty_review_scopes_pass_on_exact_head(self):
        self.assertEqual(list(range(1, 41)), [item.number for item in review_registry.ROUNDS])
        for item in review_registry.ROUNDS:
            try:
                item.review()
            except Exception as error:
                message = f"Round {item.number:02d} — {item.title}: {type(error).__name__}: {error}"
                print(f"::error title=CF-01 forty-round scope failure::{message}")
                self.fail(message)


if __name__ == "__main__":
    unittest.main()
