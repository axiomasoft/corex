from pathlib import Path
import json,hashlib,subprocess
from task import journal,task_contract,terminal_policy,plan_continuity,command_render
r=Path('plans/2026.09.27-№1-COREX-KERNEL');o=r/'artifacts/P06.3-execution'
def save(name,value):(o/name).write_text(json.dumps(value,ensure_ascii=False,indent=2)+'\n')
sha=(o/'candidate-commit.txt').read_text().strip();base=(o/'base-commit.txt').read_text().strip()
assert subprocess.check_output(['git','rev-parse','HEAD'],text=True).strip()==sha
for p in json.loads((o/'product-files.json').read_text()):assert Path(p).read_bytes()==subprocess.check_output(['git','show',f'{sha}:{p}']),p
review=json.loads((o/'grok-final-review.json').read_text())
assert 'Verdict: GREEN' in review['text'] or '**Verdict: GREEN' in review['text'],review['text']
assert not review.get('is_error')
bind=json.loads((o/'review-final-binding.json').read_text())
for p,h in bind['sources'].items():assert Path(p).is_file() and hashlib.sha256(Path(p).read_bytes()).hexdigest()==h,p
assert '"passed":73' in (o/'codec-tests-final.log').read_text()
assert '"passed":300' in (o/'core-tests-final.log').read_text()
assert '102 passed' in (o/'webhooks-tests-final.log').read_text()
assert '"passed":242' in (o/'pg-tests.log').read_text()
assert '"passed":1083' in (o/'root-tests-final.log').read_text() and '"passed":17' in (o/'root-tests-final.log').read_text()
assert '"passed":4' in (o/'golden-check-final.log').read_text()
assert '"diagnostics": []' in (o/'php-api-check-final.json').read_text()
for p in ['bc-check.log','bc-check-core-final.log','bc-check-webhooks-final.log']:assert 'bc-check: OK' in (o/p).read_text(),p
assert '"result":"passed"' in (o/'lint-final-candidate.log').read_text()
assert '"result":"failed"' not in (o/'analyse-green-final.log').read_text() and 'ceilings OK' in (o/'analyse-green-final.log').read_text()
assert 'ANTI-WEAKENING GATE: OK' in (o/'anti-weakening-final.log').read_text()
assert (o/'php-api-write1.json').read_bytes()==(o/'php-api-write2.json').read_bytes()
completion=f'''# P06.3 completion — 2026-10-03

GREEN. Base `{base}`; final candidate/product commit `{sha}`. Owner-requested independent Grok 4.7 (medium) read-only review GREEN, session `{review.get('sessionId',review.get('session_id',''))}`; `grok-final-review.json` and `review-final-binding.json` bind the reviewed source bytes. The initial native-tool review issued no product verdict because the local compatibility hook denied all repository reads; that report is retained. The complete digest-bound source/diff packet gave Grok read-only evidence without any hook changes or tool access. The completed final compact review covers the exact final candidate, including the projector PHPDoc and regenerated API documentation delta; its assistant text is preserved verbatim from supported provider export after a redundant request replaced the raw output. The redundant request was stopped without any product write. the earlier large high-effort packet was interrupted after a 600-second observation with no completed turn or product verdict.

Implemented public CloudEventCodec and Internal JsonCloudEventCodec/UriReference. Internal wire matches examples §6 byte-for-byte, uses UTC microseconds, canonical scope JSON string and a closed trace metadata set. Empty data is an object; strict decode and optional attribute/URI cases are tested. MessageContract carries optional absolute dataSchema without fragment; MessageEnvelope validates source and withPayload preserves all other fields. Codec is scoped through MessagingRegistrar and receives the host-bound contract registry. Unknown contracts are refused.

External wire has exactly specversion,id,source,type,time,datacontenttype,data and always omits internal schema, scope, PII, subject, correlation/causation and trace. All installed projector implementations found by git grep are test fixtures under webhooks; no product projector implementation exists. Fixtures now return data only. Fan-out persists the CloudEvent bytes and hash; full envelope counts toward the 20 KiB body limit. Signer/worker use the saved raw body unchanged; Content-Type is application/cloudevents+json, Standard Webhooks headers/algorithm unchanged. Redacting fixture removes secret with an internal dataSchema present; external data has no secret or false schema claim.

VERIFY-AT-EXEC: source values corex/relay, axiomasoft/corex-custom-fields and fixture-address/1 are valid URI references. Metadata grep found storage serialization in SqlOutbox and unrelated credential metadata, no new envelope metadata producer; trace injection remains host-owned TraceContextBridge. Exact commands/results in verify-at-exec.json. RFC 3986 and CloudEvents primary references were reread; no new external assumption or dependency.

Strict API gate caught the designed array<string,mixed> on the new API. Native method signatures are unchanged; concrete EventData aliases describe the existing eight-level JSON payload tree with JSON numbers, the projector imports that alias, and the external codec rejects deeper/non-JSON values. No scanner/ratchet was weakened. Initial root suite also found a predecessor-owned stale public-error fixture: its definitions omitted the 3 operations and 13 tools codes already implemented in P06.1/P06.2. Only that definitions list was synchronized; every old definition and all contracts/dynamic-adapter sections are identical. Error product sources are unchanged from the saved base. Evidence: root-repair.json, preimage and exact16-code delta. The original full root carrier was rerun GREEN after these local repairs.

Checks: core300/894assertions; focused codec+envelope73/94assertions; webhooks102/494assertions with all six isolated corex_test PG cases enabled; root PG242/1155assertions. Full root SQLite1083/5348assertions plus workflow17/79assertions GREEN. Current typed-doc delta passes all package/root PHPStan with zero errors and unchanged ceilings. Strict API0, two byte-identical API generations, Golden4/1412assertions, package docs gate, both committed contract-guards, anti-weakening and exact clean final candidate composer lint:check GREEN. The worktree lint's only failures are untouched historical untracked design PHP prototypes; final lint uses git archive of this exact product SHA with vendor symlink. No historical proof or unrelated owner source/config was changed by lint. The graph report remains an uncommitted generated validation output alongside its preexisting dirty state.

BC: one full package cohort GREEN, then final core/webhooks checks GREEN after typed-doc repair; unchanged cohort evidence retained. No new BC allowance. Golden adds only CloudEventCodec, Internal JsonCloudEventCodec and UriReference; changes MessageContract, MessageEnvelope and Internal WebhookFanout. Events projection adds MessageContract dataSchema:null; routes baseline and wire bytes unchanged, existing rename-map projection retained. Changelogs and manifests share the final product commit; generated public API list adds the codec. No threshold/dependency/DB schema change.

review: self-check GREEN. Dossier §6 checked against exact example, profile separation, URI grammar, trace set, projector inventory, body/signature bytes, public/Golden delta and unchanged routes. Additional owner-requested Grok 4.7 (medium) review GREEN.

P06.4/P06.5 and phase final hosted CI are outside this workset. P06 stays open; no phase closure/audit or hosted CI success is claimed.

write-site: pre_mutation: allow · pre_final: allow
'''
(o/'completion.md').write_text(completion)
proofs={
'E16':['contract-guard.log','contract-guard-base.log'],
'E17':['verify-at-exec.json','completion.md'],
'E18':['codec-tests-final.log','core-tests-final.log'],
'E19':['webhooks-tests-final.log','review-final-binding.json'],
'E20':['verify-at-exec.json'],
'E21':['core-tests-final.log','webhooks-tests-final.log','pg-tests.log','root-tests-final.log'],
'E22':['docs-gate.log','docs-cloud-events.log'],
'E23':['php-api-write1.json','php-api-write2.json','php-api-check-final.json','bc-check.log','bc-check-core-final.log','bc-check-webhooks-final.log','golden-semantic-delta.json','golden-check-final.log'],
'E24':['lint-final-candidate.log','lint-final-checkout.json','lint-worktree.log','analyse-green-final.log','anti-weakening-final.log'],
'E25':['completion.md','grok-review.json','grok-packet-review.json','grok-final-review.json','review-final-binding.json']}
save('validation-map.json',{'criteria':proofs,'bc_method':'full cohort then final affected core/webhooks checks','lint_method':'exact final clean product candidate; historical untracked prototypes preserved','review_model':'grok-4.7/medium'})
save('source-binding.json',{'candidate_sha':sha,'sources':{p:hashlib.sha256(Path(p).read_bytes()).hexdigest() for p in json.loads((o/'product-files.json').read_text())}})
start=next(json.loads(l) for l in reversed((r/'journal.jsonl').read_text().splitlines()) if json.loads(l)['item_id']=='P06.3' and json.loads(l)['result']=='in-progress')
actual=json.loads((o/'route-evidence-final.json').read_text())
assert actual['thread_id']==start['decision']['route_receipt']['evidence']['thread_id']
c=task_contract.build(r,'P06.3',actual_route=actual);ctx=task_contract.current_validation_context(r,'P06.3')
receipts=[task_contract.build_validation_receipt(c,e['id'],exit_code=0,context=ctx) for e in c['evidence'] if e['algorithm']=='shell-command']
ev=task_contract.evaluate(c,validation_receipts=receipts,validation_context=ctx);assert ev['pre_mutation']=='allow' and ev['pre_final']=='allow',ev
for name,value in [('task-contract.json',c),('validation-context.json',ctx),('validation-receipts.json',receipts),('task-contract-evaluation.json',ev)]:save(name,value)
validation=[{'carrier':e['path'],'status':'green','evidence_ids':[str((o/p).resolve()) for p in proofs[e['id']]]} for e in c['evidence'] if e['algorithm']=='shell-command']
for values in proofs.values():
 for p in values:assert (o/p).is_file(),p
