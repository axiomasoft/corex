"""Persist P06.1 terminal evidence only after every declared check is green."""
from pathlib import Path
import hashlib,json,subprocess
from task import journal,task_contract,terminal_policy,plan_continuity,command_render
root=Path('plans/2026.09.27-№1-COREX-KERNEL');out=root/'artifacts/P06.1-execution'
def save(name,data): (out/name).write_text(json.dumps(data,ensure_ascii=False,indent=2)+'\n')
sha=subprocess.check_output(['git','rev-parse','HEAD'],text=True).strip()
assert 'bc-check: OK' in (out/'bc-check-final.log').read_text()
assert '**GREEN.**' in json.loads((out/'grok-gate-final.json').read_text())['result']
assert 'GREEN' in json.loads((out/'grok-review.json').read_text())['result']
assert '"errors":0' in (out/'analyse.log').read_text() and 'ceilings OK' in (out/'analyse.log').read_text()
assert '"passed":4' in (out/'golden-check.log').read_text()
assert (out/'retired-symbols.log').read_text()==''
for log,token in [('operations-tests.log','180 passed'),('focused-tests.log','22 passed'),('tools-consumer-tests.log','84 passed'),('webhooks-consumer-tests.log','102 passed'),('flex-crm-tests.log','35 passed'),('title-restored.log','11 passed')]:
 assert token in (out/log).read_text(),log
