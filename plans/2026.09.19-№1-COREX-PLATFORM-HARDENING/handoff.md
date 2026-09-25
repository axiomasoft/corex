# HANDOFF — 2026-09-25 — after P12.7

**Next:** run-items: task:plan-run 2026.09.19-№1-COREX-PLATFORM-HARDENING P12.8

## ⚠ LAUNCH

| Параметр | Значение |
|:--|:--|
| Batch | E12d |
| Model | frontier |
| Thinking | high |
| Context | continue-root |
| Суть | P12.8 — final P12 ratchets and proof. |

```session-continuity-decision/v1
{"evidence": ["batch:E12d", "loaded-inputs:not-reusable", "cold-start-cost:0", "lifecycle:scope-complete", "checkpoint:journal:P12.7:closed-green"], "outcome": "continue-root", "reason": "authorized-scope-complete", "runnable": true, "schema_version": "session-continuity-decision/v1"}
```

**Done:** E12d GREEN. P12.6 stamps every audit record with staff, grant, target
and reason. P12.7 rejects nested starts, carries reason through session and
mirrors, and clears auth after a failed target login. Impersonation and
write-guard tests 31/79, tenancy analyse 0, pint passed.

**Remaining:** P12.8 and P13–P17 remain after their dependencies.

**Sources of truth:** `phases/P12/P12.7.md` · `findings/P12-receipts.md` ·
`journal.jsonl`.

**Open risks:** HTTP emission of category/retryable/correlation stays in P13.
Root `composer.json` already had unrelated edits, so the OpenTelemetry dev
dependency is installed in the worktree and declared on `packages/core`.

**Workarounds/Deferred/Open questions:**
- workarounds: —
- deferred: —
- open_questions: —
