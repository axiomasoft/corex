"""Synthetic boundary fixtures for remaining-cohort export policy."""
from __future__ import annotations

import importlib.util
import json
import sys
import tempfile
import unittest
from pathlib import Path

ROOT = Path(__file__).resolve().parents[3]
SCRIPT = ROOT / 'scripts/distribution/remaining-cohort-export.py'
spec = importlib.util.spec_from_file_location('remaining_cohort_export', SCRIPT)
module = importlib.util.module_from_spec(spec)
assert spec.loader is not None
sys.modules['remaining_cohort_export'] = module
spec.loader.exec_module(module)


class RemainingCohortExportTest(unittest.TestCase):
    def setUp(self):
        self.candidate_path = ROOT / 'delivery/remaining-cohort-20260915/candidate.json'
        self.candidate = json.loads(self.candidate_path.read_text())

    def test_reproducible_zip_hashes(self):
        self.assertEqual(module.verify_reproducible(self.candidate_path), 0)

    def test_collision_refusal(self):
        self.assertEqual(module.verify_collision_refusal(self.candidate_path), 0)

    def test_build_writes_attestation(self):
        with tempfile.TemporaryDirectory() as temporary:
            build = module.build_once(self.candidate_path, Path(temporary))
            module.write_outputs(build, Path(temporary) / 'delivery')
            attestation = json.loads((Path(temporary) / 'delivery/build/build-attestation.json').read_text())
            self.assertEqual(attestation['candidate_sha256'], module.sha256_path(self.candidate_path))
            self.assertEqual(len(attestation['packages']), len(self.candidate['public_packages']))


if __name__ == '__main__':
    unittest.main()
