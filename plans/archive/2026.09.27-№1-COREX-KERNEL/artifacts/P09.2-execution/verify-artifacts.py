"""Verify P09.2 evidence and its finite source/API/schema changes."""
from pathlib import Path
import hashlib
import json
import re
import subprocess

ROOT = Path(__file__).resolve().parents[4]
OUT = Path(__file__).resolve().parent


def read(path):
    return json.loads(path.read_text())


def sha(path):
    return hashlib.sha256(path.read_bytes()).hexdigest()


def differences(before, after, path=''):
    if isinstance(before, dict) and isinstance(after, dict):
        result = []
        for key in sorted(before.keys() | after.keys()):
            if key not in before or key not in after:
                result.append(path + '/' + key)
            else:
                result.extend(differences(before[key], after[key], path + '/' + key))
        return result
    return [] if before == after else [path]


required = ['V1', 'V7', 'V12', 'V15', 'V17', 'V18', 'V19', 'V20', 'V21', 'V22']
checks = {}
for key in required:
    receipt = read(OUT / (key + '-receipt.json'))
    assert receipt['id'] == key and receipt['exit_code'] == 0, key
    if 'log' in receipt:
        log = OUT / receipt['log']
        assert log.is_file() and log.stat().st_size > 0, key
        receipt['log_sha256'] = sha(log)
    checks[key] = receipt

baseline = ROOT / 'packages/access/phpstan-baseline.neon'
assert baseline.read_text() == 'parameters:\n    ignoreErrors: []\n'
assert read(ROOT / 'scripts/quality-ceilings.json')['phpstan']['access'] == 0
before = read(OUT / 'before-hashes.json')
immutable = [p for p in before['paths'] if p.startswith('packages/access/database/') or p in (
    'packages/access/composer.json', 'packages/access/phpstan.neon', 'packages/access/phpunit.xml',
    'scripts/mutation-check.sh') or p.startswith('scripts/mutations/')]
for path in immutable:
    assert sha(ROOT / path) == before['paths'][path], path

public_before = read(OUT / 'public-delta-pre-hashes.json')
for path in ['docs/reference/public-api.md', 'scripts/bc/approved-deltas.json']:
    assert sha(ROOT / path) == public_before[path], path

api_path = 'packages/access/resources/contracts/php-api.json'
api_before = json.loads(subprocess.check_output(['git', 'show', before['source_sha'] + ':' + api_path], cwd=ROOT, text=True))
api_after = read(ROOT / api_path)
api_diff = differences(api_before, api_after)
assert len(api_diff) == 4, api_diff
assert all(any(symbol in p for symbol in ('PrincipalIdentity', 'PrincipalUserProvider')) for p in api_diff), api_diff

golden_path = 'tests/Golden/baseline/php-surface.json'
golden_before = json.loads(subprocess.check_output(['git', 'show', before['source_sha'] + ':' + golden_path], cwd=ROOT, text=True))
golden_after = read(ROOT / golden_path)
golden_diff = differences(golden_before, golden_after)
assert golden_diff == [
    '/CoreX\\Access\\Access\\Laravel\\PrincipalIdentity/methods/getAuthPassword/return',
    '/CoreX\\Access\\Identity\\Internal\\LowercaseColumn',
], golden_diff

tracked = subprocess.check_output(['git', 'diff', '--name-only', '-z', before['source_sha']], cwd=ROOT).decode().split('\0')
new = subprocess.check_output(['git', 'ls-files', '--others', '--exclude-standard', '-z'], cwd=ROOT).decode().split('\0')
product_paths = sorted(p for p in set(tracked + new) if p and not p.startswith('plans/'))
assert all(p.startswith('packages/access/') or p in ('scripts/quality-ceilings.json', golden_path) for p in product_paths), product_paths
product_hashes = {p: sha(ROOT / p) for p in product_paths}
if (OUT / 'summary.json').exists():
    sealed = read(OUT / 'summary.json')
    assert sealed['product_sha256'] == product_hashes, 'Checked product bytes changed'
    for key in required:
        assert sealed['checks'][key].get('log_sha256') == checks[key].get('log_sha256'), key

counts = {}
for key in ['V7', 'V12']:
    text = re.sub(r'\x1b\[[0-9;]*m', '', (OUT / (key + '.log')).read_text())
    match = re.search(r'Tests:\s+(\d+) passed\s+\((\d+) assertions\)', text)
    assert match, key
    counts[key] = {'tests': int(match[1]), 'assertions': int(match[2])}
mutation_log = (OUT / 'V22.log').read_text()
mutation_count = len(re.findall(r'^mutation-check\[.*\]: OK', mutation_log, re.MULTILINE))
assert mutation_count == 35 and 'все 35 кейса(ов)' in mutation_log, mutation_count

summary = {
    'item': 'PLAN3.P09.2', 'status': 'green', 'source_sha': before['source_sha'],
    'phpstan_before': 30, 'phpstan_after': 0, 'baseline_entries': 0, 'access_ceiling': 0,
    'checks': checks, 'tests': counts, 'fixed_mutations_killed': mutation_count,
    'api_delta_paths': api_diff, 'golden_delta_paths': golden_diff,
    'immutable_paths_verified': immutable, 'product_paths': product_paths,
    'product_sha256': product_hashes,
    'schema_unchanged': True, 'bc_base_and_approvals_unchanged': True,
    'deviation': 'D127: string/empty password contract and three truthful credentials PHPDoc parameters; quoted internal SQL expression.',
    'environment': 'PHP 8.4.26 via scripts/php84; PostgreSQL16 on 127.0.0.1:5434/corex_test; serial PG execution.',
    'review_and_hosted_ci': 'Remain owned by P09.8; no publication/release action.',
}
(OUT / 'summary.json').write_text(json.dumps(summary, indent=2) + '\n')
print(json.dumps({k: summary[k] for k in ('item', 'status', 'phpstan_after', 'access_ceiling', 'tests', 'fixed_mutations_killed')}))
