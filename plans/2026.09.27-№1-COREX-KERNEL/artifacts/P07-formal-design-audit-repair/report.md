# P07 local carrier/admission repair — GREEN — 2026-10-04

**Result: GREEN for the owner-requested local repair.** Task design gate returns `allowed=true`, `PLAN_DESIGN_AUDIT_WAIVED_BY_OWNER`, owner decision **D104**. No additional audit, model, reviewer or subagent was launched. This result records owner admission and successful repair verification; no formal `audit-green` event was created.

Remaining Critical / Major / Minor in the reported repair scope: **0 / 0 / 0**. The substantive design is not re-audited in this repair. The original audit-attention report and event 222 remain unchanged historical evidence.

## Owner authority and admission

The owner directed «исправляй все здесь до green! т.е. все что нашел и завершайся», then explicitly refused another audit: «аудит уже был, больше не надо. доделай свою задачу!!!». [Actual message capture](owner-direction.md) is the hash-bound source for [D104](../../decisions/D104-owner-no-repeat-design-audit.md). This supersedes the initial no-repair boundary and authorizes the explicit no-repeat-audit admission path; it does not authorize product execution or closing P06.

Installed Task `docs/reference/design-audit-waiver.md` and `plan_readiness.design_gate` support this owner-selected, accepted, fingerprint-bound decision. The only changed authored design input is the `plan.md` selector:

```diff
-**Design Audit Waiver:** D89
+**Design Audit Waiver:** D104
```

Original fingerprint: `830e2cb881f05da62be0f5b03dfb2dec2fdacab9fef449186a6418d8d9ca925f`.

New fingerprint: **`6c53c4206610981206cf9c23184718411e392ab54384ef8c5bf96350099780e3`**.

The new candidate was finalized **once** through the existing Task `plan_work.finalize_design(..., finish=True)` API. This was the authorized administrative repair, not a fictional Task launch or an independent review. The previous non-GREEN event for `830e2cb…` was neither overwritten nor upgraded. D89 remains historical and is no longer selected. [Waiver carrier](owner-waiver.json), [finish evidence](finish.json), [actual gate](design-gate.json).

## Correction of the bundle observation

The previous broad “33 stale bundles” observation was an overbroad admission inventory. The adopted Task [plan-layout-v2 reference]( /home/vostrikov/projects/packages/swissknifeman/packages/task/runtime/plan-protocol/references/plan-layout-v2.md) states that execution-sheet plans render the next runnable bundle, materialize it at execution start and defer later predecessor-dependent items until their inputs exist. Task finish also preserves closed-item snapshots because later phases can move the files they once read.

- **P07.1:** its current bundle was materialized through Task and passes the byte-fresh check. It is ready as a design input bundle; execution still waits for P06 acceptance and closure.
- **P07.2–P07.6:** declared execution-completion inputs do not exist yet because P07 has not executed. Their bundle production is deferred by contract. These are normal future deliverables, not design defects to “fix” with fabricated completion files.
- **Closed items:** historical bundles are preserved. The initial broad `bundle --all` attempt updated P00.1–P00.3/P01.1 before encountering a moved historical input. Those four changes were restored through Task's durable projection writer to the exact pre-audit bytes, verified against the original saved hashes. No historical spec or path was rewritten to make old inputs look current.

[Bundle results and classification](bundle-repair.json), [exact historical restoration](historical-restoration.json). This repairs the actionable current carrier and corrects the earlier report's interpretation without inventing missing producer evidence.

## Provenance and boundaries

The recorded D100–D103 repair thread was queried through the installed Task adapter. Full `capture_current` rejected an exact Task invocation because that thread contained direct repair instructions; [the refusal is preserved](author-provenance-lookup.json). The adapter's identity-validated metadata reader observed `gpt-6.1-sol/high` in its latest author-thread context. [Limited author metadata](author-thread-metadata.json) does not claim a Task launch or per-decision model history.

The current direct owner repair likewise records actual provider-thread metadata and explicitly marks Task invocation attestation unavailable, rather than reusing an old audit launch as a new design invocation. [Repair actor evidence](repair-route-evidence.json). No independent-model claim is used to obtain admission: D104 is the owner's explicit waiver.

The six author proofs and saved 143-check total were not rerun or relabelled as this root's independent verdict. No new content audit, design algorithms, P07 spec/dossier/example changes, product code, tests, scripts, manifests, baselines, migrations, Docker, application, PG, Redis/Memcached, hosted CI work, releases, commits or dependency updates were undertaken.

## Verification and Task carriers

Final verification passed:

- Task finish: all nine detailed phases, 36 items, supporting artifacts and all 29 execution-sheet groups are structurally complete.
- Design gate: `allowed=true`, `PLAN_DESIGN_AUDIT_WAIVED_BY_OWNER`, D104, exactly the new fingerprint.
- P07.1 bundle, all item states, status and decision views: fresh.
- Plan-lint: **0 errors**, one existing P04 orchestration/workflow warning. No warning was turned into another repair requirement.
- Task handoff recommendation receipt: built and verified. Its successor is gated P06 closure; no further audit is scheduled.
- HEAD remains `4137d2412497aa9348cf19322b26f1d074b5ad2c`; index unchanged. Protected source changes are only the explicit `plan.md` selector and four Task-generated decision navigation views (`decisions/_archive.md`, `_current.md`, `_index.md`, `open-questions.md`). Item-state bytes are unchanged. Product and unrelated dirty paths remain preserved.
- New journal rows are only owner-repair design finalization/bookkeeping. **No audit or product execution events** were added in this repair.
- P06 closure is absent and acceptance remains open. All P07.1–P07.6 states remain **Not started**. G2 remains 19 through P07.1 (23 service / 25 total), full `{}` only at P07.3.

The initial preservation checker omitted `open-questions.md` from its permitted generated decision-view targets and rejected that expected projection update. The checker classification was corrected after confirming the exact Task producer; the repository state needed no additional repair or test replay. [Verification](verification.json), [lint](plan-lint.json), [receipt](receipt.json), [continuity](continuity.json).

## Exact Task receipt continuation

**Next-step recommendation:** waiting: полное owner review P06 и hosted CI acceptance подтверждены по D73/D91/D94 → close: task:plan-close 2026.09.27-№1-COREX-KERNEL P06

P06 acceptance and product integration Validation remain outside this completed repair. Work stops here as requested.
