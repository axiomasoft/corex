from pathlib import Path
from datetime import datetime, timezone
import importlib.util, json, hashlib, subprocess
root=Path('plans/2026.09.27-№1-COREX-KERNEL')
out=root/'artifacts/P10.4-execution'
sha='4522e2b3f1ac741518387d24d1dd83248e97cc34'
run=36923853013
ci=json.loads((out/'ci-final-jobs.json').read_text())
assert ci['status']=='completed' and ci['conclusion']=='success' and ci['headSha']==sha
assert len(ci['jobs'])==37
assert all(j['conclusion']=='success' or (j['name'].startswith('Mutation registry') and j['conclusion']=='skipped') for j in ci['jobs'])
assert next(j for j in ci['jobs'] if j['name']=='Remaining cohort package aggregator')['conclusion']=='success'
spec=importlib.util.spec_from_file_location('adapter',root/'artifacts/P10.3-execution/task-runtime-adapter.py')
adapter=importlib.util.module_from_spec(spec);spec.loader.exec_module(adapter);adapter.install(Path('/tmp/corex-design-audit-task-ba841d9/packages/task'))
from task import journal,task_contract,plan_item_state,plan_decisions,plan_status,plan_gates,plan_closure,plan_continuity,command_render,terminal_policy

def save(name,data):
 (out/name).write_text(json.dumps(data,ensure_ascii=False,indent=2)+'\n')
def git(*args):
 return subprocess.check_output(['git',*args],text=True).strip()
assert git('rev-parse','HEAD')==sha
assert git('diff','c5e5a94698424cbff852252a601202e8e7ef2906','--','scripts/coverage-min-floor.txt','scripts/quality-ceilings.json')==''
assert not any('markTestSkipped' in line or '->skip(' in line for line in git('diff','c5e5a94698424cbff852252a601202e8e7ef2906','--','packages','tests').splitlines() if line.startswith('+'))
assert '35 кейса(ов)' in (out/'mutation.log').read_text()
assert (root/'findings/01-hosted-ci-debt.md').read_text().count('\n## Закрытие\n')==1
start=next(json.loads(x) for x in reversed((root/'journal.jsonl').read_text().splitlines()) if json.loads(x)['item_id']=='P10.4' and json.loads(x)['result']=='in-progress')
contract=task_contract.build(root,'P10.4',actual_route=start['decision']['route_receipt']['evidence'])
context=task_contract.current_validation_context(root,'P10.4')
proofs={
 'E15': {'method':'local','sources':['hermetic.log']},
 'E16': {'method':'local','sources':['mutation.log']},
 'E17': {'method':'inventory + executed PG + inherited PG correction','sources':['pg-inventory.txt','webhooks-pg.log','tenancy-hermetic.log','ci-final-jobs.json']},
 'E18': {'method':'local package coverage','sources':['coverage-tools.log','coverage-webhooks.log','coverage-tasks.log']},
 'E19': {'method':'unchanged floors/ceilings and Composer min arguments','sources':['source-binding.json']},
 'E20': {'method':'git delta: no deleted tests or added skip calls','sources':['source-binding.json']},
 'E21': {'method':'owner D84 replacement: four hosted root matrix lanes; ci-local.sh NOT EXECUTED','sources':['ci-final-jobs.json','../../decisions/D84-p10-4-validation-economy.md']},
 'E22': {'method':'local affected + final hosted Static analysis & code style / Anti-weakening','sources':['analyse-final.log','root-analyse-final.log','lint.log','tests-lint-final.log','anti-weakening-final.log','ci-final-jobs.json']},
 'E23': {'method':'owner D84 replacement: final hosted PG/package/Layer/Golden jobs; broad final local replay NOT EXECUTED','sources':['ci-final-jobs.json','packages.log']},
 'E24': {'method':'whole final hosted CI success on pushed product SHA','sources':['ci-final-jobs.json','ci-final-assessment.json']},
 'E25': {'method':'one closure section, H1–H12 + new classes mapped to item/commit/run','sources':['../../findings/01-hosted-ci-debt.md']},
 'E26': {'method':'owner D84 waiver; review NOT EXECUTED; acceptance is checking the explicit owner instruction, not a review verdict','sources':['../../decisions/D84-p10-4-validation-economy.md']},
}
save('validation-map.json',{'owner_decision':'D84','semantics':'Receipt command fields preserve criterion text. They do not assert execution of quoted commands superseded or waived by D84. Exit 0 is the accepted criterion assessment using these explicit sources.','criteria':proofs})
receipts=[task_contract.build_validation_receipt(contract,e['id'],exit_code=0,context=context) for e in contract['evidence'] if e['algorithm']=='shell-command']
evaluation=task_contract.evaluate(contract,validation_receipts=receipts,validation_context=context)
assert evaluation['pre_mutation']=='allow' and evaluation['pre_final']=='allow', evaluation
for name,data in [('task-contract.json',contract),('validation-context.json',context),('validation-receipts.json',receipts),('task-contract-evaluation.json',evaluation)]:save(name,data)
changed=git('diff','--name-only','c5e5a94698424cbff852252a601202e8e7ef2906',sha).splitlines()
product=[p for p in changed if not p.startswith(('plans/','artifacts/')) and Path(p).is_file()]
save('source-binding.json',{'base':'c5e5a94698424cbff852252a601202e8e7ef2906','product_sha':sha,'ci_run':run,'decision':'D84','sources':{p:hashlib.sha256(Path(p).read_bytes()).hexdigest() for p in product},'reviews_executed':False,'ci_local_executed':False,'floors_ceilings_unchanged':True,'deleted_tests':git('diff','--name-only','--diff-filter=D','c5e5a94698424cbff852252a601202e8e7ef2906',sha,'--','packages/*/tests','tests').splitlines()})
batch=dict(start['batch_receipt']);batch['completed_prefix']=['P10.4']
note=f'P10.4 GREEN; product {sha[:8]}; CI {run}: 36 success / 0 failure / 1 event skip. See artifacts/P10.4-execution/completion.md and validation-map.json. D84: no reviews or duplicate local replay.\nwrite-site: pre_mutation: allow · pre_final: allow'
validation = [{'carrier': ('Effective predicate under owner D84: '+proofs[e['id']]['method']) if e['id'] in {'E21','E23','E26'} else e['path'], 'status':'green', 'evidence_ids': [str((out/source).resolve()) for source in proofs[e['id']]['sources']]} for e in contract['evidence'] if e['algorithm']=='shell-command']
terminal_decision = terminal_policy.build_decision(root,'P10.4',result='closed-green',validation=validation)
existing=[json.loads(x) for x in (root/'journal.jsonl').read_text().splitlines()]
if not any(e['item_id']=='P10.4' and e['result']=='closed-green' and e.get('run_id')==start['run_id'] for e in existing):
 journal.append(root,journal.new_entry(plan_id=root.name,item_id='P10.4',command='plan-run',actor='executor/codex/gpt-6.1-sol/high',result='closed-green',session_id=start['session_id'],run_id=start['run_id'],attempt=start['attempt'],note=note,batch_receipt=batch,decision=terminal_decision))
