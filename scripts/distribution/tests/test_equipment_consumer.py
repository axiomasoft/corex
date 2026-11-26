"""Equipment registry consumer scans."""
from __future__ import annotations

import importlib.util
import json
import sys
import tempfile
import unittest
from pathlib import Path

ROOT = Path(__file__).resolve().parents[3]
SCRIPT = ROOT / "scripts/distribution/verify_equipment_consumer.py"
SPEC = importlib.util.spec_from_file_location("verify_equipment_consumer", SCRIPT)
assert SPEC and SPEC.loader
MODULE = importlib.util.module_from_spec(SPEC)
sys.modules[SPEC.name] = MODULE
SPEC.loader.exec_module(MODULE)


class EquipmentConsumerTest(unittest.TestCase):
    def test_committed_consumer_has_no_forbidden_vocabulary(self) -> None:
        self.assertEqual(MODULE.banned_hits(MODULE.APP), [])

    def test_fixture_is_a_local_nonsymlink_module_with_a_laravel_consumer(self) -> None:
        with tempfile.TemporaryDirectory() as temporary:
            root = Path(temporary)
            consumer = root / "application"
            MODULE.prepare_install(MODULE.APP, consumer, root / "candidate")
            host = json.loads((consumer / "composer.json").read_text(encoding="utf-8"))
            package = json.loads((consumer / "fixture/composer.json").read_text(encoding="utf-8"))
            self.assertEqual(host["require"]["equipment/registry"], "@dev")
            self.assertEqual(host["require"]["laravel/framework"], "^13.0")
            self.assertEqual(host["repositories"][0]["options"], {"symlink": False})
            self.assertEqual(package["extra"]["packman"]["module"], r"Equipment\Registry\RegisteredModule")

    def test_flex_crm_dependency_is_rejected(self) -> None:
        composer = json.loads((MODULE.APP / "composer.json").read_text(encoding="utf-8"))
        self.assertFalse(MODULE.flex_dependency_fails(composer))
        planted = {**composer, "require": {**composer["require"], "flex/crm": "^1.0"}}
        self.assertTrue(MODULE.flex_dependency_fails(planted))
        self.assertNotIn("flex/crm", composer["require"])


if __name__ == "__main__":
    unittest.main()
