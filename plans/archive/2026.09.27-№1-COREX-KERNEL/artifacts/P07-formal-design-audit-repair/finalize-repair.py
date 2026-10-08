"""One-shot owner-directed administrative repair; no reviewer or product execution."""
import hashlib
import json
import os
import subprocess
import sys
from pathlib import Path

TASK=Path('/home/vostrikov/projects/packages/swissknifeman/packages/task')
sys.path[:0]=[str(TASK/'lib'),str(TASK.parent.parent)]
from task import (command_render, journal, plan_bundle, plan_continuity,
                  plan_decisions, plan_delivery, plan_item_state, plan_layout,
                  plan_lint, plan_projection, plan_readiness, plan_status,
                  plan_work, route_evidence, route_evidence_codex)

root=Path('/home/vostrikov/projects/packages/corex')
p=root/'plans/2026.09.27-№1-COREX-KERNEL'
out=p/'artifacts/P07-formal-design-audit-repair'
fp=json.loads((out/'candidate-after-authored-change.json').read_text())['fingerprint']

def save(name,value):
    (out/name).write_text(json.dumps(value,ensure_ascii=False,indent=2)+'\n')

def git(*args):
    return subprocess.check_output(['git',*args],cwd=root)

# Direct owner repair is not a new Task audit/design invocation. Use the installed
# adapter's validated, identity-bound metadata reader, and record that distinction.
thread=os.environ['CODEX_THREAD_ID']
rollout=route_evidence_codex._thread_rollout(thread,route_evidence_codex._thread_state_root())
assert rollout is not None
context,_=route_evidence_codex._read_thread_state(rollout,thread)
assert Path(context['cwd']).resolve()==root
actual={'model':context['model'],'effort':context['effort'],'thread_id':thread,
        'source':'provider-thread-state','source_version':'codex-turn-context-v1',
        'scope':'actual non-Task owner-repair actor metadata',
        'task_invocation_verified':False,
        'task_capture_result':'ROUTE_LAUNCH_MISMATCH: current turn has owner repair instructions, not exact Task invocation'}
actor=route_evidence.canonical_actor(actual['model'],actual['effort'])
save('repair-route-evidence.json',actual)

next_exact='waiting: полное owner review P06 и hosted CI acceptance подтверждены по D73/D91/D94 → close: task:plan-close '+p.name+' P06'
estimate=plan_continuity.WorkEstimate(route_attested=False,capabilities_attested=True,
    capabilities_sufficient=True,context_attested=False,context_health='unavailable',
    batch_receipt_valid=False,remaining_batch_digest=fp,loaded_inputs_reusable=True,
    cold_start_cost=0,lifecycle='waiting-owner',checkpoint='artifacts/P07-formal-design-audit-repair/report.md')
continuity=plan_continuity.reduce(estimate)
carrier=command_render.parse_next_carrier(next_exact,context_kind='continue-root',continuity=continuity)
save('continuity.json',{'estimate':estimate.as_dict(),'decision':continuity.as_dict(),
                      'carrier':carrier.as_dict(),'scope':'repair ends here; P06 acceptance outside this repair'})

handoff=f'''# HANDOFF — 2026-10-04 — after P07

**Next:** {next_exact}

| Parameter | Meaning |
|:--|:--|
| Batch | n/a |
| Model | implementation |
| Thinking | low |
| Context | {continuity.context} |
| Суть | Локальный ремонт завершён; P06 acceptance и закрытие обязательны до P07 execution. |

{command_render.render_continuity_decision(continuity)}

**Native Codex command:**

```bash
# NOT BEFORE: полное owner review P06 и hosted CI acceptance подтверждены по D73/D91/D94
codex -C /home/vostrikov/projects/packages/corex -m gpt-6-sol -c 'model_reasoning_effort="low"' -a never -s danger-full-access '$ task:plan-close {p.name} P06'
```

**Done:** Owner-directed local carrier/admission repair complete. D104 captures the owner's exact no-repeat-audit instruction, selects a fingerprint-bound waiver and preserves historical audit-attention event 222. Authored design changes only plan.md waiver selector D89→D104; new candidate {fp}. Task P07.1 bundle materialized and fresh. Broad “33 stale bundles” admission inventory corrected: closed snapshots remain historical; P07.2–P07.6 are deferred until predecessor completion inputs exist. Existing six proofs/143 author checks and technical reviews remain historical evidence; they were not independently rerun in this carrier repair. No new audit/reviewer/model/subagent or product execution.

**Remaining:** Close P06 after full owner review and final hosted CI acceptance under D73/D91/D94. P06 acceptance stays open with no closure carrier; P07.1–P07.6 Not started. P07 execution still waits for P06 closure. The repeated whole-plan design audit is explicitly waived by owner D104 for this finished fingerprint; this is owner admission, not a new audit-green verdict.

**Sources of truth:** decisions/D104-owner-no-repeat-design-audit.md; artifacts/P07-formal-design-audit-repair/owner-direction.md, owner-waiver.json, finish.json, completion.json, report.md, receipt.json. Original artifacts/P07-formal-design-audit/report.md and findings/P07-formal-design-audit.md preserved; their broad bundle-admission observation is corrected by this repair report. P07 code contracts remain D94–D103, research/P07-design.md and artifacts/P07-design/examples.md.

**Open risks:** Full PostgreSQL/provider/Redis/Memcached/resident integration remains product acceptance. No content-design changes or additional audit are performed here. Exact real Roave constructor approval rows remain P07.1 execution deliverables. Task has updated its explicit --reviewer command surface; no new review is invoked.

**Workarounds/Deferred/Open questions:** Owner explicitly said “аудит уже был, больше не надо. доделай свою задачу!!!”; no further audit is scheduled. G2 remains 19 through P07.1 (23 service findings/25 total), full baseline {{}} only P07.3. Product/tests/scripts/manifests/baselines/dossier/examples/history and unrelated dirty changes are preserved. No commits, dependency updates, Docker/migrations or project DB access.
'''
plan_projection.write_or_check(p/'handoff.md',handoff,check=False)

