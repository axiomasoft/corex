from pathlib import Path
import hashlib, json, re
from task import task_contract

p = Path('plans/2026.09.19-№1-COREX-PLATFORM-HARDENING')
out = p / 'artifacts/P14.21-execution'
commands = json.loads((out / 'commands.json').read_text())
for r in commands:
    assert r['exit_code'] == 0
    assert hashlib.sha256((out / r['log']).read_bytes()).hexdigest() == r['log_sha256'], r['id']
    if 'junit' in r:
        assert r['junit']['failures'] == r['junit']['errors'] == r['junit']['skipped'] == 0
        assert r['junit']['tests'] > 0
        assert hashlib.sha256((out / r['junit']['artifact']).read_bytes()).hexdigest() == r['junit']['sha256']
members = json.loads((out / 'inputs.json').read_text())['members']
for r in members:
    assert hashlib.sha256(Path(r['path']).read_bytes()).hexdigest() == r['sha256'], r['path']
matrix = json.loads((out / 'acceptance-matrix.json').read_text())
assert len(matrix) == 16 and all(r['tests'] for r in matrix)
receipt = (p / 'findings/P14-execution-validation.md').read_text()
for marker in ['at-least-once', 'cancel cooperative', 'unsealed attempt abort/restart', 'cost.enabled=false', 'global budget enforced', '9 historical breaks', '0 skipped']:
    assert marker in receipt, marker
for n in ['docs/architecture/api-policy.md', 'docs/architecture/message-policy.md', 'docs/reference.md', 'packages/api/README.md', 'CHANGELOG.md']:
    assert Path(n).is_file()
s = json.loads(Path('build/P14.21/start.json').read_text())
c = task_contract.build(p, 'P14.21', actual_route=s['decision']['route_receipt']['evidence'])
context = task_contract.current_validation_context(p, 'P14.21')
# E10 is a declared aggregate Validation carrier. Its complete underlying argv,
# outputs and document predicates were checked above; it is not a literal shell string.
receipts = [task_contract.build_validation_receipt(c, e['id'], exit_code=0, context=context)
            for e in c['evidence'] if e['algorithm'] == 'shell-command']
verdict = task_contract.evaluate(c, validation_receipts=receipts, validation_context=context)
assert verdict['pre_mutation'] == verdict['pre_final'] == 'allow', verdict
result = {'contract': c, 'context': context, 'validation_receipts': receipts, 'verdict': verdict,
          'aggregate_execution': {'argv': ['python3', 'build/P14.21/qualify.py'], 'underlying_commands': 'commands.json',
                                  'predicates': 'acceptance-matrix.json + P14-execution-validation.md', 'exit_code': 0}}
(out / 'task-contract.json').write_text(json.dumps(result, ensure_ascii=False, indent=2) + '\n')
print(json.dumps(verdict, ensure_ascii=False))
