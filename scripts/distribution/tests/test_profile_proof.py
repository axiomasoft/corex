"""Pristine profile matrix negatives and constraint edges."""

from __future__ import annotations

import importlib.util
import json
import shutil
import sys
import tempfile
import unittest
from pathlib import Path

ROOT = Path(__file__).resolve().parents[3]
SCRIPT = ROOT / "scripts/distribution/profile-proof.py"
SPEC = importlib.util.spec_from_file_location("profile_proof", SCRIPT)
assert SPEC and SPEC.loader
MODULE = importlib.util.module_from_spec(SPEC)
sys.modules[SPEC.name] = MODULE
SPEC.loader.exec_module(MODULE)

AVAILABLE = (
    {"minor": "8.3", "binary": "/usr/bin/php8.3", "version": (8, 3, 0)},
    {"minor": "8.4", "binary": "/usr/bin/php8.4", "version": (8, 4, 1)},
    {"minor": "8.5", "binary": "/usr/bin/php8.5", "version": (8, 5, 0)},
)


def composers() -> dict[str, dict]:
    found = {}
    for path in sorted((ROOT / "packages").glob("*/composer.json")):
        payload = json.loads(path.read_text(encoding="utf-8"))
        name = payload.get("name")
        if isinstance(name, str) and name.startswith("axiomasoft/corex"):
            found[name] = payload
    return found


