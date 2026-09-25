# HANDOFF — 2026-09-25 — after P12.1

**Next:** exec-items: task:plan-exec 2026.09.19-№1-COREX-PLATFORM-HARDENING P12.2 P12.3

## ⚠ LAUNCH

| Параметр | Значение |
|:--|:--|
| Batch | E12b |
| Model | implementation |
| Thinking | medium |
| Context | continue-root |
| Суть | P12.2–P12.3 — API projection and attributes proof on the P12.1 descriptor. |

```session-continuity-decision/v1
{"evidence": ["batch:46768219a59cc421a140fdf87079cf75abfdfa5867bfcd8110cf92eae803f2e1", "loaded-inputs:not-reusable", "cold-start-cost:0", "lifecycle:scope-complete", "checkpoint:journal:P12.1:closed-green"], "outcome": "continue-root", "reason": "authorized-scope-complete", "runnable": true, "schema_version": "session-continuity-decision/v1"}
```

**Done:** P12.1 GREEN. Core owns `ErrorCategory`, `UnavailabilityReason`, immutable
`ErrorDescriptor` and `DescribesError`. Compatibility and the result/exception table
are in `docs/architecture/api-policy.md`. The four new files are Foundation by exact
path. Core lint, analyse and tests passed; root `composer test:arch` passed, including
an R-Z0 plant of `Data/UnclassifiedError.php`.

**Remaining:** P12.2 and P12.3 are the next ready batch. P12.4, P12.6 and the rest of
P12–P17 remain after their dependencies.

**Sources of truth:** `phases/P12/P12.1.md` · `docs/architecture/api-policy.md` ·
`docs/architecture/core-zones.md` · `journal.jsonl`.

**Open risks:** Detail values are bounded at 4096 JSON bytes. HTTP mapping stays in P12.2.

**Workarounds/Deferred/Open questions:**
- workarounds: —
- deferred: —
- open_questions: —
