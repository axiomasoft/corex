"""Isolated policy checks; fixtures are never product conformance receipts."""
import contextlib
import copy
import importlib.util
import io
import json
from pathlib import Path
import shutil
import sys
import tempfile
from unittest.mock import patch

PLAN = Path(__file__).resolve().parents[2]
RUNTIME = Path('/home/vostrikov/projects/packages/swissknifeman/packages/task')
sys.path.insert(0, str(RUNTIME / 'lib'))
from task import plan_lint, plan_weight

spec = importlib.util.spec_from_file_location('p3_policy', PLAN / 'scripts/task-check.py')
gate = importlib.util.module_from_spec(spec)
spec.loader.exec_module(gate)
measured = plan_weight.measure_plan(PLAN, plan_weight.Measurer())
results = []

def check(name, item, expected, *, suffix=None, path='plan.md', line=None,
          weight_delta=0, threshold=100000, ready=True):
    fixture = copy.deepcopy(measured)
    row = next(r for r in fixture['items'] if r['item_id'] == item)
    row.update(admission_state='ready' if ready else 'pending-producer',
               weight_tokens=571115, d2a_pass=False)
    message = suffix or (f'read-budget-v1: пункт {item}: вес {571115 + weight_delta} '
                         f'> порога {threshold} оценочных токенов (isolated policy fixture)')
    def lint(root, diagnostics):
        diagnostics.error(PLAN / path, line, message)
    with patch.object(plan_weight, 'measure_plan', return_value=fixture), \
         patch.object(plan_lint, 'lint_plan', side_effect=lint), \
         contextlib.redirect_stdout(io.StringIO()):
        actual = gate.policy_lint(PLAN, RUNTIME)
    assert actual == expected, (name, actual, expected)
    results.append({'case': name, 'expected_exit': expected, 'actual_exit': actual})

for item in ('P1.1', 'P2.1', 'P3.1', 'P3.2'):
    check('approved-' + item, item, 0)
for item in ('P3.3', 'P3.7', 'P4.1'):
    check('unapproved-' + item, item, 1)
check('D2b', 'P3.1', 1, suffix='read-budget-v1: D2(b) oversized file')
check('digest', 'P3.1', 1, suffix='bundle_digest_mismatch')
check('missing-input', 'P3.1', 1, suffix='missing required input')
check('weight', 'P3.1', 1, weight_delta=1)
check('threshold', 'P3.1', 1, threshold=100001)
check('path', 'P3.1', 1, path='roadmap.md')
check('line', 'P3.1', 1, line=1)
check('not-ready', 'P3.1', 1, ready=False)

with tempfile.TemporaryDirectory(prefix='corex-p3-policy-guard-') as folder:
    isolated = Path(folder)
    shutil.copytree(PLAN / 'decisions', isolated / 'decisions')
    (isolated / 'research').mkdir()
    policy = PLAN / 'research/09-read-budget-policy.json'
    target = isolated / 'research/09-read-budget-policy.json'
    target.write_bytes(policy.read_bytes())
    def refuses(name):
        try:
            gate.policy_lint(isolated, RUNTIME)
        except ValueError:
            results.append({'case': name, 'result': 'PASS'})
        else:
            raise AssertionError(name)
    d = json.loads(policy.read_text())
    d['additional_scopes'][1]['scope'].append('P3.3')
    target.write_text(json.dumps(d))
    refuses('scope-expansion')
    target.write_bytes(policy.read_bytes())
    for name in ('D19-read-budget-advisory.md', 'D20-P2-read-budget-advisory.md',
                 'D23-P3-local-read-budget-advisory.md'):
        f = isolated / 'decisions' / name
        raw = f.read_bytes()
        f.write_bytes(raw + b'changed')
        refuses('decision-drift-' + name)
        f.write_bytes(raw)

output = {'result': 'PASS', 'class': 'isolated diagnostic policy guards',
          'checks': results, 'native_items': [
              {key: row[key] for key in ('item_id', 'weight_tokens', 'd2a_pass',
                                        'd2b_pass', 'admission_state')}
              for row in measured['items'] if row['item_id'] in ('P3.1', 'P3.2')],
          'shared_runtime_changed': False}
(PLAN / 'artifacts/B-P3-LOCAL/D23-policy-checks.json').write_text(
    json.dumps(output, ensure_ascii=False, indent=2) + '\n')
print('Policy guard PASS:', len(results), 'checks')