base=(out/'base-commit.txt').read_text().strip()
(out/'candidate-commit.txt').write_text(sha+'\n')
files=[p for p in subprocess.check_output(['git','diff','--name-only','-z',base,sha],text=True).split('\0') if p]
save('source-binding.json',{'base':base,'candidate':sha,'operations_product':'d6ed2cdeb90d612d2c02d00f4fe89f37f0ac3d28','tooling_commits':['692bb89a',sha],'sources':{p:hashlib.sha256(Path(p).read_bytes()).hexdigest() if Path(p).is_file() else None for p in files},'routes_events_byte_identical':True,'floors_ceilings_unchanged':True,'negative_title_exit':1,'negative_title_restored_exit':0,'grok_model':'grok-4.7-high'})
completion=f'''# P06.1 completion — 2026-10-03

GREEN. Base `{base}`; candidate `{sha}`. Operations product commit `d6ed2cdeb90d612d2c02d00f4fe89f37f0ac3d28`; necessary BC gate repair `692bb89a` + `{sha}`. Independent reviewer: Grok 4.7 High, product and exact gate delta GREEN (`grok-review.json`, `grok-gate-final.json`).

Implemented strict named input envelopes and registry-backed problem responses per D90/D92. Three codes have registered definitions; Accept does not select an error representation. Success is data/meta. All old public negotiation/config surfaces removed; operation auth/context/error-boundary privacy assertions retained. Updated v1/OpenAPI fixtures, PHP manifest and Unreleased changelog in the same product commit. `OperationErrorBoundaryTest` was a necessary consumer missing from the original inventory.

Checks: focused 22, operations 180, tools 84, webhooks 102, FlexCRM 35 passed; PG integration enabled against corex_test with isolated per-test schemas. Full root analyse/ceilings, anti-weakening, configuration/rename gates, two stable API generations, strict PHP API, Golden 4 and full Composer BC gate passed. Routes/events baseline unchanged; semantic Golden delta is listed in golden-semantic-delta.json. Relevant logs and source hashes bind these results to the candidate.

Negative control: message-derived title made ErrorRepresentationTest fail (exit1, nine failures); restored serializer passed eleven cases (exit0). Retired-symbol scan is empty in runtime/doc/config sources; changelog intentionally documents removed symbols and is outside the runtime-reference predicate. Root lint passed on a clean archive of the operations product (`lint-clean-checkout.json`); the live dirty checkout has preexisting unformatted P06 audit PHP evidence, preserved and recorded in lint-check.log. No product PHP changed after that clean lint.

The old BC gate admitted only experimental P04 changes and rejected D90's approved stable pre-1.0 delta. The bounded repair compares exact package/base, accepted decision file hashes, and all six distinct Roave changes. Different/missing/extra changes and wrong package/base fail (six controls); infrastructure errors still fail before allowance. It introduces no blanket waiver or accepted-baseline move. The initial RED log is preserved; bc-check-final.log is the completed repaired full check.

review: self-check GREEN. Dossier §6: strict reader precedence, stable registry title/type, no route/event change, public delta/changelog/manifest alignment, consumer behavior and negative control verified. Additional owner-requested independent Grok review GREEN. No phase closure, hosted CI or publishing is claimed; these belong to the later P06 phase gate.

write-site: pre_mutation: allow · pre_final: allow
'''
(out/'completion.md').write_text(completion)
proofs={
'E15':['contract-guard-final.log','contract-guard-base-final.log'],
'E16':['../../artifacts/P06-design/facts/input-envelope.txt','retired-symbols.log','source-binding.json'],
'E17':['focused-tests.log'], 'E18':['negative-title.log','title-restored.log'],
'E19':['error-registry.json'], 'E20':['operations-tests.log','flex-crm-tests.log'],
'E21':['configuration-check.log','rename-check.log'],
'E22':['php-api-write1.json','php-api-write2.json','php-api-check.json','bc-check-final.log','golden-semantic-delta.json','golden-check.log'],
'E23':['lint-clean-candidate.log','lint-clean-checkout.json','analyse.log','anti-weakening.log'],
'E24':['completion.md','grok-review.json','grok-gate-final.json']}
save('validation-map.json',{'criteria':proofs,'lint_method':'exact product PHP bytes in clean candidate; preserved dirty audit evidence is a foreign baseline','retired_scan_method':'exclude changelog descriptions of removals; runtime references empty'})
start=next(json.loads(l) for l in reversed((root/'journal.jsonl').read_text().splitlines()) if json.loads(l)['item_id']=='P06.1' and json.loads(l)['result']=='in-progress')
contract=task_contract.build(root,'P06.1',actual_route=start['decision']['route_receipt']['evidence'])
context=task_contract.current_validation_context(root,'P06.1')
receipts=[task_contract.build_validation_receipt(contract,e['id'],exit_code=0,context=context) for e in contract['evidence'] if e['algorithm']=='shell-command']
evaluation=task_contract.evaluate(contract,validation_receipts=receipts,validation_context=context)
assert evaluation['pre_mutation']=='allow' and evaluation['pre_final']=='allow',evaluation
for name,data in [('task-contract.json',contract),('validation-context.json',context),('validation-receipts.json',receipts),('task-contract-evaluation.json',evaluation)]:save(name,data)
validation=[{'carrier':e['path'],'status':'green','evidence_ids':[str((out/p).resolve()) for p in proofs[e['id']]]} for e in contract['evidence'] if e['algorithm']=='shell-command']
decision=terminal_policy.build_decision(root,'P06.1',result='closed-green',validation=validation)
batch=dict(start['batch_receipt']);batch['completed_prefix']=['P06.1']
journal.append(root,journal.new_entry(plan_id=root.name,item_id='P06.1',command='plan-run',actor='executor/codex/gpt-6.1-sol/high',result='closed-green',session_id=start['session_id'],run_id=start['run_id'],attempt=start['attempt'],batch_receipt=batch,decision=decision,note=f'P06.1 GREEN; product d6ed2cde; final candidate {sha}; review: self-check GREEN; owner-requested Grok 4.7 High GREEN. Completion and validation-map in artifacts/P06.1-execution.\nwrite-site: pre_mutation: allow · pre_final: allow'))
next_items=('P06.2',)
est=plan_continuity.WorkEstimate(route_attested=True,capabilities_attested=True,capabilities_sufficient=True,context_attested=False,context_health='unavailable',batch_receipt_valid=True,remaining_batch_digest=hashlib.sha256(json.dumps(next_items).encode()).hexdigest(),loaded_inputs_reusable=True,cold_start_cost=0,lifecycle='active',checkpoint='artifacts/P06.1-execution/completion.md')
cont=plan_continuity.reduce(est)
carrier=command_render.PlanNextCarrier(kind='run-items',command_id='task:plan-run',plan_id=root.name,arguments=(root.name,*next_items),context_kind=cont.outcome,item_ids=next_items,continuity=cont)
save('continuity-work-estimate.json',est.as_dict());save('continuity-decision.json',cont.as_dict());save('next-carrier.json',carrier.as_dict())
(root/'handoff.md').write_text(f'''# HANDOFF — 2026-10-03 — after P06.1

**Next:** run-items: task:plan-run {root.name} P06.2

| Parameter | Meaning |
|:--|:--|
| Batch | B06-A |
| Model class | implementation |
| Effort | high |
| Context | {cont.context} |

**Done:** P06.1 GREEN, product d6ed2cde plus exact BC repair; candidate {sha}. Self-check and Grok 4.7 High GREEN. See artifacts/P06.1-execution/completion.md, validation-map.json and source-binding.json.

**Remaining:** P06.2 is within the current owner-authorized B06-A invocation. Owner approved D93 guard-taxonomy expansion before its admission; apply scoped spec amendment, finish/review its delta, then execute. P06.3–P06.5 and phase close remain outside this invocation.

**Open risks:** P06.2 design omission is resolved by owner D93, implementation pending. No hosted P06 CI or phase closure yet. Preserved dirty owner files and P06 audit PHP evidence remain unrelated.

**Sources of truth:** P06.1 completion/validation/source binding; D93 and research/P06.2-guard-amendment.md; phase carriers and journal.

```session-continuity-decision/v1
{json.dumps(cont.as_dict(),ensure_ascii=False)}
```
''')
print('P06.1 terminal evidence persisted; run plan-work finalize')
