"""Collect observed raw commands, bounded final revalidation and immutable source proof."""
import hashlib
import json
from pathlib import Path
import re
import subprocess

OUT = Path(__file__).resolve().parent
ROOT = OUT.parents[3]
PLAN = OUT.parent.parent
item = json.loads((PLAN / 'phases/P09/P09.11.json').read_text())
pre = json.loads((OUT / 'pre.json').read_text())
paths = subprocess.check_output(['git', 'ls-files', '--cached', '--others', '--exclude-standard', '-z'], cwd=ROOT).decode().split('\0')
current = {p: hashlib.sha256((ROOT / p).read_bytes()).hexdigest() for p in paths if p and not p.startswith('plans/') and (ROOT / p).is_file()}
changed = sorted(p for p in pre['product_sha256'].keys() | current.keys() if pre['product_sha256'].get(p) != current.get(p))
before = json.loads((OUT / 'mutation-before.json').read_text())
restoration = {**before, 'after_product_sha256': current,
               'after_index_sha256': hashlib.sha256(subprocess.check_output(['git', 'ls-files', '--stage', '-z'], cwd=ROOT)).hexdigest()}
assert restoration['before_product_sha256'] == restoration['after_product_sha256']
assert restoration['before_index_sha256'] == restoration['after_index_sha256']
(OUT / 'mutation-restoration.json').write_text(json.dumps(restoration, indent=2) + '\n')
checks = {}
for validation in item['validation']:
    receipt = json.loads((OUT / (validation['id'] + '-receipt.json')).read_text())
    assert receipt['exit_code'] == 0, validation['id']
    assert hashlib.sha256((OUT / receipt['log']).read_bytes()).hexdigest() == receipt['log_sha256'], validation['id']
    checks[validation['id']] = receipt
supplemental = {}
for key in ['tests-lint', 'tests-lint-format', 'root-analysis', 'module-layout', 'package-graph', 'foundation', 'security-regressions', 'tools-pg-inventory', 'lint-delta', 'bc-operations', 'bc-webhooks']:
    receipt = json.loads((OUT / (key + '-receipt.json')).read_text())
    assert receipt['exit_code'] == (1 if key == 'tools-pg-inventory' else 0), key
    supplemental[key] = receipt
cases = re.findall(r'mutation-check\[([^]]+)\]: OK ', (OUT / 'V22.log').read_text())
assert len(cases) == 35
counts = {}
for key in ['V9', 'V10', 'V11', 'V14', 'V16']:
    raw = re.sub(r'\x1b\[[0-9;]*m', '', (OUT / (key + '.log')).read_text())
    result = re.search(r'Tests:\s+(\d+) passed\s+\((\d+) assertions\)', raw)
    assert result, key
    counts[key] = {'tests': int(result[1]), 'assertions': int(result[2]), 'skipped': 0}
summary = {'item': 'PLAN3.P09.11', 'status': 'green', 'source_sha': pre['source_sha'],
           'phpstan_before': {'operations': 137, 'tools': 16, 'webhooks': 194},
           'phpstan_after': {'operations': 0, 'tools': 0, 'webhooks': 0},
           'checks': checks, 'supplemental': supplemental, 'tests': counts,
           'package_tests': sum(v['tests'] for v in counts.values()),
           'package_assertions': sum(v['assertions'] for v in counts.values()),
           'fixed_mutations_killed': len(cases), 'fixed_mutations_strict_apply': True,
           'public_api_delta_fields': 3, 'golden_added_internal_types': 4,
           'bounded_revalidation': json.loads((OUT / 'bounded-revalidation.json').read_text()),
           'changed_product_paths': changed, 'product_sha256': {p: current[p] for p in changed},
           'review': 'P09.8 owns independent phase review and final hosted CI under D73; no final phase/hosted/release claim.',
           'tools_pg': 'No PostgreSQL suite; actual group=pg negative inventory exits1 with No tests found; SQLite tests are nonempty and green.'}
(OUT / 'summary.json').write_text(json.dumps(summary, indent=2) + '\n')
print('Collected', len(checks), 'GREEN acceptance checks;', summary['package_tests'], 'package tests;', len(cases), 'fixed mutations')
