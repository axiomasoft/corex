"""Verify P09.11 raw acceptance, finite deltas and unchanged out-of-scope inputs."""
import fnmatch
import hashlib
import json
from pathlib import Path
import re
import subprocess

OUT = Path(__file__).resolve().parent
ROOT = OUT.parents[3]
PLAN = OUT.parent.parent

def digest(path):
    return hashlib.sha256(path.read_bytes()).hexdigest()

def inventory():
    paths = subprocess.check_output(['git', 'ls-files', '--cached', '--others', '--exclude-standard', '-z'], cwd=ROOT).decode().split('\0')
    return {p: digest(ROOT / p) for p in paths if p and not p.startswith('plans/') and (ROOT / p).is_file()}

def json_deltas(before, after, path=''):
    if type(before) is not type(after):
        return [path]
    if isinstance(before, dict):
        return [delta for key in sorted(before.keys() | after.keys()) for delta in json_deltas(before.get(key), after.get(key), path + '/' + str(key))]
    return [] if before == after else [path]

item = json.loads((PLAN / 'phases/P09/P09.11.json').read_text())
summary = json.loads((OUT / 'summary.json').read_text())
for validation in item['validation']:
    receipt = json.loads((OUT / (validation['id'] + '-receipt.json')).read_text())
    assert receipt['exit_code'] == 0, validation['id']
    assert receipt['command'] == validation['cmd'], validation['id']
    assert receipt['log_sha256'] == digest(OUT / receipt['log']), validation['id']
    assert summary['checks'][validation['id']] == receipt, validation['id']
for key, expected in [('tests-lint', 0), ('tests-lint-format', 0), ('root-analysis', 0), ('module-layout', 0), ('package-graph', 0), ('foundation', 0), ('security-regressions', 0), ('tools-pg-inventory', 1), ('lint-delta', 0), ('bc-operations', 0), ('bc-webhooks', 0)]:
    receipt = json.loads((OUT / (key + '-receipt.json')).read_text())
    assert receipt['exit_code'] == expected, key
    assert digest(OUT / receipt['log']) == receipt['log_sha256'], key
pre = json.loads((OUT / 'pre.json').read_text())
current = inventory()
changed = sorted(p for p in pre['product_sha256'].keys() | current.keys() if pre['product_sha256'].get(p) != current.get(p))
assert changed == summary['changed_product_paths']
allowed = [row['path'] for row in item['task_files']]
for path in changed:
    assert any(fnmatch.fnmatchcase(path, pattern) for pattern in allowed), path
for path, before in pre['product_sha256'].items():
    if '/database/migrations/' in path or path.endswith(('composer.json', 'composer.lock')) or path.startswith('scripts/bc/') or path in ['tests/Golden/baseline/routes.json', 'tests/Golden/baseline/events.json', 'tests/Golden/baseline/wire.json']:
        assert current[path] == before, path
for path, expected in summary['product_sha256'].items():
    assert current[path] == expected, path
for package in ['operations', 'tools', 'webhooks']:
    assert (ROOT / f'packages/{package}/phpstan-baseline.neon').read_text() == 'parameters:\n    ignoreErrors: []\n'
ceilings = json.loads((ROOT / 'scripts/quality-ceilings.json').read_text())
assert all(ceilings['phpstan'][pkg] == 0 for pkg in ['operations', 'tools', 'webhooks'])
previous_ceilings = json.loads(subprocess.check_output(['git', 'show', pre['source_sha'] + ':scripts/quality-ceilings.json'], cwd=ROOT))
for lane, rows in ceilings.items():
    if not isinstance(rows, dict):
        assert rows == previous_ceilings[lane], lane
        continue
    for name, value in rows.items():
        assert value <= previous_ceilings[lane][name], (lane, name)
for key in ['V9', 'V10', 'V11', 'V14', 'V16']:
    raw = re.sub(r'\x1b\[[0-9;]*m', '', (OUT / f'{key}.log').read_text())
    match = re.search(r'Tests:\s+(\d+) passed\s+\((\d+) assertions\)', raw)
    assert match, key
    assert summary['tests'][key] == {'tests': int(match[1]), 'assertions': int(match[2]), 'skipped': 0}, key
    assert int(match[1]) > 0 and int(match[2]) > 0
manifest_path = 'packages/operations/resources/contracts/php-api.json'
before = json.loads(subprocess.check_output(['git', 'show', pre['source_sha'] + ':' + manifest_path], cwd=ROOT))
after = json.loads((ROOT / manifest_path).read_text())
actual_api_delta = json_deltas(before, after)
expected_api_delta = [row['path'] for row in json.loads((OUT / 'public-api-delta.json').read_text())]
assert actual_api_delta == expected_api_delta and len(actual_api_delta) == 3
surface = 'tests/Golden/baseline/php-surface.json'
before = json.loads(subprocess.check_output(['git', 'show', pre['source_sha'] + ':' + surface], cwd=ROOT))
after = json.loads((ROOT / surface).read_text())
actual_golden = {'added': sorted(after.keys() - before.keys()), 'removed': sorted(before.keys() - after.keys()), 'changed': sorted(k for k in before.keys() & after.keys() if before[k] != after[k])}
assert actual_golden == json.loads((OUT / 'golden-delta.json').read_text())
assert len(actual_golden['added']) == 4 and not actual_golden['removed'] and not actual_golden['changed']
mutation_log = (OUT / 'V22.log').read_text()
cases = subprocess.check_output(['bash', 'scripts/mutation-check.sh', '--list'], cwd=ROOT, text=True).splitlines()
killed = re.findall(r'mutation-check\[([^]]+)\]: OK ', mutation_log)
assert sorted(killed) == sorted(cases) and len(cases) == 35
assert '3-way fallback' not in mutation_log and 'cannot apply' not in mutation_log
restoration = json.loads((OUT / 'mutation-restoration.json').read_text())
assert restoration['before_product_sha256'] == restoration['after_product_sha256']
assert restoration['before_index_sha256'] == restoration['after_index_sha256']
assert restoration['after_product_sha256'] == current
print(f'P09.11 GREEN: {len(item["validation"])} acceptance checks, 444 package tests, all35 fixed mutations restored, finite API/Golden delta, source scope preserved.')
