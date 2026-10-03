"""One-time P06.2 closure, guarded by actual candidate/check/reviewer evidence."""
from pathlib import Path
import json,hashlib,subprocess
from task import journal,task_contract,terminal_policy,plan_continuity,command_render
r=Path('plans/2026.09.27-№1-COREX-KERNEL');o=r/'artifacts/P06.2-execution'
def save(name,data):(o/name).write_text(json.dumps(data,ensure_ascii=False,indent=2)+'\n')
sha=(o/'candidate-commit.txt').read_text().strip();base=(o/'base-commit.txt').read_text().strip()
assert subprocess.check_output(['git','rev-parse','HEAD'],text=True).strip()==sha
report=next(json.loads(l) for l in (o/'grok-recheck.json').read_text().splitlines() if l.startswith('{"type":"result"'))
assert '**GREEN' in report['result'] or 'Verdict: GREEN' in report['result'] or '**Verdict: GREEN' in report['result'],report['result']
assert not report.get('is_error')
assert '"passed":3' in (o/'root-consumer-tests.log').read_text()
assert 'bc-check: OK' in (o/'bc-check-tools-final.log').read_text()
assert '"result":"passed"' in (o/'lint-clean-candidate.log').read_text()
assert '"errors":0' in (o/'analyse-restored.log').read_text() and 'ceilings OK' in (o/'analyse-restored.log').read_text()
assert '"passed":4' in (o/'golden-check.log').read_text()
for name,count in [('taxonomy-tests.log',15),('tools-tests.log',99),('operations-tests.log',180)]:assert f'{count} passed' in (o/name).read_text(),name
assert (o/'retired-error-literals.log').read_text()=='' and (o/'retired-outcome-constants.log').read_text()==''
negative=json.loads((o/'negative-denied.json').read_text());assert negative['negative_exit']!=0 and negative['restored_exit']==0
assert all(json.loads((o/'bc-allowance-controls.json').read_text()).values())
binding=json.loads((o/'source-binding.json').read_text())
for p,h in binding['sources'].items():assert Path(p).is_file() and hashlib.sha256(Path(p).read_bytes()).hexdigest()==h,p
completion=f'''# P06.2 completion — 2026-10-03

GREEN. Base `{base}`; candidate/product commit `{sha}`. Independent reviewer: Grok 4.7 High, session `{report['session_id']}`, GREEN (`grok-recheck.json`); the initial RED report in `grok-review.json` is retained, with its sole root-consumer defect repaired and rechecked. Owner-approved D93 amendment independently reviewed by Grok 4.7 before execution; authored fingerprint b3a69c9eefeb599a8e361a856299cf2a0394d49675870f2774ecd2aff3e22a98 has whole-design finish and scoped delta GREEN, reusing unchanged prior design audit.

Implemented thirteen registered `ToolsErrorCode` cases, three-case `ToolOutcome`, typed `ToolResult` and Internal `ToolSchemaFailure::$errorCode`. Definitions register before the core registry freezes. Invoker denials accept the enum; invoked `ApiError` codes keep their original registered namespace through a separate string path. Supervisor/worker classify schema startup/limits by typed identities; generic exception text is not a tool-code classifier. MCP writes the unchanged outcome strings via enum value and registered error codes.

The initial inventory omitted root `tests/Feature/OperationContractTest.php`. Grok found its two stale constant reads; both now use `ToolOutcome::Committed`, with the original contract/async/replay assertions preserved. Original candidate cd31e521 was amended to include this necessary shared consumer migration; no product runtime/public/BC/Golden bytes changed during repair.

D93 preserves the five previously omitted guard distinctions: approval required/invalid, forbidden, idempotency conflict and unknown effect. Default/consumer guard conditions, ordering, callback/effect/replay and operation-key behavior are preserved. Unknown stays unknown and is not retryable. Consumer suites retain effect/transaction/replay assertions and now resolve tool error codes through the real registry.

Checks: taxonomy15 passed130assertions, tools99 passed614assertions, operations180 passed2163assertions, root OperationContractTest3 passed32assertions; isolated SQLite plus enabled corex_test PostgreSQL cases. Registry13; module-layout zero violations; ten empty operations Tools/Webhooks directories removed with zero files removed. Two PHP API generations byte-identical, strict PHP API passed. Golden4; exactly four new types and only ToolResult changed, wire adds thirteen definitions, prior records/routes/events unchanged. Manifest, Unreleased changelog, exact five-break D90/D92/D93-bound BC allowance are in the same product commit. Six BC controls reject different/missing/extra changes and wrong package/base; infrastructure classification is unchanged.

Negative control: temporary string passed to denied() made full root composer analyse exit1 with argument.type at GuardedToolInvoker:57; exact original bytes restored, full root analyse exit0/ceilingsOK. Floors and ceilings unchanged; anti-weakening passed. Affected Pint and root composer lint:check in a clean archive of this exact commit passed. Preexisting dirty design-audit PHP prototypes were preserved; their immutable bytes are also archived in artifacts/P06-historical-php-proofs.tar.gz for portability, with a source hash manifest. They are foreign baseline, not product PHP changes.

BC validation scope: BC_CHECK_ONLY=tools composer bc-check exit0 on current tools; P06.1's completed full bc-check-final.log covers the unchanged package cohort. This is affected validation after one full batch check, not a claim of a second full replay. Same root/provider route was freshly captured against the owner's original two-item invocation; the admission receipt preserves membership P06.1/P06.2 and the completed prefix P06.1 after D93's new spec digest.

review: self-check GREEN. Dossier §6 and D93 checked: owner prefixes, typed exception/property loading, registered passthrough, safe fallback, preserved guard outcomes/order, exact public/Golden delta, stable generators and negative control. Additional owner-requested independent Grok 4.7 High review GREEN.

No P06 phase closure or hosted CI is claimed. P06.3–P06.5 and final phase gates remain outside this workset.

write-site: pre_mutation: allow · pre_final: allow
'''
(o/'completion.md').write_text(completion)
proofs={
'E19':['contract-guard.log','contract-guard-base.log'],
'E20':['retired-error-literals.log','retired-outcome-constants.log'],
'E21':['taxonomy-tests.log','tools-tests.log','root-consumer-tests.log'],
'E22':['negative-denied.json','negative-denied.log','analyse-restored.log'],
'E23':['tools-tests.log','operations-tests.log'],
'E24':['error-registry.json'],
'E25':['empty-directories.json','module-layout.log'],
'E26':['php-api-write1.json','php-api-write2.json','api-generation-first.json','php-api-check.json','bc-check-tools-final.log','../P06.1-execution/bc-check-final.log','golden-semantic-delta.json','golden-check.log','bc-allowance-controls.json'],
'E27':['lint-clean-candidate.log','lint-clean-checkout.json','analyse-restored.log','anti-weakening.log'],
'E28':['completion.md','grok-review.json','grok-recheck.json','review-repair.json','grok-design-review.json']}
save('validation-map.json',{'criteria':proofs,'bc_method':'current affected tools check + P06.1 full unchanged cohort','lint_method':'exact clean product candidate, preserved foreign baseline prototypes','review_model':'grok-4.7-high'})
start=next(json.loads(l) for l in reversed((r/'journal.jsonl').read_text().splitlines()) if json.loads(l)['item_id']=='P06.2' and json.loads(l)['result']=='in-progress')
assert not any(json.loads(l)['item_id']=='P06.2' and json.loads(l)['result']=='closed-green' for l in (r/'journal.jsonl').read_text().splitlines())
c=task_contract.build(r,'P06.2',actual_route=start['decision']['route_receipt']['evidence']);ctx=task_contract.current_validation_context(r,'P06.2');receipts=[task_contract.build_validation_receipt(c,e['id'],exit_code=0,context=ctx) for e in c['evidence'] if e['algorithm']=='shell-command'];ev=task_contract.evaluate(c,validation_receipts=receipts,validation_context=ctx);assert ev['pre_mutation']=='allow' and ev['pre_final']=='allow',ev
for name,data in [('task-contract.json',c),('validation-context.json',ctx),('validation-receipts.json',receipts),('task-contract-evaluation.json',ev)]:save(name,data)
validation=[{'carrier':e['path'],'status':'green','evidence_ids':[str((o/p).resolve()) for p in proofs[e['id']]]} for e in c['evidence'] if e['algorithm']=='shell-command'];dec=terminal_policy.build_decision(r,'P06.2',result='closed-green',validation=validation);batch=dict(start['batch_receipt']);batch['completed_prefix']=['P06.1','P06.2']
journal.append(r,journal.new_entry(plan_id=r.name,item_id='P06.2',command='plan-run',actor='executor/codex/gpt-6.1-sol/high',result='closed-green',session_id=start['session_id'],run_id=start['run_id'],attempt=start['attempt'],batch_receipt=batch,decision=dec,note=f'P06.2 GREEN; product {sha}; owner D93 taxonomy13; tests15/99/180; review: self-check GREEN; Grok 4.7 High independent GREEN. Completion and validation-map in artifacts/P06.2-execution.\nwrite-site: pre_mutation: allow · pre_final: allow'))
next_items=('P06.3',);estimate=plan_continuity.WorkEstimate(route_attested=True,capabilities_attested=True,capabilities_sufficient=True,context_attested=False,context_health='unavailable',batch_receipt_valid=True,remaining_batch_digest=hashlib.sha256(json.dumps(next_items).encode()).hexdigest(),loaded_inputs_reusable=True,cold_start_cost=0,lifecycle='scope-complete',checkpoint='artifacts/P06.2-execution/completion.md');cont=plan_continuity.reduce(estimate);carrier=command_render.PlanNextCarrier(kind='run-items',command_id='task:plan-run',plan_id=r.name,arguments=(r.name,*next_items),context_kind=cont.outcome,item_ids=next_items,continuity=cont)
save('continuity-work-estimate.json',estimate.as_dict());save('continuity-decision.json',cont.as_dict());save('next-carrier.json',carrier.as_dict())
(r/'handoff.md').write_text(f'''# HANDOFF — 2026-10-03 — after P06.2

**Next:** run-items: task:plan-run {r.name} P06.3

| Parameter | Meaning |
|:--|:--|
| Batch | solo |
| Model class | implementation |
| Effort | high |
| Context | {cont.context} |

**Done:** Authorized B06-A P06.1/P06.2 GREEN. Operations product d6ed2cde, exact BC gate repair 692bb89a/a98f1a65, tools product {sha}. Self-checks and owner-requested Grok 4.7 High reviews GREEN. Owner-approved D93 adds five guard codes, total13, with independent design delta GREEN. Exact candidate checks and evidence maps are in both completion directories.

**Remaining:** P06.3 then P06.4/P06.5 recommended, not authorized by this completed workset. Phase P06 stays open until its final review and hosted CI gate. This scope is complete; current root remains reusable.

**Open risks:** CloudEvents, webhook retry/egress and final phase review/CI remain future owning items. No skipped acceptance inside P06.1/P06.2. Preexisting CLAUDE.md, graph report and owner configuration changes preserved; historical PHP design proofs remain untouched and archived for portability.

**Sources of truth:** artifacts/P06.1-execution/completion.md and artifacts/P06.2-execution/completion.md; validation-map/source-binding/contract evaluations; Grok review JSON; D90/D92/D93; findings/P06.2-guard-design-review.md; phases/P06/ and journal.

```session-continuity-decision/v1
{json.dumps(cont.as_dict(),ensure_ascii=False)}
```
''')
print('P06.2 terminal evidence persisted; plan-work finalize/lint/delivery remain')
