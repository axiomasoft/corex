"""P17.5 equipment registry consumer scans."""
from __future__ import annotations

import importlib.util
import json
import sys
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

    def test_flex_crm_dependency_is_rejected(self) -> None:
        composer = json.loads((MODULE.APP / "composer.json").read_text(encoding="utf-8"))
        self.assertFalse(MODULE.flex_dependency_fails(composer))
        planted = {**composer, "require": {**composer["require"], "flex/crm": "^1.0"}}
        self.assertTrue(MODULE.flex_dependency_fails(planted))
        self.assertNotIn("flex/crm", composer["require"])


if __name__ == "__main__":
    unittest.main()
