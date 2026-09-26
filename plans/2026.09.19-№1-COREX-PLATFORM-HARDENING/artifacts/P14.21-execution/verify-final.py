from pathlib import Path
import hashlib, json
from task import task_contract, plan_delivery, plan_gates, plan_closure, plan_item_state, plan_status, plan_decisions

p = Path('plans/2026.09.19-№1-COREX-PLATFORM-HARDENING')
out = p / 'artifacts/P14.21-execution'
for r in json.loads((out / 'inputs.json').read_text())['members']:
    assert hashlib.sha256(Path(r['path']).read_bytes()).hexdigest() == r['sha256'], r['path']
for r in json.loads((out / 'commands.json').read_text()):
    assert r['exit_code'] == 0
    assert hashlib.sha256((out / r['log']).read_bytes()).hexdigest() == r['log_sha256']
s = json.loads(Path('build/P14.21/start.json').read_text())
c = task_contract.build(p, 'P14.21', actual_route=s['decision']['route_receipt']['evidence'])
context = task_contract.current_validation_context(p, 'P14.21')
receipts = [task_contract.build_validation_receipt(c, e['id'], exit_code=0, context=context)
            for e in c['evidence'] if e['algorithm'] == 'shell-command']
verdict = task_contract.evaluate(c, validation_receipts=receipts, validation_context=context)
assert verdict['pre_mutation'] == verdict['pre_final'] == 'allow', verdict
plan_closure.build_closure(p, 'P14', check=True)
plan_item_state.build_states(p, item_id='P14.21', check=True)
plan_status.build_status(p, check=True)
plan_decisions.build_views(p, check=True)
candidate = plan_gates.phase_candidate_fingerprint(p, 'P14')
gate = plan_gates.phase_close_gate(p, 'P14', candidate_fingerprint=candidate['fingerprint'], candidate_snapshot=candidate['snapshot'])
assert gate['allow']
receipt = plan_delivery.build_terminal_receipt(p, 'P14.21')
plan_delivery.verify_receipt(p, receipt)
plan_delivery.write_receipt('build/P14.21/delivery-current.json', receipt)
Path('build/P14.21/final-verification.json').write_text(json.dumps({'context': context, 'contract': verdict,
    'validation_receipts': receipts, 'phase_gate': gate, 'delivery': receipt}, ensure_ascii=False, indent=2)+'\n')
print(json.dumps({'contract': verdict, 'phase_gate': gate['code'], 'next_exact': receipt['next_exact'],
                  'commit': receipt['commit_sha'], 'continuity': receipt['continuity']['reason']}, ensure_ascii=False))
