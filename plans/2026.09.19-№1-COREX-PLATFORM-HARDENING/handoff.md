# HANDOFF — 2026-09-25 — after P12.5

**Next:** run-items: task:plan-run 2026.09.19-№1-COREX-PLATFORM-HARDENING P12.6

## ⚠ LAUNCH

| Параметр | Значение |
|:--|:--|
| Batch | E12d |
| Model | frontier |
| Thinking | high |
| Context | continue-root |
| Суть | P12.6 — impersonation provenance on core audit entries. P12.7 bundle still names the pre-P9 guard path. |

```session-continuity-decision/v1
{"evidence": ["batch:E12d", "loaded-inputs:not-reusable", "cold-start-cost:0", "lifecycle:scope-complete", "checkpoint:journal:P12.5:closed-green"], "outcome": "continue-root", "reason": "authorized-scope-complete", "runnable": true, "schema_version": "session-continuity-decision/v1"}
```

**Done:** E12c GREEN. P12.4 added the operation lifecycle and a redacting PSR-3
adapter. P12.5 added the metric allow-list, a null trace bridge, outbox carrier
injection and an optional OpenTelemetry adapter pinned to schema 1.32.0. Core
pest 139/387, zone 8/33, delivery-health pg 7/27, analyse 0, pint passed.

**Remaining:** P12.6–P12.8 and P13–P17 remain after their dependencies.

**Sources of truth:** `phases/P12/P12.5.md` · `findings/P12-receipts.md` ·
`journal.jsonl`.

**Open risks:** HTTP emission of category/retryable/correlation stays in P13.
Root `composer.json` already had unrelated edits, so the OpenTelemetry dev
dependency is installed in the worktree and declared on `packages/core`.

**Workarounds/Deferred/Open questions:**
- workarounds: —
- deferred: —
- open_questions: —
