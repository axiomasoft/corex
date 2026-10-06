"""Exercise the real pre/verify-pre/post CLI against an isolated git repository."""
import hashlib
import importlib.util
import json
import subprocess
import sys
import tempfile
import unittest
from pathlib import Path

SCRIPT = Path(__file__).resolve().parents[1] / 'facts-guard.py'
spec = importlib.util.spec_from_file_location('facts_guard', SCRIPT)
guard = importlib.util.module_from_spec(spec)
spec.loader.exec_module(guard)


class FactsGuardTest(unittest.TestCase):
    def setUp(self):
        self.temp = tempfile.TemporaryDirectory()
        self.addCleanup(self.temp.cleanup)
        self.root = Path(self.temp.name)
        self.plan = self.root / guard.PLAN
        self.build = self.root / 'build'
        self.build.mkdir()
        self.put('.gitignore', 'build/\n')
        self.put('docs/reference.md', 'unchanged documentation\n')
        self.put('phpstan-baseline.neon', 'parameters:\n    ignoreErrors:\n        - count: 1\n')
        self.put('phpstan.neon', 'includes:\n    - phpstan-extensions.neon\n    - phar://phpstan.phar/conf/bleedingEdge.neon\nparameters:\n    level: max\n    paths:\n        - scripts\n')
        self.put('packages/core/phpstan.neon', (self.root / 'phpstan.neon').read_text().replace('scripts', 'src'))
        self.put('packages/core/phpstan-baseline.neon', '        count: 1\n')
        self.put('packages/core/composer.json', json.dumps({'name': 'axiomasoft/corex', 'require': {'php': '^8.4'}}))
        self.put('scripts/schema-gate.php', 'old schema\n')
        self.put('.github/workflows/ci.yml', 'old ci\n')
        self.put('scripts/quality-ceilings.json', json.dumps({'schema': 'test', 'phpstan': {'root': 1, 'core': 1, 'access-azguard': 0}, 'dependencies': {}}))
        self.put(str(guard.FACTS_SCRIPT), r'''import hashlib,json,subprocess,re
from pathlib import Path
r=Path(__file__).resolve().parents[5]
count=lambda p:sum(map(int,re.findall(r'count: (\d+)',(r/p).read_text())))
m=json.loads((r/'packages/core/composer.json').read_text())
c=count('packages/core/phpstan-baseline.neon');t=count('phpstan-baseline.neon')
print(json.dumps({'source_commit':subprocess.check_output(['git','rev-parse','HEAD'],cwd=r,text=True).strip(),
'packages':[{'path':'packages/core','name':m['name'],'require':m['require'],'baseline_count':c,'baseline_entries':c}],
'root_baseline_count':t,'baseline_total':c+t,'anchors':{p:hashlib.sha256((r/p).read_bytes()).hexdigest() for p in ['scripts/schema-gate.php','.github/workflows/ci.yml']}}))
''')
        self.put(str(guard.PLAN / 'phases/P08/P08.10.json'), json.dumps({'status': 'done'}))
        self.put(str(guard.PLAN / 'phases/P09/P09.1.json'), json.dumps({'task_files': [{'path': p} for p in
                 ['phpstan*.neon', 'packages/core/phpstan*.neon', 'scripts/quality-ceilings.json']]}))
        self.git('init', '-q')
        self.git('config', 'user.email', 'fixture@example.test')
        self.git('config', 'user.name', 'Fixture')
        self.commit()
        self.snapshot = guard.measure(self.root)
        self.put('scripts/schema-gate.php', 'closed schema\n')
        self.put('.github/workflows/ci.yml', 'closed ci\n')
        self.commit()
        self.ci_sha = self.git('rev-parse', 'HEAD').strip()
        self.put(str(guard.PLAN / 'artifacts/run.json'), json.dumps({'id': 42, 'head_sha': self.ci_sha, 'status': 'completed', 'conclusion': 'success'}))
        self.put(str(guard.PLAN / 'artifacts/jobs.json'), json.dumps({'total_count': 1, 'jobs': [{'head_sha': self.ci_sha, 'status': 'completed', 'conclusion': 'success'}]}))
        self.commit()
        self.current = guard.measure(self.root)
        self.rebase = {'exact_deltas': [{**r, 'owning_item': 'P08.3', 'candidate_source_sha': self.ci_sha,
                                       'hosted_ci_run_id': 42, 'reason': 'closed P08 fixture'}
                                      for r in guard.differences(self.snapshot, self.current)],
                       'hosted_receipt': {'head_sha': self.ci_sha, 'run_id': 42,
                                          'run_path': 'artifacts/run.json', 'jobs_path': 'artifacts/jobs.json'},
                       'lineage': {'tested_sha': self.ci_sha, 'pre_source_sha': self.current['source_commit'], 'plan_only': True}}
        self.write_set = {'paths': [{'path': p, 'before_sha256': guard.inventory(self.root)[p], 'allowed_change': 'debt shrinks'}
                                    for p in ['phpstan-baseline.neon', 'packages/core/phpstan-baseline.neon', 'scripts/quality-ceilings.json']]}
        self.save('snapshot.json', self.snapshot)
        self.save('rebase.json', self.rebase)
        self.save('write-set.json', self.write_set)

    def put(self, path, value):
        file = self.root / path
        file.parent.mkdir(parents=True, exist_ok=True)
        file.write_text(value)

    def git(self, *args):
        return subprocess.check_output(['git', *args], cwd=self.root, stderr=subprocess.PIPE, text=True)

    def commit(self):
        self.git('add', '.')
        self.git('commit', '-qm', 'fixture')

    def save(self, name, value):
        (self.build / name).write_text(json.dumps(value))

    def cli(self, command, expected=0, diagnostic='', receipt='pre.json'):
        flags = {'pre': ['--snapshot', 'snapshot.json', '--p08-rebase', 'rebase.json', '--write-set', 'write-set.json', '--output', 'pre.json'],
                 'verify-pre': ['--snapshot', 'snapshot.json', '--receipt', receipt, '--p08-rebase', 'rebase.json'],
                 'post': ['--before', receipt, '--write-set', 'write-set.json', '--output', 'post.json'],
                 'extend-scope': ['--before', 'pre.json', '--write-set', 'write-set.json', '--decision', 'decision.json', '--output', 'extended.json']}[command]
        argv = [v if v.startswith('--') else str(self.build / v) for v in flags]
        result = subprocess.run([sys.executable, str(SCRIPT), '--root', str(self.root), command, *argv], text=True, capture_output=True)
        self.assertEqual(result.returncode, expected, result.stderr)
        self.assertIn(diagnostic, result.stdout + result.stderr)

    def shrink(self):
        for p in ['phpstan-baseline.neon', 'packages/core/phpstan-baseline.neon']:
            self.put(p, 'parameters:\n    ignoreErrors: []\n')
        self.put('scripts/quality-ceilings.json', json.dumps({'schema': 'test', 'phpstan': {'root': 0, 'core': 0, 'access-azguard': 0}, 'dependencies': {}}))

    def test_owned_shrink_verifies_historical_pre_and_post(self):
        self.cli('pre')
        self.shrink()
        self.cli('verify-pre')
        self.cli('post')

    def test_pre_rejects_unknown_anchor(self):
        self.put('scripts/schema-gate.php', 'unreviewed\n')
        self.cli('pre', 1, 'anchors.scripts/schema-gate.php')

    def test_pre_rejects_wildcard_rebase(self):
        self.rebase['exact_deltas'][0]['key'] = 'anchors.*'
        self.save('rebase.json', self.rebase)
        self.cli('pre', 1, 'anchors.*')

    def test_pre_rejects_wrong_ci_run(self):
        self.rebase['hosted_receipt']['run_id'] = 43
        self.save('rebase.json', self.rebase)
        self.cli('pre', 1, 'CI run/SHA/jobs mismatch')

    def test_pre_rejects_counterfeit_anchor_hash(self):
        self.rebase['exact_deltas'][0]['current'] = 'counterfeit'
        self.save('rebase.json', self.rebase)
        self.cli('pre', 1, 'rebase value mismatch')

    def test_pre_rejects_manifest_drift(self):
        self.put('packages/core/composer.json', json.dumps({'name': 'changed', 'require': {'php': '^8.4'}}))
        self.cli('pre', 1, 'packages')

    def test_pre_rejects_outside_write_set(self):
        self.write_set['paths'].append({'path': '.github/workflows/ci.yml', 'before_sha256': guard.inventory(self.root)['.github/workflows/ci.yml'], 'allowed_change': 'outside'})
        self.save('write-set.json', self.write_set)
        self.cli('pre', 1, '.github/workflows/ci.yml')

    def test_pre_cannot_overwrite_receipt(self):
        self.cli('pre')
        self.cli('pre', 1, 'receipt already exists')

    def test_verify_pre_rejects_mutated_receipt(self):
        self.cli('pre')
        p = self.build / 'pre.json'
        p.write_text(p.read_text() + ' ')
        self.cli('verify-pre', 1, 'mutated receipt')

    def test_post_rejects_retroactive_allowlist(self):
        self.cli('pre')
        self.write_set['paths'].append({'path': '.github/workflows/ci.yml', 'allowed_change': 'retroactive'})
        self.save('write-set.json', self.write_set)
        self.cli('post', 1, 'frozen write-set')

    def test_post_rejects_outside_anchor(self):
        self.cli('pre')
        self.shrink()
        self.put('scripts/schema-gate.php', 'drift')
        self.cli('post', 1, 'scripts/schema-gate.php')

    def test_post_rejects_deleted_analysis_path(self):
        self.cli('pre')
        self.shrink()
        (self.root / 'packages/core/phpstan.neon').unlink()
        self.cli('post', 1, 'packages/core/phpstan.neon')

    def test_post_rejects_requires_change(self):
        self.cli('pre')
        self.shrink()
        self.put('packages/core/composer.json', json.dumps({'name': 'axiomasoft/corex', 'require': {'php': '^8.5'}}))
        self.cli('post', 1, 'packages/core/composer.json')

    def test_post_rejects_ceiling_increase(self):
        self.cli('pre')
        self.shrink()
        self.put('scripts/quality-ceilings.json', json.dumps({'schema': 'test', 'phpstan': {'root': 2, 'core': 0, 'access-azguard': 0}, 'dependencies': {}}))
        self.cli('post', 1, 'ceilings.phpstan.root increased')

    def test_post_rejects_new_outside_file(self):
        self.cli('pre')
        self.shrink()
        self.put('packages/core/new.php', '<?php')
        self.cli('post', 1, 'packages/core/new.php')

    def prepare_extension(self):
        self.cli('pre')
        item_path = self.plan / 'phases/P09/P09.1.json'
        item = guard.read_json(item_path)
        item['task_files'].append({'path': 'docs/reference.md'})
        item_path.write_text(json.dumps(item))
        self.write_set['paths'].append({'path': 'docs/reference.md',
                                       'before_sha256': guard.inventory(self.root)['docs/reference.md'],
                                       'allowed_change': 'accepted exact documentation precision'})
        self.save('write-set.json', self.write_set)
        self.save('decision.json', {'status': 'accepted', 'applies_to': ['P09.1']})

    def test_declared_extension_freezes_unchanged_path_before_mutation(self):
        self.prepare_extension()
        self.cli('extend-scope')
        self.shrink()
        self.put('docs/reference.md', 'declared documentation precision\n')
        self.cli('verify-pre', receipt='extended.json')
        self.cli('post', receipt='extended.json')

    def test_extension_rejects_already_modified_path(self):
        self.prepare_extension()
        self.put('docs/reference.md', 'already changed\n')
        self.cli('extend-scope', 1, 'retroactive scope extension: docs/reference.md')

    def test_extension_rejects_altered_original_allowance(self):
        self.prepare_extension()
        self.write_set['paths'][0]['before_sha256'] = 'counterfeit'
        self.save('write-set.json', self.write_set)
        self.cli('extend-scope', 1, 'changed existing frozen paths')

    def test_extension_requires_accepted_owning_decision(self):
        self.prepare_extension()
        self.save('decision.json', {'status': 'open', 'applies_to': ['P09.1']})
        self.cli('extend-scope', 1, 'accepted owning decision')


if __name__ == '__main__':
    unittest.main()
