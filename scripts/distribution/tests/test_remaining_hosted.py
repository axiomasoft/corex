"""Synthetic boundary fixtures for remaining-cohort hosted preflight."""
from __future__ import annotations

import importlib.util
import json
import sys
import unittest
from pathlib import Path

ROOT = Path(__file__).resolve().parents[3]
DEFERRAL_FLAG = ROOT / 'scripts/.plan-close-deferred'
HOSTED = ROOT / 'scripts/distribution/remaining-cohort-hosted.py'
DEFERRAL = ROOT / 'scripts/distribution/plan_close_deferral.py'
deferral_spec = importlib.util.spec_from_file_location('plan_close_deferral', DEFERRAL)
deferral_module = importlib.util.module_from_spec(deferral_spec)
assert deferral_spec.loader is not None
deferral_spec.loader.exec_module(deferral_module)
spec = importlib.util.spec_from_file_location('remaining_cohort_hosted', HOSTED)
module = importlib.util.module_from_spec(spec)
assert spec.loader is not None
sys.modules['remaining_cohort_hosted'] = module
spec.loader.exec_module(module)


class RemainingCohortHostedTest(unittest.TestCase):
    def test_preflight_reports_blockers_without_remote_workflow(self):
        if DEFERRAL_FLAG.is_file():
            self.skipTest('preflight uses gh; blocked by D44 while plan-close deferred')
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

    def test_reconcile_refused_while_plan_close_deferred(self):
        if not DEFERRAL_FLAG.is_file():
            self.skipTest('plan-close deferral flag not active')
        with self.assertRaises(module.DeferralDenied):
            module.execute_reconcile()

    def test_finalize_uses_cached_receipt_without_gh(self):
        receipt = ROOT / 'delivery/remaining-cohort-20260915/hosted/receipt.json'
        if not receipt.is_file():
            self.skipTest('hosted receipt missing')
        outcome = module.finalize_report()
        self.assertIn(outcome['result'], {'PASS', 'BLOCKED'})
        self.assertIn('case_results', outcome)


if __name__ == '__main__':
    unittest.main()
