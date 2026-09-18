"""Synthetic boundary fixtures for remaining-cohort publication helper."""
from __future__ import annotations

import importlib.util
import sys
import unittest
from pathlib import Path

ROOT = Path(__file__).resolve().parents[3]
DIST = ROOT / 'scripts/distribution'
sys.path.insert(0, str(DIST))
PUBLISH = DIST / 'remaining-cohort-publish.py'
spec = importlib.util.spec_from_file_location('remaining_cohort_publish', PUBLISH)
module = importlib.util.module_from_spec(spec)
assert spec.loader is not None
sys.modules['remaining_cohort_publish'] = module
spec.loader.exec_module(module)
from publish_guard import PublicationDenied


class RemainingCohortPublicationTest(unittest.TestCase):
    def test_read_only_plan_denies_writes(self):
        attestation = ROOT / 'delivery/remaining-cohort-20260915/build/build-attestation.json'
        if not attestation.is_file():
            self.skipTest('build attestation not present')
        plan = module.prepare_plan(write=False)
        self.assertEqual(plan['write_attempted'], False)
        self.assertEqual(plan['transport_writes'], [])
        with self.assertRaises(PublicationDenied):
            module.prepare_plan(write=True)

    def test_every_operation_is_denied_without_grant(self):
        attestation = ROOT / 'delivery/remaining-cohort-20260915/build/build-attestation.json'
        if not attestation.is_file():
            self.skipTest('build attestation not present')
        plan = module.prepare_plan()
        self.assertTrue(plan['operations'])
        for row in plan['operations']:
            self.assertTrue(str(row['result']).startswith('denied:'))


if __name__ == '__main__':
    unittest.main()