# New owner-selected waiver changes an authored input. Finish exactly once for it.
finish=plan_work.finalize_design(p,finish=True,actor=actor,session_id=thread)
save('finish.json',finish)
gate=plan_readiness.design_gate(p)
save('design-gate.json',gate)
assert gate['allowed'] and gate['code']=='PLAN_DESIGN_AUDIT_WAIVED_BY_OWNER'
assert gate['owner_decision']=='D104' and gate['fingerprint']==fp

entry=journal.new_entry(plan_id=p.name,item_id='finish',command='plan-design',
    actor=actor,result='no-op',session_id=thread,fingerprint=fp,
    note='Owner repair complete: D104 explicit no-repeat-audit waiver admitted; first P07 bundle fresh, future/closed bundles correctly classified. P06 acceptance open; no P07 execution or audit-green.')
journal.append(p,entry)
plan_status.build_status(p)
plan_bundle.build(p,'P07.1')

# Verify repaired scope once, after final carrier publication.
plan_bundle.build(p,'P07.1',check=True)
plan_item_state.build_states(p,check=True)
plan_decisions.build_views(p,check=True)
plan_status.build_status(p,check=True)
diag=plan_lint.Diagnostics(); plan_lint.lint_plan(p,diag)
save('plan-lint.json',{'errors':diag.errors,'warnings':diag.warnings})
assert not diag.errors
receipt=plan_delivery.build_handoff_receipt(p,'finish')
plan_delivery.write_receipt(out/'receipt.json',receipt)
plan_delivery.verify_receipt(p,receipt)

before=json.loads((out/'protected-before.json').read_text())
candidate_before=json.loads((out/'candidate-before.json').read_text())
changed=[]
for name,old in before['paths'].items():
    path=root/name
    if path.is_symlink(): current=hashlib.sha256(os.readlink(path).encode()).hexdigest()
    elif path.is_file(): current=hashlib.sha256(path.read_bytes()).hexdigest()
    else: current=None
    if current!=old: changed.append(name)
allowed_exact={str((p/'plan.md').relative_to(root)),str((p/'open-questions.md').relative_to(root)),
    *[str(path.relative_to(root)) for path in (p/'decisions').glob('_*.md')]}
state_changes=[name for name in changed if name.endswith('.state.md')]
unexpected=[name for name in changed if name not in allowed_exact and name not in state_changes]
states={path.stem:path.read_text() for path in (p/'phases/P07').glob('*.state.md')}
rows=journal.read_authoritative_entries(p)
new_rows=rows[222:]
snapshot=plan_readiness.design_snapshot(p)
result={'head':git('rev-parse','HEAD').decode().strip(),
    'fingerprint_before':candidate_before['design']['fingerprint'],
    'fingerprint_after':snapshot['fingerprint'],
    'allowed_authored_change':'plan.md: **Design Audit Waiver:** D89 → D104',
    'changed_protected_paths':changed,'unexpected_protected_changes':unexpected,
    'state_projections_changed':state_changes,
    'index_unchanged':hashlib.sha256(git('diff','--cached','--binary')).hexdigest()==candidate_before['index_sha256'],
    'p07_all_not_started':len(states)==6 and all('⬜ Not started' in text for text in states.values()),
    'p06_closure_absent':not plan_layout.carrier_path(p,'phase-closure',phase_id='P06').exists(),
    'new_audit_events':[row for row in new_rows if row['command']=='plan-audit'],
    'new_product_events':[row for row in new_rows if row['command'] in ['plan-run','plan-exec']],
    'p07_1_bundle_fresh':True,'later_bundles':'deferred by predecessor contract',
    'status_states_decisions_fresh':True,'receipt_verified':True,
    'lint_errors':len(diag.errors),'lint_warnings':len(diag.warnings),
    'gate':gate,'formal_audit_green_created':False}
save('verification.json',result)
assert not unexpected and result['index_unchanged'] and result['head']==candidate_before['head']
assert result['p07_all_not_started'] and result['p06_closure_absent']
assert not result['new_audit_events'] and not result['new_product_events']
assert snapshot['fingerprint']==fp
print(json.dumps({'repair':'GREEN','gate':gate,'lint_errors':len(diag.errors),
                  'changed_protected_paths':changed,'next_exact':receipt['next_exact']},ensure_ascii=False))
