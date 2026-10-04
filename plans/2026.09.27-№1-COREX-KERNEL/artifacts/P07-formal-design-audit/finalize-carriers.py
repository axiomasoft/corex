"""One-shot carrier writer for this invocation; retained as evidence, do not rerun."""
import hashlib
import json
import os
import subprocess
import sys
from pathlib import Path

TASK = Path('/home/vostrikov/projects/packages/swissknifeman/packages/task')
sys.path[:0] = [str(TASK / 'lib'), str(TASK.parent.parent)]
from task import (command_render, journal, plan_continuity, plan_delivery,
                  plan_layout, plan_lint, plan_projection, plan_readiness,
                  plan_status, route_evidence)

root = Path('/home/vostrikov/projects/packages/corex')
plan = root / 'plans/2026.09.27-№1-COREX-KERNEL'
out = plan / 'artifacts/P07-formal-design-audit'
expected = '830e2cb881f05da62be0f5b03dfb2dec2fdacab9fef449186a6418d8d9ca925f'

def save(name, value):
    (out / name).write_text(json.dumps(value, ensure_ascii=False, indent=2) + '\n')

def git(*args):
    return subprocess.check_output(['git', *args], cwd=root)

assert plan_readiness.design_snapshot(plan)['fingerprint'] == expected
assert git('rev-parse', 'HEAD').decode().strip() == '4137d2412497aa9348cf19322b26f1d074b5ad2c'
actual = json.loads((out / 'route-evidence.json').read_text())
assert actual['model'] == 'gpt-6.1-sol' and actual['effort'] == 'xhigh'
assert actual['model_class'] == 'frontier' and actual['source'] == 'provider-thread-state'
actor = route_evidence.canonical_actor(actual['model'], actual['effort'])
run_id = journal.derive_run_id(plan_id=plan.name, item_id='design', attempt=1,
                             session_id=actual['thread_id'])
decision = {
    'signal_class': 'mandatory-evidence-missing',
    'scope_class': 'whole-plan-design-audit-admission',
    'action': 'return-owning-continuation',
    'reason_code': 'INDEPENDENT_AUDITOR_MODEL_UNPROVEN',
    'evidence_ids': ['artifacts/P07-formal-design-audit/route-evidence.json',
                     'decisions/D24-rolling-backlog.md',
                     'decisions/D98-p07-design-audit-remediation.md',
                     'decisions/D99-p07-g2-scan-correction.md',
                     'artifacts/P07-design-remediation/repair-provenance.json'],
    'recovered': False, 'terminal': False,
    'owning_continuation': 'Verify distinct auditor/author model provenance before admitting task:plan-audit design; P06 acceptance remains required before P07 execution.',
}
entry = journal.new_entry(
    plan_id=plan.name, item_id='design', command='plan-audit', actor=actor,
    result='audit-attention', session_id=actual['thread_id'], run_id=run_id,
    fingerprint=expected, friction=('block-review',), decision=decision,
    note='Admission preflight blocked: current gpt-6.1-sol/xhigh matches D98/D99 author model; D100-D103 selector unavailable. No substantive audit/proofs or GREEN. See artifacts/P07-formal-design-audit/report.md.')
assert not any(r.get('run_id') == run_id for r in journal.read_authoritative_entries(plan))
path = journal.append(plan, entry)
line = len(path.read_text().splitlines())
save('audit-journal-event.json', {'path':str(path.relative_to(plan)), 'line':line, 'entry':entry})

condition = 'подтверждена модель аудитора, отличная от авторов P07 (D24), и её provider provenance frontier/xhigh'
next_exact = f'waiting: {condition} → audit-design: task:plan-audit {plan.name} design'
estimate = plan_continuity.WorkEstimate(
    route_attested=True, capabilities_attested=True, capabilities_sufficient=False,
    context_attested=False, context_health='unavailable', batch_receipt_valid=False,
    remaining_batch_digest=expected, loaded_inputs_reusable=False, cold_start_cost=0,
    lifecycle='waiting-owner', checkpoint='artifacts/P07-formal-design-audit/report.md')
continuity = plan_continuity.reduce(estimate)
carrier = command_render.parse_next_carrier(next_exact, context_kind='continue-root',
                                            continuity=continuity)
save('continuity.json', {'estimate':estimate.as_dict(), 'decision':continuity.as_dict(),
                         'carrier':carrier.as_dict(),
                         'note':'No product workset admitted; batch_receipt_valid=false. Owner must supply distinct-model provenance; no permission question or successor launch.'})
