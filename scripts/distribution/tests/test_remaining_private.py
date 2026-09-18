"""Synthetic boundary fixtures for remaining-cohort private read packet."""
from __future__ import annotations

import importlib.util
import json
import sys
import tempfile
import unittest
from pathlib import Path

ROOT = Path(__file__).resolve().parents[3]
PRIVATE = ROOT / 'scripts/distribution/remaining-cohort-private.py'
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


if __name__ == '__main__':
    unittest.main()
