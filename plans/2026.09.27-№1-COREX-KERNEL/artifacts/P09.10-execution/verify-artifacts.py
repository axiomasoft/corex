"""Verify P09.10 acceptance, source scope, exact internal delta and immutable inputs."""
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


required = ['V2', 'V8', 'V13', 'V17', 'V18', 'V19', 'V20', 'V21', 'V22', 'V30']
checks = {}
for key in required + ['foundation', 'tests-lint']:
    receipt = read(OUT / (key + '-receipt.json'))
    assert receipt['id'] == key and receipt['exit_code'] == 0, key
    log = OUT / receipt['log']
    assert log.is_file() and log.stat().st_size > 0, key
    receipt['log_sha256'] = sha(log)
    checks[key] = receipt

assert (ROOT / 'packages/tenancy/phpstan-baseline.neon').read_text() == 'parameters:\n    ignoreErrors: []\n'
assert read(ROOT / 'scripts/quality-ceilings.json')['phpstan']['tenancy'] == 0
assert read(OUT / 'phpstan-before-full.json')['errors'] == 232
assert read(OUT / 'V2.log')['errors'] == 0
before = read(OUT / 'before-hashes.json')
immutable = [p for p in before['paths'] if (
    p.startswith('packages/tenancy/') and not p.startswith(('packages/tenancy/src/', 'packages/tenancy/tests/'))
    and p != 'packages/tenancy/phpstan-baseline.neon'
) or p.startswith('scripts/mutations/') or p in (
    'scripts/mutation-check.sh', 'scripts/bc/accepted-base.json', 'scripts/bc/approved-deltas.json',
    'docs/reference/public-api.md'
) or (p.startswith('tests/Golden/') and p not in (
    'tests/Golden/baseline/php-surface.json', 'tests/Golden/REBASELINE.md'
))]
for path in immutable:
    assert sha(ROOT / path) == before['paths'][path], path
api = 'packages/tenancy/resources/contracts/php-api.json'
assert sha(ROOT / api) == before['paths'][api]
ceiling_before = json.loads(subprocess.check_output(
    ['git', 'show', before['source_sha'] + ':scripts/quality-ceilings.json'], cwd=ROOT, text=True))
assert differences(ceiling_before, read(ROOT / 'scripts/quality-ceilings.json')) == ['/phpstan/tenancy']

golden_path = 'tests/Golden/baseline/php-surface.json'
golden_before = json.loads(subprocess.check_output(
    ['git', 'show', before['source_sha'] + ':' + golden_path], cwd=ROOT, text=True))
golden_diff = differences(golden_before, read(ROOT / golden_path))
expected = sorted(['/CoreX\\Tenancy\\' + module + '\\Internal\\DatabaseValue'
                   for module in ['Account', 'Identity', 'Integration', 'Membership', 'Provisioning']]
                  + ['/CoreX\\Tenancy\\Membership\\Internal\\Transport\\StrictJson/methods/namedObject'])
assert golden_diff == expected, golden_diff
assert read(OUT / 'golden-delta.json')['paths'] == golden_diff
assert 'module-layout OK:' in (OUT / 'module-layout.log').read_text()
assert '0 violations, 0 baselined, 0 new, 0 stale' in (OUT / 'module-layout.log').read_text()
assert read(OUT / 'package-graph-report.json')['violations'] == []

tracked = subprocess.check_output(['git', 'diff', '--name-only', '-z', before['source_sha']], cwd=ROOT).decode().split('\0')
new = subprocess.check_output(['git', 'ls-files', '--others', '--exclude-standard', '-z'], cwd=ROOT).decode().split('\0')
product_paths = sorted(p for p in set(tracked + new) if p and not p.startswith('plans/'))
assert all(p.startswith('packages/tenancy/') or p in (
    'scripts/quality-ceilings.json', golden_path, 'tests/Golden/REBASELINE.md'
) for p in product_paths), product_paths
product_hashes = {p: sha(ROOT / p) for p in product_paths}
counts = {}
for key in ['V8', 'V13']:
    text = re.sub(r'\x1b\[[0-9;]*m', '', (OUT / (key + '.log')).read_text())
    match = re.search(r'Tests:\s+(\d+) passed\s+\((\d+) assertions\)', text)
    assert match and 'skipped' not in text and 'FAILED' not in text, key
    counts[key] = {'tests': int(match[1]), 'assertions': int(match[2]), 'skipped': 0}
mutation_log = (OUT / 'V22.log').read_text()
mutation_count = len(re.findall(r'^mutation-check\[.*\]: OK', mutation_log, re.MULTILINE))
assert mutation_count == 35 and 'все 35 кейса(ов)' in mutation_log
assert '3-way fallback applied' not in mutation_log
if (OUT / 'summary.json').exists():
    sealed = read(OUT / 'summary.json')
    assert sealed['product_sha256'] == product_hashes, 'Checked product bytes changed'
    for key in checks:
        assert sealed['checks'][key]['log_sha256'] == checks[key]['log_sha256'], key

summary = {
    'item': 'PLAN3.P09.10', 'status': 'green', 'source_sha': before['source_sha'],
    'phpstan_before': 232, 'phpstan_after': 0, 'baseline_entries': 0, 'tenancy_ceiling': 0,
    'checks': checks, 'tests': counts, 'fixed_mutations_killed': mutation_count,
    'golden_delta_paths': golden_diff, 'immutable_paths_verified': immutable,
    'product_paths': product_paths, 'product_sha256': product_hashes,
    'schema_unchanged': True, 'bc_base_and_approvals_unchanged': True,
    'public_api_and_spi_signatures_unchanged': True,
    'environment': 'PHP8.4.26 via scripts/php84; PostgreSQL16 host127.0.0.1:5434/corex_test, serial; isolated Redis/S3 endpoints in optional-services.json.',
    'self_check': 'Feature-local SQL value guards preserve module privacy; decimal counters retain unsigned64 precision; scalar callbacks, framework types and JSON object keys reflect real runtime data. Optional integration scenarios executed without skips.',
    'review_and_hosted_ci': 'Independent code-quality review and phase final hosted CI remain P09.8; no external/release action.',
}
(OUT / 'summary.json').write_text(json.dumps(summary, indent=2) + '\n')
print(json.dumps({k: summary[k] for k in (
    'item', 'status', 'phpstan_after', 'tenancy_ceiling', 'tests', 'fixed_mutations_killed'
)}))
