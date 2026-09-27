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
            self.assertFalse(any(name.startswith("corex/") and constraint != "^1.0" for name, constraint in composer["require"].items()))

    def test_workspace_detector_sees_monorepo_paths(self) -> None:
        self.assertTrue(MODULE.autoload_contains_workspace(str(ROOT / "vendor/autoload.php"), ROOT))
        self.assertFalse(MODULE.autoload_contains_workspace("/tmp/consumer/vendor/autoload.php", ROOT))


if __name__ == "__main__":
    unittest.main()
