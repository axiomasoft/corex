"""Run the declared P09.10 checks and retain commands, exits and raw logs."""
from pathlib import Path
import json
import subprocess
import sys
import time
from datetime import datetime, timezone

OUT = Path(__file__).resolve().parent
COMMANDS = {'V2': 'scripts/php84 vendor/bin/phpstan analyse -c packages/tenancy/phpstan.neon --memory-limit=2G --no-progress', 'V8': 'scripts/php84 composer --working-dir=packages/tenancy test -- --exclude-group=pg', 'V13': 'PGSQL_PORT=5434 scripts/php84 composer --working-dir=packages/tenancy test -- --group=pg', 'V17': 'scripts/php84 composer lint:check', 'V18': 'scripts/php84 composer bc-check', 'V19': 'scripts/php84 composer php-api', 'V20': 'scripts/php84 composer schema-gate', 'V21': 'scripts/php84 composer golden', 'V22': 'bash scripts/mutation-check.sh --all', 'V30': 'scripts/php84 php scripts/quality-ceiling-guard.php --tighten', 'foundation': 'scripts/php84 php scripts/quality/release-quality.php --stage=foundation', 'tests-lint': 'scripts/php84 vendor/bin/phpstan analyse -c phpstan-tests-lint.neon packages/tenancy/tests --memory-limit=2G --no-progress'}

for check in sys.argv[1:]:
    command = COMMANDS[check]
    start = time.monotonic()
    started_at = datetime.now(timezone.utc).isoformat().replace('+00:00', 'Z')
    pending = {'id': check, 'command': command, 'exit_code': None, 'started_at': started_at, 'log': check + '.log'}
    (OUT / (check + '-receipt.json')).write_text(json.dumps(pending, indent=2) + '\n')
    with (OUT / (check + '.log')).open('w') as log:
        result = subprocess.run(['bash', '-c', command], stdout=log, stderr=subprocess.STDOUT)
    receipt = {'id': check, 'command': command, 'exit_code': result.returncode,
               'started_at': started_at, 'finished_at': datetime.now(timezone.utc).isoformat().replace('+00:00', 'Z'),
               'duration_seconds': round(time.monotonic() - start, 3), 'log': check + '.log'}
    (OUT / (check + '-receipt.json')).write_text(json.dumps(receipt, indent=2) + '\n')
    print(json.dumps(receipt), flush=True)
    if result.returncode:
        sys.exit(result.returncode)
