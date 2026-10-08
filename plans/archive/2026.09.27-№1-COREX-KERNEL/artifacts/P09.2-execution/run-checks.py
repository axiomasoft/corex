"""Run the declared P09.2 checks and retain commands, exits and raw logs."""
from pathlib import Path
import json
import subprocess
import sys
import time

OUT = Path(__file__).resolve().parent
COMMANDS = {
    'V1': 'scripts/php84 vendor/bin/phpstan analyse -c packages/access/phpstan.neon --memory-limit=2G --no-progress',
    'V7': 'scripts/php84 composer --working-dir=packages/access test -- --exclude-group=pg',
    'V12': 'PGSQL_PORT=5434 scripts/php84 composer --working-dir=packages/access test:pg',
    'V17': 'scripts/php84 composer lint:check',
    'V18': 'scripts/php84 composer bc-check',
    'V19': 'scripts/php84 composer php-api',
    'V20': 'scripts/php84 composer schema-gate',
    'V21': 'scripts/php84 composer golden',
    'V22': 'bash scripts/mutation-check.sh --all',
    'tests-lint': 'scripts/php84 vendor/bin/phpstan analyse -c phpstan-tests-lint.neon packages/access/tests --memory-limit=2G --no-progress',
    'foundation': 'scripts/php84 php scripts/quality/release-quality.php --stage=foundation',
    'ceilings': 'scripts/php84 php scripts/quality-ceiling-guard.php',
}

for check in sys.argv[1:]:
    command = COMMANDS[check]
    start = time.monotonic()
    with (OUT / (check + '.log')).open('w') as log:
        result = subprocess.run(['bash', '-lc', command], stdout=log, stderr=subprocess.STDOUT)
    receipt = {'id': check, 'command': command, 'exit_code': result.returncode,
               'duration_seconds': round(time.monotonic() - start, 3), 'log': check + '.log'}
    (OUT / (check + '-receipt.json')).write_text(json.dumps(receipt, indent=2) + '\n')
    print(json.dumps(receipt), flush=True)
    if result.returncode:
        sys.exit(result.returncode)
