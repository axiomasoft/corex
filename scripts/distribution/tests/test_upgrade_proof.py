"""P17.7 upgrade baseline selection and named failure cases."""

from __future__ import annotations

import importlib.util
import json
import sys
import unittest
from pathlib import Path

ROOT = Path(__file__).resolve().parents[3]
SCRIPT = ROOT / "scripts/distribution/upgrade-proof.py"
FIXTURES = ROOT / "tests/Distribution/fixtures/upgrade"
SPEC = importlib.util.spec_from_file_location("upgrade_proof", SCRIPT)
assert SPEC and SPEC.loader
MODULE = importlib.util.module_from_spec(SPEC)
sys.modules[SPEC.name] = MODULE
SPEC.loader.exec_module(MODULE)


class UpgradeProofTest(unittest.TestCase):
    def test_latest_release_is_newer_than_older_tag(self) -> None:
        latest = MODULE.select_latest([
            {"tag": "v0.1.0-p4.3", "asset": "old.zip"},
            {"tag": "0.4.0-alpha.20260918", "asset": "new.zip"},
        ])
        self.assertEqual(latest["tag"], "0.4.0-alpha.20260918")

    def test_missing_assets_are_not_a_release(self) -> None:
        self.assertIsNone(MODULE.select_latest([{"tag": "0.4.0-alpha.20260918", "asset": None}]))

    def test_monorepo_tag_substitution_fails_by_name(self) -> None:
        row = json.loads((FIXTURES / "monorepo-substitution.json").read_text(encoding="utf-8"))
        with self.assertRaisesRegex(MODULE.UpgradeError, "monorepo tag substitution"):
            MODULE.assert_baseline_row(row)

    def test_wrong_baseline_digest_fails_by_name(self) -> None:
        with self.assertRaisesRegex(MODULE.UpgradeError, "wrong baseline digest"):
            MODULE.assert_baseline_digest("abc", "def")

    def test_stale_candidate_fails_by_name(self) -> None:
        with self.assertRaisesRegex(MODULE.UpgradeError, "stale candidate"):
            MODULE.assert_candidate("0" * 64, "1" * 64)

    def test_migration_fault_without_forward_recovery_fails_by_name(self) -> None:
        scenario = json.loads((FIXTURES / "migration-fault.json").read_text(encoding="utf-8"))
        with self.assertRaisesRegex(MODULE.UpgradeError, "migration fault without forward recovery"):
            MODULE.assert_migration_recovery(scenario["migrated"], scenario["recovered"])

    def test_forward_recovery_allows_the_fault(self) -> None:
        MODULE.assert_migration_recovery(False, True)

    def test_missing_cross_package_relation_is_recoverable(self) -> None:
        detail = 'ERROR:  relation "wsp_workspaces" does   \n  not exist'
        self.assertEqual(
            MODULE.recovery_sql(detail),
            'CREATE TABLE IF NOT EXISTS "wsp_workspaces" (id uuid PRIMARY KEY)',
        )

    def test_wrapped_users_key_mismatch_is_recoverable(self) -> None:
        detail = 'incompatible types: uuid an  \n  d bigint.'
        self.assertIn("DROP TABLE IF EXISTS users", MODULE.recovery_sql(detail) or "")

    def test_duplicate_users_table_is_recoverable(self) -> None:
        detail = 'SQLSTATE[42P07]: Duplicate table: 7 ERROR:  relation "users" already exists'
        self.assertEqual(MODULE.recovery_sql(detail), 'DROP TABLE IF EXISTS "users" CASCADE')

    def test_unrelated_migration_fault_has_no_recovery(self) -> None:
        self.assertIsNone(MODULE.recovery_sql("SQLSTATE[42601]: syntax error"))

    def test_generic_skip_fails_by_name(self) -> None:
        with self.assertRaisesRegex(MODULE.UpgradeError, "generic skip"):
            MODULE.assert_baseline_row({"classification": "skip"})

    def test_new_package_requires_remote_absence(self) -> None:
        with self.assertRaisesRegex(MODULE.UpgradeError, "missing remote absence"):
            MODULE.assert_baseline_row({"classification": "new-package", "reason": "not-applicable/new-package"})

    def test_new_package_with_absence_is_closed(self) -> None:
        MODULE.assert_baseline_row({
            "classification": "new-package",
            "prior_version": None,
            "archive_sha256": None,
            "reason": "not-applicable/new-package",
            "absence": {"observed": True, "status": 404},
        })


if __name__ == "__main__":
    unittest.main()
