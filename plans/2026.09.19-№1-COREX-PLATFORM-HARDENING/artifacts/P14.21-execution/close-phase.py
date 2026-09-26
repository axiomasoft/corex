from pathlib import Path
import hashlib, json
from task import journal, plan_closure, plan_gates, plan_status, plan_recovery, plan_continuity

p = Path('plans/2026.09.19-№1-COREX-PLATFORM-HARDENING')
out = p / 'artifacts/P14.21-execution'
s = json.loads(Path('build/P14.21/start.json').read_text())
candidate = plan_gates.phase_candidate_fingerprint(p, 'P14')
gate = plan_gates.phase_close_gate(p, 'P14', candidate_fingerprint=candidate['fingerprint'], candidate_snapshot=candidate['snapshot'])
assert gate['allow'], gate
prior = [e for e in journal.read_authoritative_entries(p) if e['item_id']=='P14' and e['result']=='closed-green']
if not prior:
    e = journal.new_entry(plan_id=p.name, item_id='P14', command='plan-close', actor=s['actor'],
        result='closed-green', session_id=s['session_id'], run_id=s['run_id'], attempt=s['attempt'],
        fingerprint=candidate['fingerprint'],
        decision={'signal_class': 'acceptance', 'scope_class': 'phase', 'action': 'phase-close-green',
                  'recovered': True, 'terminal': True, 'gate_code': gate['code']},
        note='P14 direct-close after all 21 GREEN items. Qualification covers full root/package/isolated PG, 16 threats, snapshots and repaired mutation gates. Live sends, production DDL/rotation and release remain outside authority.')
    journal.append(p, e)
plan_status.build_status(p)
closure = plan_closure.build_closure(p, 'P14')
next_bundle = plan_recovery.materialize_next_ready(p, 'P14.21')
assert next_bundle is not None and next_bundle.stem == 'P15.1', next_bundle
line = next(i for i,r in enumerate((p/'journal.jsonl').read_text().splitlines(),1)
            if json.loads(r)['item_id']=='P14.21' and json.loads(r)['result']=='closed-green')
estimate = plan_continuity.WorkEstimate(route_attested=True, capabilities_attested=True,
    capabilities_sufficient=True, context_attested=False, context_health='unavailable',
    batch_receipt_valid=True, remaining_batch_digest=hashlib.sha256(b'[]').hexdigest(),
    loaded_inputs_reusable=True, cold_start_cost=0, lifecycle='scope-complete',
    checkpoint=f'journal:P14.21:line{line};closure:{closure.relative_to(p)}')
continuity = plan_continuity.reduce(estimate)
(out / 'continuity.json').write_text(json.dumps({'estimate': estimate.as_dict(), 'decision': continuity.as_dict()}, ensure_ascii=False, indent=2)+'\n')
handoff = f'''# HANDOFF — 2026-09-26 — after P14.21

**Next:** exec-items: task:plan-exec {p.name} P15.1

| Параметр | Значение |
|:--|:--|
| Batch | solo |
| Model class | implementation |
| Model | gpt-6-sol |
| Thinking | medium |
| Native model | gpt-6-sol |
| Capabilities | — |
| Context | continue-root |
| Суть | P15.1: public modules FQCN migration and nine discovery consumers; requires its scoped G-API-P15 grant. |

```session-continuity-decision/v1
{json.dumps(continuity.as_dict(), ensure_ascii=False, sort_keys=True, separators=(',', ':'))}
```

**Done:** P14.21 и P14 GREEN. Documentation, 16-row matrix, actual commands/log hashes/JUnit; root 815 tests and PG 239; API 267, modules 83, transfer 8 plus PG 23; mutation 34 nonempty refusals. Missing canonical registry, HTTP cost projection and stale mutation carriers repaired within the admitted attempt.

**Remaining:** P15.1 — actual ready successor, bundle materialized. User authorized P14.21 only; scope-complete retains continue-root and recommends P15.1 without starting it.

**Sources of truth:** `phases/P14/P14.21.md` · `phases/P14/P14.21.state.md` · `{closure.relative_to(p)}` · `findings/P14-execution-validation.md` · `artifacts/P14.21-execution/commands.json` · `journal.jsonl`.

**Open risks:** API BC reports nine historical breaks against the old baseline under experimental policy (exit 0); this is not clean stable-release BC. Qualification covers the actual dirty checkout; unrelated predecessor changes remain unstaged and dependent own deltas are preserved as scoped patch artifacts. Bulk needs trusted host admission/field-policy bindings. Delivery stays off; no live sends, production migration, real secret rotation or publication was authorized.

**Workarounds/Deferred/Open questions:** No deferred required qualification check. Host rollout and stable-release BC remain their own future authority. P15 naming is outside this invocation.
'''
(p / 'handoff.md').write_text(handoff)
print(json.dumps({'closure': str(closure), 'next_bundle': str(next_bundle), 'continuity': continuity.as_dict()}, ensure_ascii=False))
