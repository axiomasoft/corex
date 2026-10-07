"""Record bounded P09.11 commands and retain previous attempts, including interruption."""
import datetime
import hashlib
import json
import os
from pathlib import Path
import shlex
import shutil
import subprocess
import sys
import time

ROOT = Path(__file__).resolve().parents[4]
OUT = Path(__file__).resolve().parent
ITEM = json.loads((OUT.parent.parent / 'phases/P09/P09.11.json').read_text())
COMMANDS = {v['id']: v['cmd'] for v in ITEM['validation']}
COMMANDS.update({
    'tests-lint': 'scripts/php84 vendor/bin/phpstan analyse -c phpstan-tests-lint.neon packages/operations/tests packages/tools/tests packages/webhooks/tests tests/Arch/QualityReleaseGateTest.php --memory-limit=2G --no-progress',
    'root-analysis': 'scripts/php84 vendor/bin/phpstan analyse -c phpstan.neon --memory-limit=2G --no-progress',
    'module-layout': 'scripts/php84 python3 scripts/module-layout-gate.py',
    'package-graph': 'scripts/php84 composer package-graph-gate',
    'bc-operations': 'scripts/php84 composer bc-check',
    'bc-webhooks': 'scripts/php84 composer bc-check',
    'lint-delta': 'scripts/php84 vendor/bin/pint --test packages/operations/src/Bulk/Internal/SqlBulkOperationStore.php packages/webhooks/src/Internal/SqlWebhookDeliveryStore.php packages/webhooks/src/Internal/SqlWebhookSubscriptionStore.php',
    'foundation': 'scripts/php84 php scripts/quality/release-quality.php --stage=foundation',
    'tests-lint-format': 'scripts/php84 vendor/bin/pint --test packages/operations/tests packages/tools/tests packages/webhooks/tests tests/Arch/QualityReleaseGateTest.php',
    'tools-pg-inventory': 'scripts/php84 composer --working-dir=packages/tools test -- --group=pg',
    'security-regressions': 'scripts/php84 vendor/bin/pest tests/Arch/QualityReleaseGateTest.php --exclude-group=pg',
})

for key in sys.argv[1:]:
    command = COMMANDS[key]
    args = shlex.split(command)
    env = os.environ.copy()
    env['PAO_DISABLE'] = '1'
    if key.startswith('bc-'):
        env['BC_CHECK_ONLY'] = key.removeprefix('bc-')
    if args[0].startswith('PGSQL_PORT='):
        env['PGSQL_PORT'] = args.pop(0).split('=', 1)[1]
        env.update(PGSQL_HOST='127.0.0.1', PGSQL_DATABASE='corex_test', PGSQL_USERNAME='corex', PGSQL_PASSWORD='corex')
    # Mutation runner contains its own serial PG cases and calls default PHP on PATH.
    if key == 'V22':
        env['PATH'] = str(ROOT / 'build/php84/bin') + ':' + env['PATH']
        env.update(PGSQL_HOST='127.0.0.1', PGSQL_PORT='5434', PGSQL_DATABASE='corex_test', PGSQL_USERNAME='corex', PGSQL_PASSWORD='corex')
    log = OUT / f'{key}.log'
    receipt_path = OUT / f'{key}-receipt.json'
    if receipt_path.exists():
        attempt = OUT / 'attempts' / f'{key}-{time.time_ns()}'
        attempt.mkdir(parents=True)
        shutil.copy2(receipt_path, attempt / receipt_path.name)
        if log.exists():
            shutil.copy2(log, attempt / log.name)
            previous = json.loads(receipt_path.read_text())
            if hashlib.sha256(log.read_bytes()).hexdigest() != previous['log_sha256']:
                (attempt / 'interrupted.json').write_text(json.dumps({'reason': 'Interrupted rerun replaced raw log; preceding receipt hash no longer matches.', 'acceptance': False}, indent=2) + '\n')
    started = datetime.datetime.now(datetime.timezone.utc).isoformat()
    start = time.monotonic()
    with log.open('wb') as stream:
        result = subprocess.run(args, stdout=stream, stderr=subprocess.STDOUT, env=env, cwd=ROOT)
    receipt = {'id': key, 'command': command, 'exit_code': result.returncode,
               'started_at': started, 'finished_at': datetime.datetime.now(datetime.timezone.utc).isoformat(),
               'duration_seconds': round(time.monotonic() - start, 3), 'log': log.name,
               'log_sha256': hashlib.sha256(log.read_bytes()).hexdigest(),
               'environment': {'PAO_DISABLE': '1', 'PHP': '8.4.26'}}
    if key.startswith('bc-'):
        receipt['environment']['BC_CHECK_ONLY'] = env['BC_CHECK_ONLY']
    if key in ['V14', 'V16', 'V22']:
        receipt['environment'].update({k: env[k] for k in ['PGSQL_HOST', 'PGSQL_PORT', 'PGSQL_DATABASE', 'PGSQL_USERNAME']})
    receipt_path.write_text(json.dumps(receipt, indent=2) + '\n')
    print(key, 'exit', result.returncode, 'seconds', receipt['duration_seconds'], flush=True)
    if result.returncode:
        print(log.read_text()[-4000:], flush=True)
