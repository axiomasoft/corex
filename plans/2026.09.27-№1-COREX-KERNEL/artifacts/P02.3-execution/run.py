#!/usr/bin/env python3
"""Replay the same admitted P02.3 pipeline in the dry-run and main checkout."""
import argparse, json, os, subprocess, time
from pathlib import Path

ap = argparse.ArgumentParser()
ap.add_argument('--out', required=True)
ap.add_argument('--source-tree', required=True)
ap.add_argument('--preimage-install', action='store_true')
args = ap.parse_args()
out = Path(args.out)
out.mkdir(parents=True, exist_ok=True)
scripts = Path('plans/2026.09.27-№1-COREX-KERNEL/artifacts/P02-design/scripts')
rows = []
env = {**os.environ, 'PGSQL_PORT': '5432', 'XDEBUG_MODE': 'off'}

def run(name, command):
    start = time.monotonic()
    with (out / (name + '.log')).open('w') as log:
        result = subprocess.run(command, env=env, stdout=log, stderr=subprocess.STDOUT)
    row = {'check': name, 'exit': result.returncode, 'seconds': round(time.monotonic()-start, 2), 'command': command}
    rows.append(row)
    (out/'pipeline.json').write_text(json.dumps(rows, indent=2)+'\n')
    display = {**row, 'command': command[:4]+[str(len(command)-4)+' file arguments']} if name.startswith('php-l-') else row
    print(json.dumps(display), flush=True)
    if result.returncode:
        raise SystemExit(result.returncode)

def composer(name, command):
    paths = [Path('composer.json'), Path('packages/tenancy/composer.json'), Path('packages/access/composer.json')]
    saved = {p: p.read_bytes() for p in paths}
    try:
        run(name+'-stage', ['python3', 'scripts/distribution/stage-stancl-providers.py'])
        run(name, ['scripts/php84', 'composer', *command, '--no-scripts', '--no-interaction', '--no-progress'])
    finally:
        for path, content in saved.items():
            path.write_bytes(content)
    run(name+'-portable', ['python3', 'scripts/distribution/stage-stancl-providers.py', '--portable-locks'])

if args.preimage_install:
    composer('preimage-install', ['install'])
run('apply', ['python3', str(scripts/'p02-transfer.py'), 'apply', '--source-tree', args.source_tree])
run('implicit-references', ['scripts/php84', 'php', str(scripts/'p02-imports.php')])
for step in ['scaffold', 'graph', 'gates', 'residuals', 'quality']:
    run(step, ['python3', str(scripts/('p02-'+step+'.py'))])
composer('composer-update', ['update', 'axiomasoft/*', 'flex/crm'])
run('snapshots', ['python3', str(scripts/'p02-snapshots.py')])
run('pint', ['scripts/php84', 'vendor/bin/pint'])
run('strict-directives', ['scripts/php84', 'php', str(scripts/'p02-references.php'),
                          'build/p02/literal-rules.json', '--current', '--strict-only'])
strict_files = json.loads(Path('build/p02/strict-directive-files.json').read_text())
if strict_files:
    run('strict-pint', ['scripts/php84', 'vendor/bin/pint', *strict_files])
php_files = []
for base in ['packages', 'products', 'tests', 'scripts', 'fixtures']:
    for directory, subdirs, files in os.walk(base):
        subdirs[:] = [name for name in subdirs if name not in ['vendor', 'node_modules', '.phpunit.cache']]
        php_files.extend(str(Path(directory)/name) for name in files if name.endswith('.php'))
php_files.sort()
for start in range(0, len(php_files), 200):
    run('php-l-'+str(start//200+1), ['scripts/php84', 'php', '-l', *php_files[start:start+200]])
run('adapter-hashes', ['python3', str(scripts/'p02-gates.py'), '--hash-only'])
for directory in ['packages/core', 'packages/operations', 'packages/tools', 'packages/webhooks', 'packages/custom-fields',
                  'products/flex-crm', 'packages/intl', 'packages/panels', 'packages/recurrence', 'packages/tenancy']:
    run('baseline-'+Path(directory).name, ['scripts/php84', 'bash', 'scripts/phpstan-packages.sh', '--generate-baseline', directory])
run('baseline-root', ['scripts/php84', 'vendor/bin/phpstan', 'analyse', '-c', 'phpstan.neon', '--memory-limit=2G',
                      '--no-progress', '--generate-baseline=phpstan-baseline.neon', '--allow-empty-baseline'])
run('ratchets', ['python3', str(scripts/'p02-ratchets.py')])
run('tighten', ['scripts/php84', 'php', 'scripts/quality-ceiling-guard.php', '--tighten'])
run('ownership', ['scripts/php84', 'php', 'scripts/ownership-gate.php', '--write-baseline'])
run('verify', ['python3', str(scripts/'p02-transfer.py'), 'verify'])
run('checks', ['python3', 'plans/2026.09.27-№1-COREX-KERNEL/artifacts/P02.3-execution/check.py', '--out', str(out/'validation'), '--fail-fast',
               'manifests', 'lint', 'golden', 'root-test', 'pg-test', 'packages-test', 'analyse', 'deps-analyse',
               'rename', 'ownership', 'ceilings', 'anti-weakening', 'package-graph-gate', 'api-contract',
               'contract-guard', 'naming-gate', 'package-docs-gate', 'mutation'])
run('lock-hashes', ['scripts/php84', 'php', str(scripts/'p02-lock-hashes.php')])
manifests = [Path('composer.json'), *sorted(Path('packages').glob('*/composer.json')),
             *sorted(Path('products').glob('*/composer.json'))]
assert len(manifests) == 22
for manifest in manifests:
    name = 'root' if manifest.parent == Path('.') else manifest.parent.name
    run('strict-'+name, ['scripts/php84', 'composer', '--working-dir='+str(manifest.parent), 'validate', '--strict'])
checks = json.loads((out/'validation/checks.json').read_text())
if any(row['exit'] for row in checks):
    raise SystemExit('validation carriers failed: '+', '.join(row['check'] for row in checks if row['exit']))
print('pipeline GREEN', flush=True)
