#!/usr/bin/env python3
"""Isolated negative tests for plan evidence gates; never consumer acceptance."""
import contextlib
import hashlib
import importlib.util
import io
from pathlib import Path
import shutil
import tempfile
import unittest


def module(name, path):
    spec = importlib.util.spec_from_file_location(name, path)
    loaded = importlib.util.module_from_spec(spec)
    spec.loader.exec_module(loaded)
    return loaded


PLAN = Path(__file__).resolve().parent.parent
gate = module('design_gate', PLAN / 'scripts/task-check.py')
p11 = module('p11_gate', PLAN / 'scripts/validate-p11.py')


class DesignSealTests(unittest.TestCase):
    @classmethod
    def setUpClass(cls):
        cls.temp = tempfile.TemporaryDirectory(prefix='corex-design-guard-')
        cls.plan = Path(cls.temp.name) / 'plan'
        shutil.copytree(PLAN, cls.plan)
        # Synthetic seal is confined to a disposable copy. It tests enforcement,
        # not whether the real author's semantic review has completed.
        rows = '\n'.join(
            f'| {path} | {hashlib.sha256((cls.plan / path).read_bytes()).hexdigest()} |'
            for path in sorted(gate.design_inputs(cls.plan))
        )
        (cls.plan / 'artifacts/design-finish.md').write_text(
            '# Synthetic isolated fixture\n\n**Design finish:** verified\n\n'
            '## Design inputs\n\n| Path | SHA256 |\n|:--|:--|\n' + rows + '\n'
        )

    @classmethod
    def tearDownClass(cls):
        cls.temp.cleanup()

    def check(self):
        with contextlib.redirect_stdout(io.StringIO()):
            gate.design_ready(self.plan)

    def changed_bytes_fail(self, name):
        path = self.plan / name
        before = path.read_bytes()
        try:
            path.write_bytes(before + b'\nchanged indirect input\n')
            with self.assertRaises(ValueError):
                self.check()
        finally:
            path.write_bytes(before)

    def test_unchanged_design_passes(self):
        self.check()

    def test_original_criterion_drift_fails(self):
        self.changed_bytes_fail('research/01a-acceptance-index.md')

    def test_gate_wording_drift_fails(self):
        self.changed_bytes_fail('research/03-gates.md')

    def test_early_validator_drift_fails(self):
        self.changed_bytes_fail('scripts/validate-p1.py')

    def test_new_research_drift_fails(self):
        self.changed_bytes_fail('research/06-azguard-patterns.md')

    def test_supplementary_raw_retrieval_drift_fails(self):
        self.changed_bytes_fail('artifacts/astra-whole-plan-review/perplexity-response-05.json')

    def test_added_indirect_research_requires_new_seal(self):
        path = self.plan / 'research/unsealed-new-premise.md'
        try:
            path.write_text('New premise must be reviewed.\n')
            with self.assertRaises(ValueError):
                self.check()
        finally:
            path.unlink()

    def test_deleted_snapshot_fails(self):
        path = self.plan / 'brief/sources/b36.md'
        before = path.read_bytes()
        try:
            path.unlink()
            with self.assertRaises((ValueError, OSError)):
                self.check()
        finally:
            path.write_bytes(before)

    def test_rolling_journal_does_not_invalidate_design(self):
        path = self.plan / 'journal.jsonl'
        before = path.read_bytes()
        try:
            path.write_bytes(before + b'{"synthetic_test_only":true}\n')
            self.check()
        finally:
            path.write_bytes(before)


class ConsumerVerifierTests(unittest.TestCase):
    def setUp(self):
        self.temp = tempfile.TemporaryDirectory(prefix='corex-p11-guard-')
        self.plan = Path(self.temp.name)
        for folder in ('scripts', 'findings', 'artifacts'):
            (self.plan / folder).mkdir()
        raw = self.plan / 'artifacts/observations.json'
        raw.write_text('{"synthetic":true,"expected":1,"actual":1}\n')
        checksum = hashlib.sha256(raw.read_bytes()).hexdigest()
        cases = '\n'.join(f'| {case} | PASS | 1 | 1 | E1 |' for case in sorted(p11.wanted('intake')))
        self.report = 'findings/P11-intake.md'
        (self.plan / self.report).write_text(
            '# Synthetic fixture\n\np11-reconciliation-report/1\n\n'
            '## Cases\n\n| ID | Result | Expected | Actual | Evidence |\n|:--|:--|:--|:--|:--|\n'
            + cases + '\n\n## Inputs\n\n| Path | SHA256 |\n|:--|:--|\n'
            + f'| artifacts/observations.json | {checksum} |\n\n'
            + '## Evidence\n\n| ID | Repo | Commit | Stage | Actor proof | Covered atoms | Artifact | SHA256 | Command | Runtime | Start | End | Exit | Outcome |\n'
            + '|:--|:--|:--|:--|:--|:--|:--|:--|:--|:--|:--|:--|:--|:--|\n'
            + f'| E1 | fixture | fixture | synthetic | fixture | fixture | artifacts/observations.json | {checksum} | synthetic | Python | 1 | 2 | 0 | PASS |\n'
        )

    def tearDown(self):
        self.temp.cleanup()

    def verifier(self, exit_code):
        (self.plan / 'scripts/reconcile-consumer.py').write_text(
            'import pathlib, sys\n'
            'assert sys.argv[1] == "--verify"\n'
            'assert pathlib.Path(sys.argv[2]).is_absolute()\n'
            'assert pathlib.Path(sys.argv[2]).name == "P11-intake.md"\n'
            f'raise SystemExit({exit_code})\n'
        )

    def test_equal_expected_actual_is_valid_with_bound_observation(self):
        self.verifier(0)
        p11.report(self.plan, 'intake')

    def test_structural_pass_cannot_override_verifier_refusal(self):
        self.verifier(7)
        with self.assertRaisesRegex(ValueError, 'prepared consumer verifier red'):
            p11.report(self.plan, 'intake')

    def test_missing_prepared_verifier_refuses(self):
        with self.assertRaises((ValueError, OSError)):
            p11.report(self.plan, 'intake')

    def test_tampered_observation_refuses_even_with_accepting_verifier(self):
        self.verifier(0)
        (self.plan / 'artifacts/observations.json').write_text('{"actual":0}\n')
        with self.assertRaises(ValueError):
            p11.report(self.plan, 'intake')


if __name__ == '__main__':
    unittest.main(verbosity=2)