dec=terminal_policy.build_decision(r,'P06.3',result='closed-green',validation=validation);batch=dict(start['batch_receipt']);batch['completed_prefix']=['P06.3']
journal.append(r,journal.new_entry(plan_id=r.name,item_id='P06.3',command='plan-run',actor='executor/codex/gpt-6.1-sol/high',result='closed-green',session_id=start['session_id'],run_id=start['run_id'],attempt=start['attempt'],batch_receipt=batch,decision=dec,note=f'P06.3 GREEN; product {sha}; CloudEvents codec and webhook wire. Acceptance and Grok 4.7 GREEN; review: self-check GREEN. Evidence: artifacts/P06.3-execution/completion.md.\nwrite-site: pre_mutation: allow · pre_final: allow'))
next_items=('P06.4',)
estimate=plan_continuity.WorkEstimate(route_attested=True,capabilities_attested=True,capabilities_sufficient=True,context_attested=False,context_health='unavailable',batch_receipt_valid=True,remaining_batch_digest=hashlib.sha256(json.dumps(next_items).encode()).hexdigest(),loaded_inputs_reusable=True,cold_start_cost=0,lifecycle='scope-complete',checkpoint='artifacts/P06.3-execution/completion.md')
cont=plan_continuity.reduce(estimate);carrier=command_render.PlanNextCarrier(kind='run-items',command_id='task:plan-run',plan_id=r.name,arguments=(r.name,*next_items),context_kind=cont.outcome,item_ids=next_items,continuity=cont)
for name,value in [('continuity-work-estimate.json',estimate.as_dict()),('continuity-decision.json',cont.as_dict()),('next-carrier.json',carrier.as_dict())]:save(name,value)
(r/'handoff.md').write_text(f'''# HANDOFF — 2026-10-03 — after P06.3

**Next:** run-items: task:plan-run {r.name} P06.4

| Parameter | Meaning |
|:--|:--|
| Batch | solo |
| Model class | implementation |
| Effort | high |
| Context | {cont.context} |

**Done:** P06.1/P06.2 and authorized solo P06.3 GREEN. P06.3 candidate {sha}; CloudEvents wire, strict codec/URI validation, data-only projectors and external webhook profile; root/package/PG/BC/API/Golden/gates GREEN; self-check and owner-requested Grok 4.7 (medium) GREEN. Exact evidence in artifacts/P06.3-execution/completion.md and validation-map.json.

**Remaining:** P06.4 delivery/egress then P06.5 review are recommendations outside this completed authorization. P06 phase remains open until final review and hosted CI gate. Current root remains reusable; lifecycle scope-complete.

**Open risks:** P06.4 retry/egress work, P06.5 independent phase review and final hosted CI remain. No skipped P06.3 acceptance. Historical untracked PHP design prototypes and unrelated owner CLAUDE/config remain; generated graph report remains uncommitted after validation.

**Sources of truth:** phases/P06/, decisions/D90/D92/D93; artifacts/P06.1-execution/completion.md, artifacts/P06.2-execution/completion.md, artifacts/P06.3-execution/completion.md; validation maps, source/review binding, Grok reports and journal.

**Workarounds/Deferred/Open questions:** Native Grok repository-tool access was refused by a compatibility hook; complete immutable source/diff packet review and final compact candidate review GREEN with no hook changes. Exact final git-archive lint avoids unrelated historical untracked PHP prototypes. Typed-doc and stale predecessor error-fixture repairs retained with original/red and final/green evidence.

```session-continuity-decision/v1
{json.dumps(cont.as_dict(),ensure_ascii=False)}
```
''')
print('P06.3 terminal persisted; finalize/lint/delivery remain')
