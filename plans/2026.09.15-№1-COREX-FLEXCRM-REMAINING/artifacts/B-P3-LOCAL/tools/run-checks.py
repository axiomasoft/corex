#!/usr/bin/env python3
"""Archive actual command outcomes; PostgreSQL suites and mutation tests stay serial."""
from pathlib import Path
from datetime import datetime, timezone
import concurrent.futures
import hashlib
import json
import os
import subprocess
import sys

root = Path(__file__).resolve().parents[5]
plan = root / 'plans/2026.09.15-№1-COREX-FLEXCRM-REMAINING'
artifacts = plan / 'artifacts/B-P3-LOCAL'
item = sys.argv[1]
out = artifacts / item / 'checks'
out.mkdir(parents=True, exist_ok=True)
env = dict(os.environ, APP_ENV='testing', PGSQL_HOST='127.0.0.1', PGSQL_PORT='5434', PGSQL_DATABASE='corex_test')
sdk = Path('/tmp/corex-p3-sdk-dir').read_text().strip()
env['COREX_P3_SDK_AUTOLOAD'] = sdk + '/vendor/autoload.php'
env['COREX_P3_STORAGE_CONFIG'] = Path('/tmp/corex-p3-minio-config').read_text().strip()


def run(name, command):
    start = datetime.now(timezone.utc).isoformat()
    log = out / (name + '.log')
    with log.open('w') as stream:
        result = subprocess.run(command, cwd=root, env=env, stdout=stream, stderr=subprocess.STDOUT)
    receipt = {
        'command': command, 'cwd': str(root), 'runtime': {'php': subprocess.check_output(['php', '-r', 'echo PHP_VERSION;'], text=True)},
        'started_at': start, 'ended_at': datetime.now(timezone.utc).isoformat(), 'exit_code': result.returncode,
        'environment': {'APP_ENV': 'testing', 'PGSQL_DATABASE': 'corex_test', 'PGSQL_PORT': '5434', 'files': 'unique Testbench paths and generated storage roots'},
        'log': str(log.relative_to(plan)), 'sha256': hashlib.sha256(log.read_bytes()).hexdigest(),
    }
    (out / (name + '.json')).write_text(json.dumps(receipt, indent=2) + '\n')
    print(name, result.returncode, flush=True)
    return receipt


independent = [
    ('root-lint', ['composer', 'lint:check']), ('root-analyse', ['composer', 'analyse']),
    ('root-test', ['composer', 'test']), ('root-arch', ['composer', 'test:arch']),
    ('contract-guard', ['composer', 'contract-guard']), ('anti-weakening', ['bash', 'scripts/anti-weakening-gate.sh']),
    ('tenancy-analyse', ['composer', '--working-dir=packages/tenancy', 'analyse']),
]
with concurrent.futures.ThreadPoolExecutor(max_workers=3) as pool:
    results = list(pool.map(lambda pair: run(*pair), independent))
for pair in [
    ('root-pg', ['composer', 'test:pg']),
    ('tenancy-test', ['composer', '--working-dir=packages/tenancy', 'test']),
    ('mutations', ['bash', 'scripts/mutation-check.sh', '--all']),
]:
    results.append(run(*pair))
(out / 'results.json').write_text(json.dumps(results, indent=2) + '\n')
sys.exit(0 if all(row['exit_code'] == 0 for row in results) else 1)
