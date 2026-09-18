"""Synthetic boundary fixtures for remaining-cohort runner plan."""
from __future__ import annotations

import importlib.util
import json
import sys
import tempfile
import unittest
from pathlib import Path

ROOT = Path(__file__).resolve().parents[3]
RUNNER = ROOT / 'scripts/distribution/remaining-cohort-runner.py'
spec = importlib.util.spec_from_file_location('remaining_cohort_runner', RUNNER)
module = importlib.util.module_from_spec(spec)
assert spec.loader is not None
sys.modules['remaining_cohort_runner'] = module
spec.loader.exec_module(module)


class RemainingCohortRunnerTest(unittest.TestCase):
    def test_runner_plan_schema(self):
        matrix_path = ROOT / 'delivery/remaining-cohort-20260915/local/matrix.json'
        if not matrix_path.is_file():
            self.skipTest('matrix artifact not built yet')
        with tempfile.TemporaryDirectory() as temporary:
            plan = module.build_runner_plan(matrix_path)
            self.assertEqual(plan['schema_version'], 'runner-plan/1')
            self.assertFalse(plan['dispatch']['protected'])
            self.assertGreater(len(plan['jobs']), 0)
            output = Path(temporary) / 'runner-plan.json'
            module.write_json(output, plan)
            self.assertEqual(module.verify_runner_plan(output), 0)


if __name__ == '__main__':
    unittest.main()