class ProfileProofTest(unittest.TestCase):
    def setUp(self) -> None:
        self.manifest = json.loads(MODULE.MANIFEST_PATH.read_text(encoding="utf-8"))
        self.composers = composers()

    def test_bridge_profiles_require_cached_di_state(self) -> None:
        for id, expected in (("search-fields", "absent"), ("search-fields-disabled", "disabled"), ("search-fields-enabled", "enabled")):
            profile = MODULE.require_profile(self.manifest, id)
            enabled = expected == "enabled"
            boot = {"search_custom_fields": {"installed": expected != "absent", "enabled": None if expected == "absent" else enabled,
                                             "subscriber": enabled, "contributors": ["FieldProjectionContributor"] if enabled else []}}
            self.assertEqual(expected, MODULE.assert_fields_bridge(profile, boot)["search_custom_fields"]["state"])
            boot["search_custom_fields"]["subscriber"] = not enabled
            with self.assertRaisesRegex(MODULE.ProfileError, f"does not match {expected}"):
                MODULE.assert_fields_bridge(profile, boot)

    def test_task_profiles_require_exact_cached_capture_composition(self) -> None:
        for profile_id, expected in (("tasks-workflow-absent", "absent"), ("tasks-workflow-disabled", "disabled"),
                                     ("tasks-workflow-enabled", "enabled")):
            with self.subTest(profile=profile_id):
                profile = MODULE.require_profile(self.manifest, profile_id)
                enabled = expected == "enabled"
                actual = {
                    "installed": expected != "absent", "enabled": None if expected == "absent" else enabled,
                    "definition_ref": "fixture:workflow" if enabled else None,
                    "version_ref": "fixture:version1" if enabled else None,
                    "contributor_count": int(enabled),
                }
                evidence = MODULE.assert_tasks_workflow(profile, {"tasks_workflow": actual})
                self.assertEqual(expected, evidence["tasks_workflow"]["state"])
                actual["contributor_count"] += 1
                with self.assertRaisesRegex(MODULE.ProfileError, f"does not match {expected}"):
                    MODULE.assert_tasks_workflow(profile, {"tasks_workflow": actual})

    def test_unsupported_task_lifetime_pin_survives_cached_configuration(self) -> None:
        profile = {"id": "activation-proof", "tasks_workflow": "unsupported-version"}
        actual = {
            "installed": True, "enabled": True, "definition_ref": "fixture:workflow", "version_ref": "fixture:missing-version",
            "contributor_count": 1,
        }
        evidence = MODULE.assert_tasks_workflow(profile, {"tasks_workflow": actual})
        self.assertEqual("unsupported-version", evidence["tasks_workflow"]["state"])
        actual["version_ref"] = "fixture:version1"
        with self.assertRaisesRegex(MODULE.ProfileError, "does not match unsupported-version"):
            MODULE.assert_tasks_workflow(profile, {"tasks_workflow": actual})

    def test_equipment_fixture_identity_uses_committed_consumer_package(self) -> None:
        spec = {"id": "equipment-registry", "version": "1.0.0", "kind": "equipment"}
        self.assertEqual(Path("fixtures/consumers/equipment-registry"), MODULE.extension_source(spec))
        self.assertEqual("equipment/registry", MODULE.fixture_package_name(spec))
        with self.assertRaisesRegex(MODULE.ProfileError, "fixture kind"):
            MODULE.extension_source({**spec, "id": "another-consumer"})

    def test_declared_profile_ids_must_match_definitions(self) -> None:
        MODULE.validate_profiles(self.manifest)
        self.manifest["profiles"].pop()
        with self.assertRaisesRegex(MODULE.ProfileError, "profile set mismatch"):
            MODULE.validate_profiles(self.manifest)

    def test_every_owner_has_a_standalone_cell_per_supported_php(self) -> None:
        cells = MODULE.plan_edges(self.composers, list(AVAILABLE))
        for owner in self.composers:
            with self.subTest(owner=owner):
                actual = {
                    cell["php"]
                    for cell in cells
                    if cell["status"] == "included" and cell["packages"] == [owner] and not cell["prefer_lowest"]
                }
                self.assertEqual(actual, {"8.4", "8.5"})

    def test_composition_does_not_count_as_standalone(self) -> None:
        cells = MODULE.plan_edges(self.composers, list(AVAILABLE))
        results = [
            {**cell, "status": "passed", "installed": cell["packages"]}
            for cell in cells
            if cell["status"] == "included" and cell["packages"] != ["axiomasoft/corex-search"]
        ]
        results.append({"kind": "profile", "status": "passed", "packages": list(self.composers), "installed": list(self.composers)})
        gaps = MODULE.coverage_gaps(self.composers, results, list(AVAILABLE))
        self.assertIn("missing edge: axiomasoft/corex-search php8.5/l13", gaps)

    def test_missing_profile_names_the_profile(self) -> None:
        with self.assertRaises(MODULE.ProfileError) as caught:
            MODULE.require_profile(self.manifest, "no-such-profile")
        self.assertIn("missing profile: no-such-profile", str(caught.exception))

    def test_missing_package_names_the_package(self) -> None:
        with self.assertRaises(MODULE.ProfileError) as caught:
            MODULE.expand_packages({"packages": ["corex/missing-package"]}, set(self.composers))
        self.assertIn("missing package: corex/missing-package", str(caught.exception))

    def test_extension_is_loaded_from_a_committed_copy_and_rejects_drift(self) -> None:
        with tempfile.TemporaryDirectory() as work:
            consumer = Path(work)
            extension = {"id": "custom-attribute-source", "version": "1.0.0"}
            (consumer / "composer.json").write_text(json.dumps({"require": {}, "repositories": []}))
            MODULE.install_extension_source(consumer, ROOT, extension)
            name = "thirdparty/custom-attribute-source"
            source = consumer / "scenario-extension"
            installed = consumer / "vendor" / name
            shutil.copytree(source, installed)
            (consumer / "composer.lock").write_text(json.dumps({"packages": [{
                "name": name, "version": "1.0.0", "dist": {"type": "path", "url": "scenario-extension"},
            }]}))
            proof = MODULE.assert_fixture_extension(consumer, extension)
            self.assertEqual(name, proof["name"])
            self.assertEqual(64, len(proof["source_sha256"]))
            (installed / "composer.json").write_text("{}")
            with self.assertRaisesRegex(MODULE.ProfileError, "does not match the committed source copy"):
                MODULE.assert_fixture_extension(consumer, extension)

    def test_product_provenance_binds_the_physical_copy_to_lock_and_source(self) -> None:
        with tempfile.TemporaryDirectory() as work:
            consumer = Path(work)
            source = consumer / "flex-crm"
            MODULE.copy_committed_fixture(ROOT, Path("products/flex-crm"), source)
            installed = consumer / "vendor/flex/crm"
            shutil.copytree(source, installed)
            (consumer / "vendor/composer").mkdir()
            locked = {"name": "flex/crm", "version": "1.0.x-dev",
                      "dist": {"type": "path", "url": "flex-crm", "reference": "a" * 40}}
            (consumer / "composer.lock").write_text(json.dumps({"packages": [locked]}))
            metadata = consumer / "vendor/composer/installed.json"
            metadata.write_text(json.dumps({"packages": [locked]}))

            proof = MODULE.assert_fixture_product(consumer)
            self.assertEqual(proof["source_sha256"], proof["installed_sha256"])
            self.assertEqual(proof["dist"], locked["dist"])
            for version in ("1.0.x-dev", "1.0.x-dev aaaaaaa"):
                shown = {"locked": [{"name": "flex/crm", "version": version}]}
                self.assertEqual(MODULE.locked_summary(shown, product=proof), ["flex/crm:1.0.x-dev"])
            shown = {"locked": [{"name": "flex/crm", "version": "1.0.x-dev bbbbbbb"}]}
            with self.assertRaisesRegex(MODULE.ProfileError, "disagrees with the verified flex/crm version and reference"):
                MODULE.locked_summary(shown, product=proof)
            metadata.write_text(json.dumps({"packages": [{**locked, "dist": {**locked["dist"], "url": "../../products/flex-crm"}}]}))
            with self.assertRaisesRegex(MODULE.ProfileError, "locked relative source distribution"):
                MODULE.assert_fixture_product(consumer)
            metadata.write_text(json.dumps({"packages": [locked]}))
            (installed / "composer.json").write_text("{}")
            with self.assertRaisesRegex(MODULE.ProfileError, "committed source copy"):
                MODULE.assert_fixture_product(consumer)

    def test_optional_host_opis_is_pinned_dist_and_never_a_corex_shadow(self) -> None:
        with tempfile.TemporaryDirectory() as work:
            consumer = Path(work)
            manifest = consumer / "composer.json"
            manifest.write_text(json.dumps({"require": {}}))
            for requirements in ({"axiomasoft/corex": "2.0.0"}, {"opis/json-schema": "^2.6"}):
                with self.subTest(requirements=requirements), self.assertRaisesRegex(MODULE.ProfileError, "unknown or unpinned"):
                    MODULE.add_host_requirements(consumer, requirements)
            pins = {"opis/json-schema": "2.6.2"}
            MODULE.add_host_requirements(consumer, pins)
            self.assertEqual(json.loads(manifest.read_text())["require"], pins)
            vendor = consumer / "vendor"
            (vendor / "composer").mkdir(parents=True)
            (vendor / "opis/json-schema").mkdir(parents=True)
            (vendor / "opis/json-schema/composer.json").write_text(json.dumps({"name": "opis/json-schema"}))
            dist = {"type": "zip", "url": "https://api.github.com/repos/opis/json-schema/zipball/example", "reference": "a" * 40}
            package = {"name": "opis/json-schema", "version": "2.6.2", "dist": dist}
            (consumer / "composer.lock").write_text(json.dumps({"packages": [package]}))
            installed = vendor / "composer/installed.json"
            observed = {**package, "installation-source": "dist", "install-path": "../opis/json-schema"}
            installed.write_text(json.dumps({"packages": [observed]}))
            proof = MODULE.assert_host_requirements(consumer, pins)
            self.assertEqual(proof["opis/json-schema"]["dist"], dist)
            installed.write_text(json.dumps({"packages": [{**observed, "installation-source": "source"}]}))
            with self.assertRaisesRegex(MODULE.ProfileError, "pinned release dist"):
                MODULE.assert_host_requirements(consumer, pins)

    def test_undeclared_skip_names_the_cell(self) -> None:
        with self.assertRaises(MODULE.ProfileError) as caught:
            MODULE.assert_cell_closed({"id": "bare-single-php8.4-l12", "status": "skip"})
        self.assertIn("undeclared skip: bare-single-php8.4-l12", str(caught.exception))

    def test_workspace_path_is_named(self) -> None:
        with self.assertRaises(MODULE.ProfileError) as caught:
            MODULE.assert_no_workspace(str(ROOT / "vendor/autoload.php"), ROOT)
        self.assertIn("workspace path", str(caught.exception))

    def test_bare_single_names_meilisearch_and_search_intl(self) -> None:
        profile = MODULE.require_profile(self.manifest, "bare-single")
        for package, name in (
            ("axiomasoft/corex-search-meilisearch", "Meilisearch"),
            ("axiomasoft/corex-search-intl", "Search Intl"),
        ):
            with self.subTest(name=name):
                with self.assertRaises(MODULE.ProfileError) as caught:
                    MODULE.assert_profile_clean(profile, ["axiomasoft/corex", package])
                self.assertIn(name, str(caught.exception))
                self.assertIn("bare-single", str(caught.exception))

    def test_search_portable_names_meilisearch(self) -> None:
        profile = MODULE.require_profile(self.manifest, "search-portable")
        with self.assertRaises(MODULE.ProfileError) as caught:
            MODULE.assert_profile_clean(profile, ["axiomasoft/corex", "axiomasoft/corex-search", "axiomasoft/corex-search-meilisearch"])
        self.assertIn("Meilisearch", str(caught.exception))

    def test_removed_search_intl_is_named(self) -> None:
        profile = {**MODULE.require_profile(self.manifest, "search-portable"), "remove_observed": ["axiomasoft/corex-search-intl"]}
        with self.assertRaises(MODULE.ProfileError) as caught:
            MODULE.assert_profile_clean(profile, ["axiomasoft/corex", "axiomasoft/corex-search", "axiomasoft/corex-search-intl"])
        self.assertIn("Search Intl", str(caught.exception))

    def test_constraints_do_not_cross_every_package_with_every_lane(self) -> None:
        self.assertEqual(MODULE.declared_laravel_majors(self.composers["axiomasoft/corex-access"]), (13,))
        self.assertEqual(MODULE.declared_laravel_majors(self.composers["axiomasoft/corex"]), (13,))
        self.assertEqual(MODULE.declared_laravel_majors(self.composers["axiomasoft/corex-recurrence"]), ())
        self.assertFalse(MODULE.allows(MODULE.php_constraint(self.composers["axiomasoft/corex-tenancy"]), (8, 3, 0)))
        self.assertTrue(MODULE.allows(MODULE.php_constraint(self.composers["axiomasoft/corex-tenancy"]), (8, 4, 1)))
        groups = {name: row["laravel"] for row in MODULE.group_packages(self.composers) for name in row["packages"]}
        self.assertEqual(groups["axiomasoft/corex-access"], [13])
        self.assertEqual(groups["axiomasoft/corex-recurrence"], [])

    def test_profiles_follow_current_laravel_support(self) -> None:
        cells = MODULE.plan_profiles(self.manifest, self.composers, list(AVAILABLE))
        full = next(cell for cell in cells if cell["id"] == "full-corex-php8.4-l13")
        self.assertEqual(full["status"], "included")
        reason = MODULE.exclusion_reason(["axiomasoft/corex-access"], self.composers, 12, (8, 4, 1))
        self.assertIn("axiomasoft/corex-access", reason or "")
        included = [cell["id"] for cell in cells if cell["profile"] == "bare-single" and cell["status"] == "included"]
        self.assertEqual(included, ["bare-single-php8.4-l13"])

    def test_tenancy_php_83_is_excluded_by_name(self) -> None:
        cells = MODULE.plan_edges(self.composers, list(AVAILABLE))
        excluded = next(cell for cell in cells if cell["status"] == "excluded" and cell["php"] == "8.3" and "axiomasoft/corex-tenancy" in cell["packages"])
        self.assertEqual(excluded["status"], "excluded")
        self.assertIn(excluded["packages"][0], excluded["reason"])
        self.assertIn("8.4", excluded["reason"])


if __name__ == "__main__":
    unittest.main()
