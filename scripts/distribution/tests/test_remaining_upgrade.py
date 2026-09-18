"""Synthetic boundary fixtures for remaining-cohort installed upgrade proofs."""
from __future__ import annotations

import importlib.util
import json
import sys
import tempfile
import unittest
from pathlib import Path

ROOT = Path(__file__).resolve().parents[3]
INSTALLED = ROOT / 'scripts/distribution/remaining-cohort-installed.py'
spec = importlib.util.spec_from_file_location('remaining_cohort_installed', INSTALLED)
module = importlib.util.module_from_spec(spec)
assert spec.loader is not None
sys.modules['remaining_cohort_installed'] = module
spec.loader.exec_module(module)


class RemainingCohortUpgradeTest(unittest.TestCase):
    def test_fixture_manifest_present(self):
        manifest = ROOT / 'delivery/remaining-cohort-20260915/fixtures/populated-baseline-manifest.json'
        self.assertTrue(manifest.is_file())
        payload = json.loads(manifest.read_text())
        self.assertEqual(payload['schema_version'], 'remaining-cohort-populated-baseline/1')
        self.assertGreaterEqual(len(payload['domain']['rows']), 2)

    def test_refusal_scenario_expects_nonzero(self):
        scenario = json.loads(
            (ROOT / 'delivery/remaining-cohort-20260915/fixtures/refusal-scenario.json').read_text()
        )
        self.assertTrue(scenario['expected_exit_nonzero'])

    def test_install_06_refusal(self):
        candidate = ROOT / 'delivery/remaining-cohort-20260915/candidate.json'
        if not candidate.is_file():
            self.skipTest('candidate artifact not built yet')
        self.assertEqual(module.verify_named('INSTALL-06'), 0)

    def test_install_07_forward_recovery(self):
        with tempfile.TemporaryDirectory() as temporary:
            root = Path(temporary)
            result = module.verify_install_07(root)
            self.assertEqual(result['recovery'], 'forward-retry')
            self.assertEqual(len(result['migrations']), 2)


if __name__ == '__main__':
    unittest.main()
