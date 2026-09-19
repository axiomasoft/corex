from pathlib import Path
import json,hashlib
from task import plan_context,task_contract,plan_evidence,terminal_policy,journal,plan_item_state,plan_status,plan_decisions,plan_bundle,plan_recovery,route_evidence,plan_gates,plan_closure
p=Path('plans/2026.09.15-№1-COREX-FLEXCRM-REMAINING');a=p/'artifacts/B-P1-QUALIFY';item='P1.4'
sha=lambda x:hashlib.sha256(Path(x).read_bytes()).hexdigest()
def save(name,value):(a/name).write_text(json.dumps(value,ensure_ascii=False,indent=2)+'\n')
checks=json.loads((a/'P1.4-check-results.json').read_text());assert len(checks)==3 and all(x['exit_code']==0 for x in checks)
assert json.loads((a/'P1.4-semantic-review.json').read_text())['status']=='PASS'
independent=json.loads((a/'P1.4-independent-seal-check.json').read_text());assert independent['result']=='PASS'
for name,digest in independent['product_sha256'].items():assert sha(p/'findings'/name)==digest
save('P1.4-seal-structural.json',json.loads(checks[0]['stdout']))
snapshot=plan_context.capture(p,item);snapshot.assert_live_integrity();assert snapshot.snapshot_sha256=='0cd79b152b2ff3f2c2b31ea1f5353991069744be3232936e96e3b3cca7fdfcc0'
actual=route_evidence.capture_launch(expected_invocation='$ task:plan-exec '+p.name+' P1.1',expected_cwd=Path.cwd());run=json.loads((a/'P1.4-run.json').read_text())
receipt=route_evidence.build_run_receipt(plan_id=p.name,item_id=item,attempt=1,session_id=actual['thread_id'],run_id=run['run_id'],evidence=actual);route_evidence.verify_run_receipt(receipt,current_evidence=actual);save('P1.4-route-final.json',receipt)
c=task_contract.build(p,item,actual_route=actual);g=plan_evidence.compile_graph(p,item);boundary=task_contract.evaluate(c,evidence_graph=g,requires_closure_freshness=True);assert boundary['pre_mutation']==boundary['pre_final']=='allow',boundary;save('P1.4-write-site.json',boundary)
anchor=next(z['anchor'] for z in g['nodes'] if z['id']=='product');save('P1.4-product-anchor.json',anchor)
validation=[{'carrier':' '.join(x['command']),'status':'green','evidence_ids':['artifacts/B-P1-QUALIFY/P1.4-check-results.json',sha(a/'P1.4-check-results.json')]} for x in checks]
validation.append({'carrier':'P1.4 substantive all-R/parent/source/cohort/finite-conflict/effective-condition/no-new-scope assessment','status':'green','evidence_ids':['artifacts/B-P1-QUALIFY/P1.4-semantic-review.json',sha(a/'P1.4-semantic-review.json'),'artifacts/B-P1-QUALIFY/P1.4-independent-seal-check.json',sha(a/'P1.4-independent-seal-check.json')]})
decision=terminal_policy.build_decision(p,item,result='closed-green',validation=validation)
note='seal/downstream/lint+semantic PASS;100sources/52R/365parents/682conditions+6delimiter IDs;17finite conflicts/11SHA;DI34/AP13 open. Exact commands/cwd/results: artifacts/B-P1-QUALIFY/P1.4-check-results.json\nwrite-site: pre_mutation: allow · pre_final: allow'
assert len(note)<=300
event=journal.new_entry(plan_id=p.name,item_id=item,command='plan-exec',actor='executor/gpt-5.6-sol/high',result='closed-green',session_id=actual['thread_id'],run_id=run['run_id'],attempt=1,note=note,decision=decision);journal.append_idempotent_terminal(p,event)
plan_item_state.build_states(p);plan_status.build_status(p);plan_decisions.build_views(p);plan_bundle.build(p,item)
after=plan_evidence.compile_graph(p,item);proof=plan_evidence.verify_closure(anchor,next(z['anchor'] for z in after['nodes'] if z['id']=='product'));save('P1.4-closure-byte-proof.json',proof)
candidate=plan_gates.phase_candidate_fingerprint(p,'P1');gate=plan_gates.phase_close_gate(p,'P1',candidate_fingerprint=candidate['fingerprint'],candidate_snapshot=candidate['snapshot']);save('P1-phase-close-admission.json',gate);assert gate['allow'],gate
assert plan_status.STATUS_DONE==__import__('task.plan_layout',fromlist=['phase_statuses']).phase_statuses(p)['P1']
closure=plan_closure.build_closure(p,'P1');plan_closure.build_closure(p,'P1',check=True);print('P1 inline phase closure',closure)
next_bundle=plan_recovery.materialize_next_ready(p,item);print('Next bundle (prepare only)',next_bundle)
admission=task_contract.admit_batch(p,['P2.1']);save('P2.1-admission.json',admission);print('P2.1 admission',admission['verdict']);assert admission['verdict']=='ALLOW',admission
batch=task_contract.build_batch_receipt(admission['batch'],completed_prefix=[]);save('P2.1-batch-receipt.json',batch)
print('P1.4 CLOSED GREEN; P1 phase CLOSED; successor P2.1 prepared, NOT STARTED. B-P1 authorized scope complete.')
