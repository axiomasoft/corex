"""P17.6 pristine profile matrix negatives and constraint edges."""

from __future__ import annotations

import importlib.util
import json
import sys
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
        if isinstance(name, str) and name.startswith("corex/"):
            found[name] = payload
    return found


class ProfileProofTest(unittest.TestCase):
    def setUp(self) -> None:
        self.manifest = json.loads(MODULE.MANIFEST_PATH.read_text(encoding="utf-8"))
        self.composers = composers()

    def test_manifest_has_the_six_profiles(self) -> None:
        self.assertEqual(
            [profile["id"] for profile in self.manifest["profiles"]],
            ["bare-single", "modular-single", "modular-multi", "worker-only", "search-portable", "full-corex"],
        )

    def test_missing_profile_names_the_profile(self) -> None:
        with self.assertRaises(MODULE.ProfileError) as caught:
            MODULE.require_profile(self.manifest, "no-such-profile")
        self.assertIn("missing profile: no-such-profile", str(caught.exception))

    def test_missing_package_names_the_package(self) -> None:
        with self.assertRaises(MODULE.ProfileError) as caught:
            MODULE.expand_packages({"packages": ["corex/missing-package"]}, set(self.composers))
        self.assertIn("missing package: corex/missing-package", str(caught.exception))

    def test_undeclared_skip_names_the_cell(self) -> None:
        with self.assertRaises(MODULE.ProfileError) as caught:
            MODULE.assert_cell_closed({"id": "bare-single-php8.4-l12", "status": "skip"})
        self.assertIn("undeclared skip: bare-single-php8.4-l12", str(caught.exception))

    def test_workspace_path_is_named(self) -> None:
        with self.assertRaises(MODULE.ProfileError) as caught:
            MODULE.assert_no_workspace(str(ROOT / "vendor/autoload.php"), ROOT)
        self.assertIn("workspace path", str(caught.exception))

    def test_bare_single_names_modules_meilisearch_and_search_intl(self) -> None:
        profile = MODULE.require_profile(self.manifest, "bare-single")
        for package, name in (
            ("corex/modules", "modules"),
            ("corex/search-meilisearch", "Meilisearch"),
            ("corex/search-intl", "Search Intl"),
        ):
            with self.subTest(name=name):
                with self.assertRaises(MODULE.ProfileError) as caught:
                    MODULE.assert_profile_clean(profile, ["corex/core", package])
                self.assertIn(name, str(caught.exception))
                self.assertIn("bare-single", str(caught.exception))

    def test_search_portable_names_meilisearch(self) -> None:
        profile = MODULE.require_profile(self.manifest, "search-portable")
        with self.assertRaises(MODULE.ProfileError) as caught:
            MODULE.assert_profile_clean(profile, ["corex/core", "corex/search", "corex/search-meilisearch"])
        self.assertIn("Meilisearch", str(caught.exception))

    def test_removed_search_intl_is_named(self) -> None:
        profile = {**MODULE.require_profile(self.manifest, "search-portable"), "remove_observed": ["corex/search-intl"]}
        with self.assertRaises(MODULE.ProfileError) as caught:
            MODULE.assert_profile_clean(profile, ["corex/core", "corex/search", "corex/search-intl"])
        self.assertIn("Search Intl", str(caught.exception))

    def test_constraints_do_not_cross_every_package_with_every_lane(self) -> None:
        self.assertEqual(MODULE.declared_laravel_majors(self.composers["corex/access"]), (12,))
        self.assertEqual(MODULE.declared_laravel_majors(self.composers["corex/core"]), (12, 13))
        self.assertEqual(MODULE.declared_laravel_majors(self.composers["corex/recurrence"]), ())
        self.assertFalse(MODULE.allows(MODULE.php_constraint(self.composers["corex/tenancy"]), (8, 3, 0)))
        self.assertTrue(MODULE.allows(MODULE.php_constraint(self.composers["corex/tenancy"]), (8, 4, 1)))
        signatures = {tuple(row["packages"][:1]): row["laravel"] for row in MODULE.group_packages(self.composers)}
        self.assertEqual(signatures[("corex/access",)], [12])
        self.assertNotIn(13, signatures[("corex/access",)])
        self.assertEqual(signatures[("corex/recurrence",)], [])

    def test_full_corex_laravel_13_is_excluded_by_access(self) -> None:
        cells = MODULE.plan_profiles(self.manifest, self.composers, list(AVAILABLE))
        excluded = next(cell for cell in cells if cell["id"] == "full-corex-php8.4-l13")
        self.assertEqual(excluded["status"], "excluded")
        self.assertIn("corex/access", excluded["reason"])
        included = [cell["id"] for cell in cells if cell["profile"] == "bare-single" and cell["status"] == "included"]
        self.assertEqual(included, ["bare-single-php8.4-l12", "bare-single-php8.4-l13"])

    def test_tenancy_php_83_is_excluded_by_name(self) -> None:
        cells = MODULE.plan_edges(self.composers, list(AVAILABLE))
        excluded = next(cell for cell in cells if cell["id"] == "edge-excluded-php8.3-corex/tenancy")
        self.assertEqual(excluded["status"], "excluded")
        self.assertIn("corex/tenancy", excluded["reason"])
        self.assertIn("8.4", excluded["reason"])


if __name__ == "__main__":
    unittest.main()
