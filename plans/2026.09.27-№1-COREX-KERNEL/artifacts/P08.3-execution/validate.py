from pathlib import Path
from concurrent.futures import ThreadPoolExecutor
import json
import os
import subprocess

ROOT = Path(__file__).resolve().parents[4]
OUT = Path(__file__).resolve().parent
jobs = {
 'V1': ['scripts/php84', 'composer', 'schema-gate'],
 'V2': ['scripts/php84', 'vendor/bin/pest', 'tests/Unit/SchemaGateTest.php', '--compact'],
 'V3': ['scripts/php84', 'php', 'scripts/schema-gate.php', '--check'],
 'V5': ['scripts/php84', 'vendor/bin/pest', 'tests/Golden/SchemaSqliteGoldenTest.php', '--compact'],
 'V6': ['scripts/php84', 'composer', 'table-ownership-gate'],
 'V8': ['scripts/php84', 'vendor/bin/pint', '--test', 'scripts/schema-gate.php', 'scripts/table-ownership-gate.php', 'tests/Unit/SchemaGateTest.php', 'tests/Golden', 'tests/Arch/AchievedInvariantTest.php', 'packages/operations/database/migrations/tenant/create_sys_api_bulk_operations_table.php', 'packages/operations/database/migrations/budget/create_sys_api_budget_tables.php', 'packages/webhooks/database/migrations/tenant/create_sys_api_webhook_tables.php', 'packages/operations/tests/Feature/ApiRetentionTest.php', 'packages/webhooks/tests/Feature/WebhookApiRetentionTest.php'],
 'V9': ['scripts/php84', 'vendor/bin/phpstan', 'analyse', '-c', 'phpstan.neon', '--memory-limit=1G', '--no-progress'],
 'V14': ['scripts/php84', 'vendor/bin/pest', 'tests/Arch/AchievedInvariantTest.php', '--filter=table ownership', '--compact'],
 'V15': ['scripts/php84', 'composer', 'golden'],
 'V16': ['bash', 'scripts/anti-weakening-gate.sh'],
 'V17': ['scripts/php84', 'php', 'scripts/package-docs-gate.php'],
 'related': ['scripts/php84', 'vendor/bin/pest', 'packages/operations/tests/Feature/BulkApiTest.php', 'packages/operations/tests/Feature/ApiRetentionTest.php', 'packages/operations/tests/Feature/OperationBudgetTest.php', 'packages/webhooks/tests/Feature/WebhookSubscriptionTest.php', 'packages/webhooks/tests/Feature/WebhookApiRetentionTest.php', '--exclude-group=pg', '--compact'],
}

def run(pair):
 name, command = pair
 environment = dict(os.environ)
 if name in ('V1', 'V3', 'V5', 'V15'):
  environment.update(PGSQL_HOST='127.0.0.1', PGSQL_PORT='1')
 with (OUT/(name+'.log')).open('w') as log:
  result = subprocess.run(command, cwd=ROOT, env=environment, stdout=log, stderr=subprocess.STDOUT)
 record = dict(command=command, exit_code=result.returncode, log=str(OUT/(name+'.log')))
 print(name, result.returncode, flush=True)
 return name, record

if __name__ == '__main__':
 # Architecture negatives mutate shipped probe paths, so do not overlap a static gate scan.
 summary = {}
 summary.update([run(('V14', jobs.pop('V14')))])
 # Separate app processes are isolated on SQLite :memory:; no PG mutation occurs here.
 with ThreadPoolExecutor(max_workers=4) as executor:
  summary.update(executor.map(run, jobs.items()))
 # All PostgreSQL operations are serial on the explicitly preflighted corex_test database.
 summary.update([run(('V4', ['scripts/php84', 'vendor/bin/pest', 'tests/Golden/SchemaGoldenTest.php', 'tests/Golden/Support/SnapshotSchemaTest.php', '--group=pg', '--compact']))])
 (OUT/'validation-summary.json').write_text(json.dumps(summary, indent=2)+'\n')
 raise SystemExit(1 if any(x['exit_code'] for x in summary.values()) else 0)
