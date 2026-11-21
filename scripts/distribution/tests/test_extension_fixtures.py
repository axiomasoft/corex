"""P17.2 external extension fixture matrix."""
from __future__ import annotations

import importlib.util
import json
import sys
import tempfile
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
            "profile": "absent", "compiled_fields": ["title"], "mapping": "b" * 64, "assertions": 6,
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
            {"profile": "enabled"}, {"compiled_fields": []}, {"assertions": 0},
            {"stale_text_rejected": False},
            {"uid": "fixed-index"}, {"php": "8.4.27"},
        ):
            with self.subTest(changes=changes), self.assertRaises(SystemExit):
                MODULE.validate_search_witness({**witness, **changes}, databases, fixture, "8.5")

    def test_only_enabled_host_requires_optional_search_packages(self) -> None:
        fixture = ROOT / "fixtures/extensions/custom-search-provider"
        with tempfile.TemporaryDirectory() as directory:
            root = Path(directory)
            for profile in ("absent", "enabled"):
                consumer = root / profile
                MODULE.write_consumer(
                    consumer, fixture, root / "repository", drop_corex=False, laravel_host=True,
                    host_requirements=MODULE.FIELD_SEARCH_HOST if profile == "enabled" else None,
                )
                host = json.loads((consumer / "composer.json").read_text())
                provider = json.loads((consumer / "fixture/composer.json").read_text())
                self.assertEqual(set(host["require"]) & MODULE.FIELD_SEARCH_HOST.keys(),
                                 set(MODULE.FIELD_SEARCH_HOST) if profile == "enabled" else set())
                self.assertTrue(MODULE.FIELD_SEARCH_HOST.keys().isdisjoint(provider["require"]))

    def test_enabled_compilation_requires_saved_identity_current_witness_and_private_stores(self) -> None:
        fixture = next(spec["search_proof"] for spec in MODULE.FIXTURES if spec["id"] == "custom-search-provider")
        field = fixture["field_id"]
        databases = {"COREX_SCENARIO_DB_A": "enabled_owner_test", "COREX_SCENARIO_DB_CONTROL": "enabled_control_test"}
        absent = {"mapping": "a" * 64, "pid": 12, "owner": {"database": "absent_owner_test"}, "control": {"database": "absent_control_test"}}
        view_id = "01900000-0000-7000-8000-000000000001"
        witness = {
            "profile": "enabled", "entity": fixture["entity"], "driver": fixture["adapter"],
            "field_id": field, "compiled_fields": [field, "title"], "assertions": 20, "pid": 13, "php": "8.5.12",
            "mapping": "b" * 64, "renamed_mapping": "c" * 64,
            "schema_revision_before": "d" * 64, "schema_revision_after": "e" * 64,
            "schema_mapping_before": "f" * 64, "schema_mapping_after": "1" * 64,
            "view_id": view_id, "view_revision": "1", "view_grammar": "2" * 64, "view_projection": [field],
            "view_filter": {"field": field, "op": "eq", "value": "PUMP-1"}, "view_sort": [{"field": field, "direction": "asc"}],
            "dependencies": [f"saved-view:{view_id}"], "old_key": "asset_code", "new_key": "equipment_code", "masked_rejected": True,
            "old_label": "Asset code", "new_label": "Equipment code", "masked_error": "panels.invalid_saved_view",
            "owner": {"database": "enabled_owner_test", "session": 10, "oid": "20", "backend": "PostgreSQL 18.6"},
            "control": {"database": "enabled_control_test", "session": 11, "oid": "21", "backend": "PostgreSQL 18.6"},
        }
        MODULE.validate_field_search_compilation(witness, databases, fixture, absent, "8.5")
        for changes in (
            {"compiled_fields": ["title"]}, {"view_projection": ["asset_code"]}, {"masked_rejected": False},
            {"schema_revision_after": witness["schema_revision_before"]}, {"mapping": absent["mapping"]},
            {"dependencies": []}, {"assertions": 0}, {"pid": absent["pid"]}, {"php": "8.4.27"},
            {"owner": {**witness["owner"], "database": absent["owner"]["database"]}},
        ):
            with self.subTest(changes=changes), self.assertRaises(SystemExit):
                MODULE.validate_field_search_compilation({**witness, **changes}, databases, fixture, absent, "8.5")


if __name__ == "__main__":
    unittest.main()
