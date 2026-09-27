# Execution audit P1–P5 — 2026-09-22

## Status

Independent read-only execution audit of PLAN2. This document is evidence for remediation,
not a phase-closure carrier and not an `audit-admission/v2` result. No P1–P5 phase receives
GREEN from this report.

**Verdict:** RED; P6 is blocked until P5.18 is GREEN.

## Candidate observed

- repository HEAD at audit start: `9608b48`;
- worktree: 87 tracked paths changed, 67 untracked paths, 154 paths total;
- tracked diff: 87 files, 8721 insertions, 1604 deletions;
- almost all P1–P5 implementation is outside bounded committed checkpoints.

The worktree is owner data. Remediation must preserve it and must not use destructive reset or
checkout operations.

## Reproduced blocking findings

### EA01 — false GREEN on root integration

`journal.jsonl:108` closes P5.11 GREEN while recording root `composer test` failures as
pre-existing. They are not safely attributable to the baseline:

1. `composer package-graph-gate` reports 14 P5 Messaging symbols as undeclared public surface.
2. Root `composer test` executes `packages/tasks-workflow/tests/Pg/MaterializationPgTest.php`
   without its package-local `tests/Pest.php`, so `PgTestCase` is absent and five tests error on
   `resetPgSchema()`.
3. `composer test:packages` fails three notifications tests because `WakeupServiceProvider`
   resolves `Psr\Clock\ClockInterface`, while the isolated notifications composition does not
   load the only current binding in `CoreServiceProvider`.
4. Root `composer lint:check` finds three P5 files requiring formatting.

### EA02 — P5.12 scope and dependency violation

P5 declares `P5.8 → P5.12`. P5.12 explicitly excludes every `packages/core/src` change.
Nevertheless `journal.jsonl:110` records that P5.12 landed `SqlInbox` because P5.8 was absent,
then closes GREEN. P5.8 was closed later at line 113.

### EA03 — missing P5.6 terminal evidence

The only P5.6 journal event is `in-progress` at line 102. The generated state claims Done while
its projection header says `through journal_line: 0`. P5.6 must be resumed by its owning command,
revalidated, and terminated honestly before any successor.

### EA04 — configurable table names disagree with DDL

Runtime paths use `Config::outboxMessagesTable()` and `Config::inboxMessagesTable()`, but both new
migrations create literal default names. A configured non-default name therefore makes migration
and runtime address different tables. The same contradiction already exists in the frozen P5
design, so this is both a design and execution defect.

### EA05 — lifecycle carriers disagree

- `status.md` says P5 is 11/12 while all twelve listed rows are Done;
- P2.3–P2.5 and P3.4 generated states are stale against journal terminal events;
- `handoff.md` launches P6.1 although P5.6 is nonterminal and root gates are RED;
- current `plan-lint --baseline HEAD --gate all` reports 118 errors and 5 warnings;
- historical journal rows lack fields required by the current Task schema, and completed state
  projections lack current write-site evidence.

Historical append-only events must not be rewritten or embellished. Remediation uses current
Task writers for new events, regenerates owned projections, and records any irreducible legacy
debt explicitly.

## Checks reproduced

Green:

- `composer --working-dir=packages/core test` — 84 tests, 197 assertions;
- `composer --working-dir=packages/core test:pg` — 36 tests, 168 assertions;
- core `analyse` and package-local `lint:check`;
- root `composer analyse` and `composer analyse:packages`;
- naming, config-composition, table-ownership and anti-weakening gates;
- `git diff --check`.

Red:

- root `composer test`;
- `composer test:packages`;
- `composer test:arch`;
- `composer package-graph-gate`;
- root `composer lint:check`.

## Owning remediation

| Finding | Owner |
|:--|:--|
| EA03 | resume P5.6 first |
| tasks-workflow root PG harness | P5.13 |
| standalone wakeup clock composition | P5.14 |
| Messaging public/internal surface | P5.15 |
| configurable messaging DDL | P5.16, pending G-DDL-P5R |
| P5 formatting debt | P5.17 |
| integrated root/package qualification and projection reconciliation | P5.18 |

P6.1 is not runnable until all owners above are terminal GREEN and P5.18 proves the joined
acceptance surface.

## Model assessment boundary

Composer 2.5 produced substantial locally passing implementation, but this run demonstrates that
it must not own cross-package qualification or phase closure. Use it only for bounded, mechanical
items. Route cross-package/provider/public-contract work to Grok 4.7 or Sol, and route the final
joined qualification to Sol independently of the implementation sessions.
