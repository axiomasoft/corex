#!/usr/bin/env python3
"""Repeat the P03 design dry replay ONLY in this audit's recorded, detached worktree.

Package vendors were copied independently from the local dependency set, with no
symlink outside the scratch tree (package-setup.json). This is not hosted CI or a
fresh dependency-resolution proof, and does not execute/close product items.
"""
import hashlib
import json
import os
from pathlib import Path
import subprocess
import sys
import time

OUT = Path(__file__).resolve().parent
ROOT = OUT.parents[1]
TREE = ROOT / 'build/p03-audit-dry'
PLAN = ROOT / 'plans/2026.09.27-№1-COREX-KERNEL'
SCRIPTS = PLAN / 'artifacts/P03-design/scripts'
record = json.loads((OUT / 'worktree.json').read_text())
assert record['root'] == str(TREE) and TREE.is_dir()
assert subprocess.check_output(['git', 'rev-parse', 'HEAD'], cwd=TREE, text=True).strip() == record['base']
assert subprocess.run(['git', 'symbolic-ref', '-q', 'HEAD'], cwd=TREE, capture_output=True).returncode != 0
env = {**os.environ, 'PGSQL_HOST': '127.0.0.1', 'PGSQL_PORT': '5432', 'PGSQL_DATABASE': 'corex_test', 'XDEBUG_MODE': 'off'}
rows = []
resume = '--resume-golden-check' in sys.argv
completed = {}
if resume:
    previous = json.loads((OUT / 'final-replay.json').read_text())
    assert previous[-1]['check'] == 'golden-map' and previous[-1]['exit'] == 1
    completed = {row['check']: row for row in previous[:-1]}
    (OUT / 'final-replay-before-golden-repair.json').write_text(json.dumps(previous, indent=2) + '\n')

def run(name, command, *, required=True):
    if name in completed:
        rows.append(completed[name])
        return
    start = time.monotonic()
    with (OUT / ('final-' + name + '.log')).open('w') as log:
        result = subprocess.run(list(map(str, command)), cwd=TREE, env=env, stdout=log, stderr=subprocess.STDOUT)
    row = {'check': name, 'exit': result.returncode, 'seconds': round(time.monotonic() - start, 2),
           'command': list(map(str, command)), 'log': 'final-' + name + '.log'}
    rows.append(row)
    (OUT / 'final-replay.json').write_text(json.dumps(rows, indent=2) + '\n')
    print(name, result.returncode, flush=True)
    if required and result.returncode:
        raise SystemExit(result.returncode)

if not resume:
    subprocess.run(['git', 'diff', 'HEAD'], cwd=TREE, check=True, stdout=(OUT / 'diagnostic-candidate.patch').open('w'))
run('reset-owned', ['git', 'reset', '--hard', 'HEAD'])
run('clean-owned', ['git', 'clean', '-fd'])
run('install-before', ['scripts/php84', 'composer', 'install', '--no-scripts', '--no-interaction'])
for action in ('plan', 'apply', 'manifest', 'golden', 'lock', 'verify'):
    run(action, ['python3', SCRIPTS / 'p03-transfer.py', action])
run('install-after', ['scripts/php84', 'composer', 'install', '--no-scripts', '--no-interaction'])
files = [f for f in subprocess.check_output(['git', 'diff', '--name-only', 'HEAD', '-z'], cwd=TREE, text=True).split('\0')
         if f.endswith('.php') and (TREE / f).is_file()]
run('pint', ['scripts/php84', 'vendor/bin/pint', *files])
run('adapters', ['python3', SCRIPTS / 'p03-transfer.py', 'adapters'])
for manifest in sorted([*TREE.glob('packages/*/composer.json'), *TREE.glob('products/*/composer.json')]):
    package = manifest.parent.relative_to(TREE)
    run('autoload-' + package.name, ['scripts/php84', 'composer', '--working-dir=' + str(package),
                                    'dump-autoload', '--no-scripts', '--no-interaction'])
run('layout', ['python3', SCRIPTS / 'module-layout-gate.py', '--config', SCRIPTS / 'module-layout.json',
               '--baseline', PLAN / 'artifacts/P03/module-layout-baseline.predicted.json'])
run('frozen-golden', ['git', 'diff', '--exit-code', '52446de', '--', 'tests/Golden/baseline'])
if resume:
    run('golden-canonical-repair', ['python3', SCRIPTS / 'p03-transfer.py', 'golden'])
run('golden-map', ['python3', SCRIPTS / 'p03-transfer.py', 'golden-check'])
run('full-stack', ['python3', PLAN / 'artifacts/P02.3-execution/check.py', '--out', OUT / 'final-stack',
                   'manifests', 'lint', 'golden', 'root-test', 'pg-test', 'packages-test', 'analyse', 'deps-analyse',
                   'ownership', 'ceilings', 'anti-weakening', 'package-graph-gate', 'api-contract',
                   'contract-guard', 'naming-gate', 'package-docs-gate', 'mutation'])
run('rename-all', ['scripts/php84', 'php', 'scripts/rename-completeness-gate.php'])
run('ownership-strict', ['scripts/php84', 'php', 'scripts/ownership-gate.php', '--strict'])
run('root-strict', ['scripts/php84', 'composer', 'validate', '--strict'])
run('arch', ['scripts/php84', 'composer', 'test:arch'])
for artifact in ('adapter-projection.json', 'fixture-projection.json'):
    (OUT / artifact).write_bytes((TREE / 'build/p03' / artifact).read_bytes())
(OUT / 'implicit-references.json').write_bytes((TREE / 'build/p02/implicit-references.json').read_bytes())
(OUT / 'script-sha256.json').write_text(json.dumps({str(p.relative_to(ROOT)): hashlib.sha256(p.read_bytes()).hexdigest()
    for p in sorted(SCRIPTS.iterdir()) if p.suffix in ('.py', '.php', '.json')}, indent=2) + '\n')
checks = json.loads((OUT / 'final-stack/checks.json').read_text())
assert all(row['exit'] == 0 for row in checks), checks
print('P03 clean full design replay GREEN', flush=True)
