"""Synthetic boundary fixtures for remaining-cohort released install helper."""
from __future__ import annotations

import importlib.util
import sys
import unittest
from pathlib import Path

ROOT = Path(__file__).resolve().parents[3]
DIST = ROOT / 'scripts/distribution'
RELEASED = DIST / 'remaining-cohort-released.py'
spec = importlib.util.spec_from_file_location('remaining_cohort_released', RELEASED)
module = importlib.util.module_from_spec(spec)
assert spec.loader is not None
sys.modules['remaining_cohort_released'] = module
spec.loader.exec_module(module)


class RemainingCohortReleasedTest(unittest.TestCase):
    def test_delivery_manifest_declares_deferral(self):
        candidate = ROOT / 'delivery/remaining-cohort-20260915/candidate.json'
        if not candidate.is_file():
            self.skipTest('candidate not present')
        endpoint, manifest = module.stage_delivery_endpoint()
        self.assertEqual(manifest['schema_version'], 'released-delivery-manifest/1')
        self.assertTrue(manifest['delivery_urls'])
        self.assertTrue(manifest['download_sha256'])
        self.assertIn('PLAN-CLOSE-CI', manifest['deferral']['remote_github_install'])
        self.assertTrue((endpoint / 'packages.json').is_file())

    def test_delivery_endpoint_differs_from_installed_candidate(self):
        candidate = ROOT / 'delivery/remaining-cohort-20260915/candidate.json'
        if not candidate.is_file():
            self.skipTest('candidate not present')
        endpoint, _manifest = module.stage_delivery_endpoint()
        installed_repo = ROOT / 'delivery/remaining-cohort-20260915/installed-candidate/repository'
        if installed_repo.is_dir():
            self.assertNotEqual(endpoint.resolve(), installed_repo.resolve())


if __name__ == '__main__':
    unittest.main()