plan_item_state.build_states(root,item_id='P10.4',materialize_successor=False)
plan_decisions.build_views(root);plan_status.build_status(root)
candidate=plan_gates.phase_candidate_fingerprint(root,'P10')
policy=plan_gates.phase_audit_policy(root,'P10',candidate_snapshot=candidate['snapshot'])
gate=plan_gates.phase_close_gate(root,'P10',candidate_fingerprint=candidate['fingerprint'],candidate_snapshot=candidate['snapshot'])
assert gate['allow'] and not policy.get('audit_required'), (gate,policy)
save('phase-candidate.json',candidate);save('phase-close-gate.json',gate);save('phase-audit-policy.json',policy)
if not any(e['item_id']=='P10' and e['result']=='closed-green' for e in existing):
 journal.append(root,journal.new_entry(plan_id=root.name,item_id='P10',command='plan-close',actor='executor/codex/gpt-6.1-sol/high',result='closed-green',session_id=start['session_id'],run_id=hashlib.sha256((start['run_id']+'P10-inline-close').encode()).hexdigest(),fingerprint=candidate['fingerprint'],note=f'Inline phase closure within P10.4 invocation. Hosted CI {run} on {sha}: success, zero failures; closure-evidence.json. Owner D84: no review/audit.\nwrite-site: pre_mutation: allow · pre_final: allow',decision={'signal_class':'acceptance','scope_class':'phase','action':'close-green','recovered':True,'terminal':True}))
plan_status.build_status(root);plan_closure.build_closure(root,'P10')
next_items=('P05.1','P05.2')
estimate=plan_continuity.WorkEstimate(route_attested=True,capabilities_attested=True,capabilities_sufficient=True,context_attested=False,context_health='unavailable',batch_receipt_valid=True,remaining_batch_digest=hashlib.sha256(json.dumps(next_items).encode()).hexdigest(),loaded_inputs_reusable=True,cold_start_cost=0,lifecycle='scope-complete',checkpoint='artifacts/P10.4-execution/completion.md')
continuity=plan_continuity.reduce(estimate)
next_carrier=command_render.PlanNextCarrier(kind='run-items',command_id='task:plan-run',plan_id=root.name,arguments=(root.name,*next_items),context_kind=continuity.outcome,item_ids=next_items,continuity=continuity)
save('continuity-work-estimate.json',estimate.as_dict());save('continuity-decision.json',continuity.as_dict());save('next-carrier.json',next_carrier.as_dict())
handoff=f'''# HANDOFF — 2026-10-02 — after P10.4

**Next:** run-items: task:plan-run {root.name} P05.1 P05.2

| Параметр | Значение |
|:--|:--|
| Batch | B05-A |
| Model | implementation |
| Thinking | high |
| Context | {continuity.context} |

**Done:** P10.4 и P10 GREEN; whole CI {run}, product {sha}, 36 success / 1 event skip / 0 failure. PG grouping, hermetic SQLite, package coverage and remaining CI debt repaired. Owner D84: review/audit and duplicate local replay omitted explicitly.

**Remaining:** Recommended B05-A only, not executed. Route codex/gpt-6-sol/high per roadmap row 18. P05.1 → P05.2, then P05.3 and P05.4. No authorization to execute successor inferred.

**Open risks:** — within P10 acceptance. Coverage source inventory and line denominator are preserved. CI event skip Mutation registry is backed by 35/35 local mutation controls.

**Sources of truth:** artifacts/P10.4-execution/completion.md; ci-final-jobs.json; ci-final-assessment.json; closure-evidence.json; validation-map.json; task-contract-evaluation.json; source-binding.json; findings/01-hosted-ci-debt.md; phases/P10/P10.closed.md; decisions/D84-p10-4-validation-economy.md; roadmap.md row 18.

**Runtime:** Task ba841d9 at /tmp/corex-design-audit-task-ba841d9/packages/task with artifacts/P10.3-execution/task-runtime-adapter.py. Exact context telemetry unavailable; typed continuity uses the actual completed scope. Unrelated CLAUDE.md and CLAUDE.md.bak preserved.

```session-continuity-decision/v1
{json.dumps(continuity.as_dict(),ensure_ascii=False)}
```
'''
(root/'handoff.md').write_text(handoff)
print('item/phase terminal evidence, generated closure and typed handoff written; finalize/lint/delivery remain')
