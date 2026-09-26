from pathlib import Path
import json
from task import journal, terminal_policy, plan_item_state, plan_decisions, plan_status, plan_gates

p = Path('plans/2026.09.19-№1-COREX-PLATFORM-HARDENING')
out = p / 'artifacts/P14.21-execution'
s = json.loads(Path('build/P14.21/start.json').read_text())
qualification = json.loads((out / 'task-contract.json').read_text())
assert qualification['verdict']['pre_mutation'] == qualification['verdict']['pre_final'] == 'allow'
validation = [{'carrier': r['id'] + ': ' + ' '.join(r['argv']), 'status': 'green',
               'evidence_ids': ['artifacts/P14.21-execution/' + r['log'] + '#sha256=' + r['log_sha256']]}
              for r in json.loads((out / 'commands.json').read_text()) if not r['id'].startswith('bc-')]
validation += [{'carrier': 'P14 section 14 matrix / operational docs / explicit BC disclosure', 'status': 'green',
                'evidence_ids': ['findings/P14-execution-validation.md', 'artifacts/P14.21-execution/acceptance-matrix.json',
                                 'artifacts/P14.21-execution/task-contract.json']}]
decision = terminal_policy.build_decision(p, s['item_id'], result='closed-green', validation=validation)
e = journal.new_entry(plan_id=p.name, item_id=s['item_id'], command=s['command'], actor=s['actor'],
    result='closed-green', session_id=s['session_id'], run_id=s['run_id'], attempt=s['attempt'],
    batch_receipt=s['batch_receipt'], decision=decision,
    note='P14.21 GREEN: docs, 16-row acceptance/hashes, root 815 and PG 239, affected suites, 34 nonempty mutation refusals. Repairs qualified; API historical experimental BC breaks disclosed. Local synthetic receiver only.')
journal.append_idempotent_terminal(p, e)
plan_item_state.build_states(p, item_id=s['item_id'], materialize_successor=False)
plan_decisions.build_views(p)
plan_status.build_status(p)
candidate = plan_gates.phase_candidate_fingerprint(p, 'P14')
gate = plan_gates.phase_close_gate(p, 'P14', candidate_fingerprint=candidate['fingerprint'], candidate_snapshot=candidate['snapshot'])
(out / 'phase-close-gate.json').write_text(json.dumps({'candidate': candidate, 'gate': gate}, ensure_ascii=False, indent=2)+'\n')
print(json.dumps({'item': s['item_id'], 'closed': True, 'phase_gate': gate}, ensure_ascii=False))
