"""P17.2 external extension fixture matrix."""
from __future__ import annotations

import importlib.util
import json
import sys
import unittest
from pathlib import Path

ROOT = Path(__file__).resolve().parents[3]
SCRIPT = ROOT / "scripts/distribution/verify_extension_fixtures.py"
SPEC = importlib.util.spec_from_file_location("verify_extension_fixtures", SCRIPT)
assert SPEC and SPEC.loader
MODULE = importlib.util.module_from_spec(SPEC)
sys.modules[SPEC.name] = MODULE
SPEC.loader.exec_module(MODULE)


class ExtensionFixtureTest(unittest.TestCase):
    def test_diagnostic_names_fixture_and_forbidden_symbol(self) -> None:
        for spec in MODULE.FIXTURES:
            diagnostic = MODULE.forbidden_diagnostic(spec["package"], spec["forbidden"])
            self.assertIn(spec["package"], diagnostic)
            self.assertIn(spec["forbidden"], diagnostic)
            self.assertIn("Internal", diagnostic)

    def test_fixture_autoload_is_package_local(self) -> None:
        for spec in MODULE.FIXTURES:
            fixture = ROOT / "fixtures/extensions" / spec["id"]
            MODULE.assert_source_autoload(fixture, ROOT)
            composer = json.loads((fixture / "composer.json").read_text(encoding="utf-8"))
            self.assertEqual(composer["name"], spec["package"])
            self.assertFalse(any(MODULE.is_corex_package(name) and constraint not in {"^2.0", "^2.0@dev"} for name, constraint in composer["require"].items()))

    def test_field_witness_requires_typed_result_sealed_fact_and_distinct_databases(self) -> None:
        fixture = {"entity": "cas.equipment", "values": {"inspection_code": "A-42"},
                   "filter_operand": "inspection:A-42"}
        databases = {"COREX_SCENARIO_DB_A": "owner_test", "COREX_SCENARIO_DB_CONTROL": "control_test"}
        witness = {
            **fixture, "fact": "fields.values.changed.v2", "sealed": True, "denied_filter": True,
            "schema_revision": "revision", "message_id": "fact", "policy_witness": "grant",
            "pid": 9, "php": "8.4.27", "field_ids": ["id"],
            "owner": {"database": "owner_test", "session": 10, "oid": "20", "backend": "PostgreSQL 16.14"},
            "control": {"database": "control_test", "session": 11, "oid": "21", "backend": "PostgreSQL 16.14"},
        }
        MODULE.validate_field_witness(witness, databases, fixture)
        for changes in (
            {"fact": "fields.values.changed.v1"},
            {"sealed": False},
            {"pid": 0},
            {"field_ids": []},
            {"values": {"inspection_code": "inspection:A-42"}},
            {"control": {**witness["control"], "session": 10}},
            {"control": {**witness["control"], "oid": "20"}},
        ):
            with self.subTest(changes=changes), self.assertRaises(SystemExit):
                MODULE.validate_field_witness({**witness, **changes}, databases, fixture)

    def test_workspace_detector_sees_monorepo_paths(self) -> None:
        self.assertTrue(MODULE.autoload_contains_workspace(str(ROOT / "vendor/autoload.php"), ROOT))
        self.assertFalse(MODULE.autoload_contains_workspace("/tmp/consumer/vendor/autoload.php", ROOT))

    def test_search_requires_public_entrypoint_and_actual_tagged_scoped_candidate_driver(self) -> None:
        fixture = next(spec["search_proof"] for spec in MODULE.FIXTURES if spec["id"] == "custom-search-provider")
        databases = {"COREX_SCENARIO_DB_A": "owner_test", "COREX_SCENARIO_DB_CONTROL": "control_test"}
        witness = {
            **fixture, "entrypoint": r"CoreX\Search\Query\Contracts\Search", "tagged": True,
            "candidate_calls": 1, "projection": {"title": "pump alpha"}, "count": "unavailable",
            "stale_text_rejected": True,
            "uid": "cx_" + "a" * 64, "generation": 1, "php": "8.5.12", "pid": 12,
            "owner": {"database": "owner_test", "session": 10, "oid": "20", "backend": "PostgreSQL 18.6"},
            "control": {"database": "control_test", "session": 11, "oid": "21", "backend": "PostgreSQL 18.6"},
        }
        MODULE.validate_search_witness(witness, databases, fixture, "8.5")
        for changes in (
            {"entrypoint": r"CoreX\Search\Query\Contracts\CandidateDriver"},
            {"tagged": False}, {"candidate_calls": 0}, {"count": "exact"},
            {"stale_text_rejected": False},
            {"uid": "fixed-index"}, {"php": "8.4.27"},
        ):
            with self.subTest(changes=changes), self.assertRaises(SystemExit):
                MODULE.validate_search_witness({**witness, **changes}, databases, fixture, "8.5")


if __name__ == "__main__":
    unittest.main()
