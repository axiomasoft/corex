"""P17.6 pristine profile matrix negatives and constraint edges."""

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
