"""Synthetic boundary fixtures for remaining-cohort private read packet."""
from __future__ import annotations

import importlib.util
import json
import sys
import tempfile
import unittest
from pathlib import Path

ROOT = Path(__file__).resolve().parents[3]
DEFERRAL_FLAG = ROOT / 'scripts/.plan-close-deferred'
PRIVATE = ROOT / 'scripts/distribution/remaining-cohort-private.py'
DEFERRAL = ROOT / 'scripts/distribution/plan_close_deferral.py'
deferral_spec = importlib.util.spec_from_file_location('plan_close_deferral', DEFERRAL)
deferral_module = importlib.util.module_from_spec(deferral_spec)
assert deferral_spec.loader is not None
deferral_spec.loader.exec_module(deferral_module)
spec = importlib.util.spec_from_file_location('remaining_cohort_private', PRIVATE)
module = importlib.util.module_from_spec(spec)
assert spec.loader is not None
sys.modules['remaining_cohort_private'] = module
spec.loader.exec_module(module)


class RemainingCohortPrivateTest(unittest.TestCase):
    def test_private_packet_schema(self):
        candidate = ROOT / 'delivery/remaining-cohort-20260915/candidate.json'
        if not candidate.is_file():
            self.skipTest('candidate artifact not built yet')
        packet = module.build_private_read_packet()
        self.assertEqual(packet['schema_version'], 'private-read-packet/1')
        self.assertTrue(packet['protected'])
        self.assertEqual(packet['grant_required'], 'G-PRIVATE')
        self.assertFalse(any(operation.get('write') for operation in packet['operations']))
        with tempfile.TemporaryDirectory() as temporary:
            output = Path(temporary) / 'private-read-packet.json'
            module.write_json(output, packet)
            self.assertEqual(module.verify_private_read_packet(output), 0)

    def test_guard_refuses_write(self):
        self.assertEqual(module.verify_guard_refuses_write(), 0)

    def test_preflight_ready_when_artifacts_exist(self):
        if not (ROOT / 'delivery/remaining-cohort-20260915/private-read-packet.json').is_file():
            self.skipTest('private-read packet not built yet')
        preflight = module.preflight()
        self.assertEqual(preflight['schema_version'], 'private-preflight/1')
        self.assertEqual(preflight['grant_required'], 'G-PRIVATE')
        self.assertTrue(preflight['ready'])
        self.assertFalse(preflight['blockers'])

    def test_reconcile_refused_while_plan_close_deferred(self):
        if not DEFERRAL_FLAG.is_file():
            self.skipTest('plan-close deferral flag not active')
        with self.assertRaises(module.DeferralDenied):
            module.execute_reconcile()

    def test_finalize_replays_cached_private_receipt(self):
        receipt = ROOT / 'delivery/remaining-cohort-20260915/private/receipt.json'
        if not receipt.is_file():
            self.skipTest('private receipt missing')
        outcome = module.finalize_report()
        self.assertIn(outcome['result'], {'PASS', 'BLOCKED'})
        self.assertIn('case_results', outcome)


if __name__ == '__main__':
    unittest.main()
