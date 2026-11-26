import json
import os
from pathlib import Path
import re
import subprocess
import sys
import tempfile
import unittest

ROOT = Path(__file__).resolve().parents[2]
WORKFLOW = (ROOT / '.github/workflows/ci.yml').read_text()
JOBS = set(re.findall(r'^  ([\w-]+):$', WORKFLOW.split('\njobs:\n')[1], re.MULTILINE)) - {'ci-gate'}
OPTIONAL = {'mutation-fragments', 'mutation-check'}
ESSENTIAL = {'changes', 'lint', 'anti-weakening-gate', 'secret-scan', 'contract-guard'}


class VerdictTest(unittest.TestCase):
    def verdict(self, required, *, code='true', selected='', mutation=False, overrides=None, error=None):
        needs = {job: {'result': 'success' if job in required else 'skipped'} for job in JOBS}
        needs['changes']['outputs'] = {'code': code}
        for job, result in (overrides or {}).items():
            needs[job]['result'] = result
        with tempfile.TemporaryDirectory() as directory:
            env = dict(os.environ, NEEDS=json.dumps(needs), SELECTED=selected, MUTATION=str(mutation).lower(),
                       GITHUB_STEP_SUMMARY=str(Path(directory) / 'summary'))
            run = subprocess.run([sys.executable, ROOT / '.github/scripts/ci-verdict.py'],
                                 env=env, capture_output=True, text=True)
        if error:
            self.assertNotEqual(0, run.returncode)
            self.assertIn(error, run.stdout)
        else:
            self.assertEqual(0, run.returncode, run.stdout + run.stderr)

    def test_final_gate_covers_every_job_in_the_workflow(self):
        gate = WORKFLOW.split('\n  ci-gate:\n')[1]
        needs = re.search(r'    needs:\n((?:      - [\w-]+\n)+)', gate)[1]
        self.assertEqual(JOBS, set(re.findall(r'- ([\w-]+)', needs)))
        self.assertIn("inputs.jobs && 'Selected CI lanes' || 'CI gate'", gate)
        self.assertIn("inputs.jobs && 'selected' || 'full'", WORKFLOW)
        self.assertIn("github.event.before", WORKFLOW)

    def test_code_requires_every_regular_lane_including_mutation_inventory(self):
        self.verdict(JOBS - OPTIONAL)
        for lane in ('tests', 'mutation-inventory', 'anti-weakening-gate'):
            with self.subTest(lane=lane):
                self.verdict(JOBS - OPTIONAL - {lane}, error=lane + ': skipped')

    def test_docs_still_require_the_independent_gates(self):
        self.verdict(ESSENTIAL, code='false')
        self.verdict(ESSENTIAL - {'secret-scan'}, code='false', error='secret-scan: skipped')

    def test_selection_requires_requested_jobs_and_rejects_typographical_errors(self):
        self.verdict({'lint', 'changes', 'tests-pg'}, selected='tests-pg')
        self.verdict({'lint', 'changes'}, selected='tests-pg', error='tests-pg: skipped')
        self.verdict({'lint', 'changes'}, selected='test-pg', error='Unknown or non-selectable')

    def test_cancellation_or_missing_classification_fails(self):
        self.verdict(ESSENTIAL, code='false', overrides={'tests': 'cancelled'}, error='tests: cancelled')
        self.verdict(JOBS, code='', error='Missing path classification')

    def test_requested_mutation_cannot_skip_the_workers(self):
        self.verdict(JOBS - OPTIONAL, mutation=True, error='mutation-fragments: skipped')
        self.verdict(JOBS, mutation=True)
