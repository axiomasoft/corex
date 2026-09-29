#!/usr/bin/env python3
"""Repeat design diagnostics only; creates/removes an owned HEAD worktree, never executes P03."""
import copy
import hashlib
import importlib.util
import json
import subprocess
import tempfile
from pathlib import Path

OUT = Path(__file__).resolve().parent
ROOT = OUT.parents[3]
PLAN = OUT.parents[1]
SCRIPTS = PLAN / 'artifacts/P03-design/scripts'
results = {}


def run(name, args, cwd=ROOT, expected=0):
    p = subprocess.run(args, cwd=cwd, text=True, capture_output=True)
    (OUT / (name + '.log')).write_text(p.stdout + p.stderr)
    results[name] = {'command': list(map(str, args)), 'exit_code': p.returncode, 'expected': expected}
    assert p.returncode == expected, (name, p.returncode, (p.stdout + p.stderr)[-1800:])
    return p.stdout


run('gate-tests', ['python3', '-m', 'unittest', 'discover', '-s', str(SCRIPTS), '-p', 'test_module_layout_gate.py'])
run('map-check', ['python3', str(SCRIPTS / 'p03-module-map.py'), '--check'])
base = ['python3', str(SCRIPTS / 'module-layout-gate.py'), '--config', str(SCRIPTS / 'module-layout.json')]
for name, extra in [('current', []), ('predicted', ['--project', str(PLAN / 'artifacts/P03/module-map.tsv')])]:
    generated = OUT / ('baseline.' + name + '.json')
    run('baseline-' + name, base + extra + ['--write-baseline', '--baseline', str(generated)])
    assert generated.read_bytes() == (PLAN / f'artifacts/P03/module-layout-baseline.{name}.json').read_bytes()
    generated.unlink()

spec = importlib.util.spec_from_file_location('transfer', SCRIPTS / 'p03-transfer.py')
transfer = importlib.util.module_from_spec(spec)
spec.loader.exec_module(transfer)
with tempfile.TemporaryDirectory(prefix='p03-design-', dir=ROOT / 'build') as folder:
    tree = Path(folder)
    run('worktree-add', ['git', 'worktree', 'add', '--detach', str(tree), 'HEAD'])
    try:
        before_manifests = {str(p.relative_to(tree)): p.read_bytes() for p in tree.glob('packages/*/composer.json')}
        for command in ('apply', 'manifest', 'golden', 'lock', 'verify'):
            run(command, ['python3', str(SCRIPTS / 'p03-transfer.py'), command] + (['--no-rector'] if command == 'apply' else []), tree)
        run('rename-completeness', ['scripts/php84', 'php', 'scripts/rename-completeness-gate.php'], tree)
        run('layout-after-transfer', base + ['--root', str(tree), '--baseline', str(PLAN / 'artifacts/P03/module-layout-baseline.predicted.json')])
        run('frozen-golden', ['git', 'diff', '--exit-code', 'HEAD', '--', 'tests/Golden/baseline'], tree)
        golden_before = json.loads(subprocess.check_output(['git', 'show', 'HEAD:tests/Golden/rename-map.json'], cwd=tree))
        golden_after = json.loads((tree / 'tests/Golden/rename-map.json').read_text())
        assert all(golden_before[k] == golden_after[k] for k in golden_before if k != 'manual')
        assert len(golden_after['manual']) - len(golden_before['manual']) == 376
        old = json.loads((tree / 'build/p03/composer-lock.before.json').read_text())
        new = json.loads((tree / 'composer.lock').read_text())
        transfer.lock_invariants(old, new)
        negative_fields = ('version', 'require', 'source', 'autoload')
        for field in negative_fields:
            bad = copy.deepcopy(new)
            bad['packages'][0][field] = 'PLANTED_DRIFT'
            try:
                transfer.lock_invariants(old, bad)
            except ValueError:
                continue
            raise AssertionError('accepted drift: ' + field)
        results['lock-negative-controls'] = list(negative_fields)
        lock = tree / 'composer.lock'
        good = lock.read_bytes()
        try:
            lock.write_bytes((tree / 'build/p03/composer-lock.before.json').read_bytes())
            run('old-lock-negative', ['scripts/php84', 'php', 'scripts/rename-completeness-gate.php'], tree, 1)
        finally:
            lock.write_bytes(good)
        changed = sorted(name for name, content in before_manifests.items() if (tree / name).read_bytes() != content)
        assert changed == [f'packages/{name}/composer.json' for name in ('access','core','operations','tasks-workflow','tasks','tenancy')], changed
        assert (tree / 'composer.json').read_bytes() == (tree / 'build/p03/composer-root.before.json').read_bytes()
        results['changed_manifests'] = changed
        results['locked_packages'] = len(new['packages']) + len(new['packages-dev'])
        (OUT / 'manifest-lock.patch').write_text(subprocess.check_output(['git', 'diff', '--', 'composer.lock', *changed], cwd=tree, text=True))
        run('diff-stat', ['git', 'diff', '--stat', 'HEAD'], tree)
    finally:
        run('worktree-remove', ['git', 'worktree', 'remove', '--force', str(tree)])
results['source_head'] = subprocess.check_output(['git', 'rev-parse', 'HEAD'], cwd=ROOT, text=True).strip()
results['script_sha256'] = {str(p.relative_to(ROOT)): hashlib.sha256(p.read_bytes()).hexdigest() for p in [Path(__file__), *sorted(SCRIPTS.glob('*.py'))]}
(OUT / 'results.json').write_text(json.dumps(results, indent=2, ensure_ascii=False) + '\n')
print('P03 design remediation checks: PASS (no Rector/product suites; not execution acceptance)')