handoff = f'''# HANDOFF — 2026-10-04 — after P07

**Next:** {next_exact}

| Parameter | Meaning |
|:--|:--|
| Batch | n/a |
| Model | frontier |
| Thinking | xhigh |
| Context | {continuity.context} |
| Суть | Доказать независимость модели по D24 перед формальным аудитом; P06 acceptance до исполнения P07. |

{command_render.render_continuity_decision(continuity)}

**Native Codex command (conditional recommendation only):**

```bash
# NOT BEFORE: {condition}; gpt-6-astra допустима только при подтверждённом отличии от авторов P07.
codex -C /home/vostrikov/projects/packages/corex -m gpt-6-astra -c 'model_reasoning_effort="xhigh"' -a never -s danger-full-access '$ task:plan-audit {plan.name} design'
```

**Done:** Один admission preflight запрошенного formal design audit завершён ATTENTION без GREEN. Фактический маршрут текущего запуска gpt-6.1-sol/frontier/xhigh подтверждён Task provider-thread-state. D98/D99 имеют того же model selector; точный selector D100–D103 недоступен. Независимость модели по D24 не доказана. HEAD и whole-plan fingerprint совпали с ожидаемыми. Supporting artifacts и 29 execution-sheet rows структурно проверены; содержательный аудит и шесть proofs не запускались. Авторские 143 проверки не перепроверены этим запуском. Ремонт дизайна и продукт не выполнялись.

**Remaining:** Formal independent audit текущего 830e2cb881f05da62be0f5b03dfb2dec2fdacab9fef449186a6418d8d9ca925f не состоялся из-за admission blocker. Подтверждённый другой model selector и provenance — owning continuation этого блокера; текущая сессия ничего не запускает. Даже будущий GREEN не разрешает P07 до закрытия P06 по D94. P06 acceptance остаётся открытой, closure отсутствует; P07.1–P07.6 Not started. Старый waiver D89 stale.

**Sources of truth:** artifacts/P07-formal-design-audit/report.md, route-evidence.json, structural-preflight.json, receipt.json; findings/P07-formal-design-audit.md; decisions/D24-rolling-backlog.md, D98-p07-design-audit-remediation.md, D99-p07-g2-scan-correction.md; artifacts/P07-design-remediation/repair-provenance.json, completion.json, finish-final.json. Предыдущий design repair D100–D103 сохранён.

**Open risks:** Содержательные P07 контракты, Validation и 143 standalone checks здесь не оценены. Нет PG/provider/Redis/Memcached или product suites. Preflight freshness выявил 33 устаревших bundles из 36, включая P07.1–P07.6; это generated-carrier observation, не заявленный дефект дизайна. Bundles сохранены без ремонта; их детерминированный owner — Task plan-views bundle. Status/state/decision views до запуска свежие.

**Workarounds/Deferred/Open questions:** Другие модели/субагенты/Grok и successor не запускались. Product, specs, decisions, examples, manifests, baselines и существующие dirty изменения сохранены. G2: 19 через P07.1; 23 service findings/25 total; полный baseline {{}} только P07.3 по D99.
'''
plan_projection.write_or_check(plan/'handoff.md', handoff, check=False)
plan_status.build_status(plan)
receipt = plan_delivery.build_handoff_receipt(plan, 'design')
plan_delivery.write_receipt(out/'receipt.json', receipt)

# Final checks after the only authorized service-carrier mutation.
diag = plan_lint.Diagnostics()
model = plan_lint.lint_plan(plan, diag)
save('plan-lint.json', {'errors':diag.errors, 'warnings':diag.warnings,
                        'purpose':'single plan-lint invocation after audit carrier finalization'})
plan_status.build_status(plan, check=True)
plan_delivery.verify_receipt(plan, receipt)
snapshot = plan_readiness.design_snapshot(plan)
gate = plan_readiness.design_gate(plan)
save('design-gate-after.json', gate)
before = json.loads((out/'protected-before.json').read_text())
changed = []
for name, old in before['protected_paths'].items():
    p = root/name
    if p.is_symlink(): now={'kind':'symlink','sha256':hashlib.sha256(os.readlink(p).encode()).hexdigest()}
    elif p.is_file(): now={'kind':'file','sha256':hashlib.sha256(p.read_bytes()).hexdigest()}
    else: now={'kind':'absent'}
    if now != old: changed.append(name)
old_names=set(before['protected_paths'])
current_names=set(git('ls-files','-z').decode().split('\0')) | set(git('ls-files','--others','--exclude-standard','-z').decode().split('\0'))
allowed=set(before['allowed_carrier_updates'])
new=[n for n in current_names if n and n not in old_names and n not in allowed
     and not n.startswith(str(out.relative_to(root))+'/')
     and n != str((plan/'findings/P07-formal-design-audit.md').relative_to(root))]
states={p.name:'⬜ Not started' in p.read_text() for p in (plan/'phases/P07').glob('*.state.md')}
result={'fingerprint_before':expected, 'fingerprint_after':snapshot['fingerprint'],
        'head':git('rev-parse','HEAD').decode().strip(),
        'index_unchanged':hashlib.sha256(git('diff','--cached','--binary')).hexdigest()==before['index_sha256'],
        'changed_protected_paths':changed,'unexpected_new_paths':sorted(new),
        'p07_states_unstarted':states,
        'p06_closure_absent':not plan_layout.carrier_path(plan,'phase-closure',phase_id='P06').exists(),
        'status_fresh':True,'receipt_verified':True,'lint_errors':len(diag.errors),
        'journal_result':entry['result'],'formal_green':False,
        'substantive_audit_completed':False,'proofs_executed':0}
save('final-verification.json', result)
assert result['fingerprint_after']==expected and result['head']==before['head']
assert not changed and not new and result['index_unchanged']
assert all(states.values()) and result['p06_closure_absent']
assert not diag.errors
print(json.dumps({'result':result,'next_exact':receipt['next_exact'],
                  'lint_warnings':diag.warnings},ensure_ascii=False))
