import os
import subprocess
import tempfile
import unittest
from pathlib import Path

SCRIPTS = Path(__file__).resolve().parents[1]
LEDGER = 'tests/Golden/REBASELINE.md'
BASELINE = 'tests/Golden/baseline/routes.json'
SCHEMA = 'tests/Golden/schema/tenant.pgsql.json'
FLOOR = 'scripts/coverage-min-floor.txt'


def row(decision, reason='Changes the route contract'):
    return f'| 2026-11-25 | {decision} | routes | {reason} |\n'


class HistoryTest(unittest.TestCase):
    def setUp(self):
        directory = tempfile.TemporaryDirectory()
        self.addCleanup(directory.cleanup)
        self.root = Path(directory.name)
        self.env = os.environ.copy()
        for key in ('GIT_DIR', 'GIT_WORK_TREE', 'GIT_INDEX_FILE'):
            self.env.pop(key, None)
        self.git('init', '--quiet', '-b', 'feature')
        self.git('config', 'user.name', 'Test')
        self.git('config', 'user.email', 'test@localhost')
        self.write(LEDGER, row('Existing'))
        self.write(BASELINE, '[]')
        self.write(SCHEMA, '[]')
        self.write(FLOOR, '80\n')
        self.write('tests/ExampleTest.php', "it('runs', fn () => true)->group('pg');\n")
        self.base = self.commit()
        self.git('update-ref', 'refs/remotes/origin/main', self.base)

    def git(self, *args):
        return subprocess.check_output(['git', *args], cwd=self.root, env=self.env, text=True).strip()

    def write(self, name, value):
        path = self.root / name
        path.parent.mkdir(parents=True, exist_ok=True)
        path.write_text(value)

    def append(self, decision, reason='Changes the route contract'):
        with (self.root / LEDGER).open('a') as ledger:
            ledger.write(row(decision, reason))

    def commit(self):
        self.git('add', '.')
        self.git('commit', '--quiet', '-m', 'Fixture')
        return self.git('rev-parse', 'HEAD')

    def gate(self, *args, error=None):
        run = subprocess.run(['bash', str(SCRIPTS / 'anti-weakening-gate.sh'), *args],
                             cwd=self.root, env=self.env, capture_output=True, text=True)
        output = run.stdout + run.stderr
        if error is None:
            self.assertEqual(0, run.returncode, output)
        else:
            self.assertNotEqual(0, run.returncode, output)
            self.assertIn(error, output)

    def test_new_receipt_covers_snapshots_in_its_commit_and_survives_a_clean_merge(self):
        self.write(BASELINE, '[1]')
        self.write(SCHEMA, '[1]')
        self.append('First revision')
        self.commit()
        self.git('switch', '--quiet', '-c', 'parallel', self.base)
        self.write('parallel', 'another change')
        self.commit()
        self.git('merge', '--quiet', '--no-ff', '-m', 'Merge feature', 'feature')
        self.gate(self.base)

    def test_schema_changes_need_their_own_receipt(self):
        self.write(SCHEMA, '[1]')
        sha = self.commit()
        self.gate(error=sha)

    def test_untracked_snapshot_and_projection_inputs_need_a_receipt(self):
        for path in ('tests/Golden/schema/new.sqlite.json', 'tests/Golden/package-projection.json',
                     'tests/Golden/promoted-fixtures.json', 'tests/Golden/rename-map.json'):
            with self.subTest(path=path):
                self.write(path, '{}')
                self.gate(error='worktree')
                (self.root / path).unlink()

    def test_editing_an_old_row_or_adding_then_deleting_a_receipt_does_not_authorize(self):
        self.write(BASELINE, '[1]')
        self.write(LEDGER, row('Existing', 'Edited rationale'))
        first = self.commit()
        self.gate(error=first)
        self.append(f'First revision (commit {first})')
        self.commit()
        self.gate()
        self.write(LEDGER, row('Existing', 'Edited rationale'))
        self.commit()
        self.gate(error=first)

    def test_reformatted_duplicate_or_hidden_rows_are_not_receipts(self):
        self.write(BASELINE, '[1]')
        original = (self.root / LEDGER).read_text()
        for extra in (original.replace(' | ', '  |   '), '<!--\n' + row('Hidden') + '-->',
                      '```md\n' + row('Example') + '```', row('Missing reason', '  ')):
            with self.subTest(extra=extra):
                self.write(LEDGER, original + extra)
                self.gate(error='worktree')

    def test_late_receipt_claims_only_the_sha_in_its_decision(self):
        self.write(BASELINE, '[1]')
        first = self.commit()
        self.write(BASELINE, '[2]')
        self.append(f'First revision (commit {first})')
        second = self.commit()
        self.gate(error=second)
        self.append('Mentions a hash', 'Earlier hash ' + second)
        self.commit()
        self.gate(error=second)
        self.append(f'Second revision (commit {second})')
        self.commit()
        self.gate()

    def test_short_or_multiple_shas_cannot_authorize_a_receipt(self):
        self.write(SCHEMA, '[1]')
        sha = self.commit()
        for decision in (f'Repair (commit {sha[:9]})', f'Repair {self.base} (commit {sha})'):
            with self.subTest(decision=decision):
                self.append(decision)
                self.gate(error='exactly one')
                self.git('restore', LEDGER)

    def test_translation_in_a_separate_commit_cannot_authorize_a_future_change(self):
        self.write(LEDGER, row('Translated'))
        self.commit()
        self.write(BASELINE, '[1]')
        sha = self.commit()
        self.gate(error=sha)

    def test_main_push_uses_previous_event_sha_even_when_origin_main_is_head(self):
        self.write(FLOOR, '79\n')
        self.commit()
        self.write('docs/info.md', 'Second commit in the push')
        tip = self.commit()
        self.git('update-ref', 'refs/remotes/origin/main', tip)
        self.gate(self.base, error='coverage floor lowered')

    def test_missing_before_at_main_tip_checks_the_parent_and_rejects_a_dirty_reduction(self):
        self.write(FLOOR, '79\n')
        tip = self.commit()
        self.git('update-ref', 'refs/remotes/origin/main', tip)
        self.gate('0' * 40, error='coverage floor lowered')
        self.write(FLOOR, '78\n')
        self.gate(tip, error='coverage floor lowered')

    def test_a_raised_floor_cannot_be_rolled_back_inside_the_same_pr(self):
        self.write(FLOOR, '85\n')
        self.commit()
        self.write(FLOOR, '81\n')
        self.commit()
        self.gate(self.base, error='coverage floor lowered')

    def test_package_test_skips_are_checked_even_in_nested_directories(self):
        self.write('packages/example/tests/Feature/Nested/AccessTest.php', "it('regression')->skip();\n")
        self.commit()
        self.gate(error='->skip(')

    def test_an_existing_skip_can_update_its_diagnostic(self):
        path = 'packages/example/tests/Feature/OptionalAdapterTest.php'
        self.write(path, "test()->markTestSkipped('Original diagnostic');\n")
        before = self.commit()
        self.write(path, "test()->markTestSkipped('An isolated Redis server is required');\n")
        self.commit()
        self.gate(before)

    def test_existing_failure_controls_reject_semantic_changes(self):
        cases = [
            ('tests/OptionalTest.php', "if ($missing) test()->markTestSkipped('Needed');", "test()->markTestSkipped('Needed');", 'markTestSkipped'),
            ('tests/PestTest.php', "it('works')->skip($offline);", "it('works')->skip(true);", '->skip('),
            ('.github/workflows/ci.yml', 'continue-on-error: false', 'continue-on-error: true', 'continue-on-error'),
        ]
        for path, original, weakened, error in cases:
            with self.subTest(path=path):
                self.write(path, original + '\n')
                before = self.commit()
                self.write(path, weakened + '\n')
                self.commit()
                self.gate(before, error=error)

    def test_invalid_committed_floor_is_rejected(self):
        self.write(FLOOR, '80disabled\n')
        self.commit()
        self.gate(error='floor missing or invalid')
