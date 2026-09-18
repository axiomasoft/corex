"""Synthetic boundary fixtures for remaining-cohort hosted preflight."""
from __future__ import annotations

import importlib.util
import json
import sys
import unittest
from pathlib import Path

ROOT = Path(__file__).resolve().parents[3]
HOSTED = ROOT / 'scripts/distribution/remaining-cohort-hosted.py'
spec = importlib.util.spec_from_file_location('remaining_cohort_hosted', HOSTED)
module = importlib.util.module_from_spec(spec)
assert spec.loader is not None
sys.modules['remaining_cohort_hosted'] = module
spec.loader.exec_module(module)


class RemainingCohortHostedTest(unittest.TestCase):
    def test_preflight_reports_blockers_without_remote_workflow(self):
        packet_path = ROOT / 'delivery/remaining-cohort-20260915/runner-packet.json'
        if not packet_path.is_file():
            self.skipTest('runner packet not built yet')
        preflight = module.preflight()
        self.assertEqual(preflight['schema_version'], 'hosted-preflight/1')
        self.assertEqual(preflight['grant_required'], 'G-RUNNER')
        self.assertFalse(preflight['ready'])
        self.assertTrue(preflight['blockers'])

    def test_workflow_policy_fixture(self):
        workflow = ROOT / '.github/workflows/remaining-cohort-proof.yml'
        if not workflow.is_file():
            self.skipTest('workflow helper missing')
        self.assertEqual(module.verify_workflow_policy(workflow), 0)


if __name__ == '__main__':
    unittest.main()
