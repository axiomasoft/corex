# P4/P5 execution remediation design

This document is the normative implementation source for P5.13–P5.18. Evidence and reproduced
failures are in `findings/execution-audit-P1-P5-2026-09-22.md`.

## 1. Recovery order

1. Resume P5.6 and produce a fresh terminal result using its existing specification.
2. P5.13–P5.17 may then run when their declared inputs are present; their product Files do not
   overlap except for receipts and CHANGELOG.
3. P5.18 runs last and may not repair product code. A red check returns the exact owning item.
4. P6.1 remains blocked until P5.18 is GREEN.

No item rewrites historical journal lines or generated state/status files by hand. Current Task
writers append new events; `plan-views.py` regenerates projections.

## 2. P5.13 — root PG harness

Root Pest configuration does not load package-local `packages/tasks-workflow/tests/Pest.php`.
Restore an explicit file-level
`uses(PgTestCase::class)->group('pg');` in `MaterializationPgTest.php`. Keep the package-local
group declaration as package-suite configuration. This makes both entry points correct without
teaching the root runner to discover every nested Pest bootstrap.

Acceptance requires the package SQLite lane to exclude the file, the package PG lane to run it,
and the root SQLite lane not to execute its `beforeEach`.

## 3. P5.14 — standalone clock composition

`corex/wakeup` consumes `ClockInterface`; therefore its provider must be resolvable when loaded
without `CoreServiceProvider`. Add a package-internal PSR-20 system clock and bind it only when the
host has not already supplied `ClockInterface`. An application binding remains authoritative.
Do not import `CoreX\Support\Time\SystemClock` across the package boundary and do not force
notifications tests to load the whole core provider merely to conceal the composition defect.

Witnesses:

- wakeup alone resolves scheduler and sweeper;
- a pre-bound `FrozenClock` is preserved;
- notifications isolated package tests pass.

## 4. P5.15 — Messaging surface classification

Classify the new namespace deliberately:

- `CoreX\Messaging\Contracts` and `CoreX\Messaging\Data` are declared public API roots in
  `packages/core/composer.json`;
- console command classes are implementation details and receive explicit `@internal` class
  docblocks rather than being promoted to public API;
- `CoreX\Messaging\Internal` remains non-public;
- no package-graph exception or broad `CoreX\Messaging` public wildcard is allowed.

The package graph must report zero new R4/R4b violations.

## 5. P5.16 — configurable table identity

D115 authorizes this item only as bounded local source/test remediation because it changes the two
new migration carriers after P5.4 terminal evidence. Applying DDL outside `corex_test` remains out
of scope.

Both migrations obtain table names from `Config::outboxMessagesTable()` and
`Config::inboxMessagesTable()`. Every raw PostgreSQL index statement derives its qualified
identifier from the same validated table name; do not concatenate unchecked arbitrary config into
SQL. Follow existing configurable core migration precedents. Add a PG witness that overrides both
names, runs the migrations, proves the custom tables and indexes exist, and proves the default
tables were not created.

If safe identifier validation is absent for these accessors, add it at the configuration boundary
or use Laravel schema grammar wrapping; never quote by hand.

## 6. P5.17 — formatting-only repair

Apply Pint only to the three reproduced P5 files. No behavioral edit, rename, API change or test
expectation change is allowed. `git diff --word-diff` must demonstrate formatting-only changes.

## 7. P5.18 — joined qualification

P5.18 is independent verification and performs no product repair. Required sequence:

1. confirm the test PostgreSQL target is isolated and ends in `_test`;
2. run core SQLite and PG suites;
3. run root lint, analysis, architecture tests, package graph and full root tests;
4. run all package tests and package analysis;
5. run naming, config-composition, table-ownership and anti-weakening gates;
6. run `git diff --check`;
7. regenerate item state, status, decisions and the next eligible bundle with Task tooling;
8. run plan lint, recording historical errors separately from errors introduced by remediation.

Any red product check returns the named owner among P5.13–P5.17. Historical append-only journal
schema debt is not repaired by rewriting old lines. P5.18 records exact counts and prevents any
new schema or write-site debt. Phase closure is allowed only if the active Task runtime accepts the
resulting carriers; otherwise the outcome is nonterminal `waiting-owner` with the exact protocol
incompatibility.

## 8. Checkpoint discipline

Do not add more product work to the current 154-path worktree before P5.6 recovery. Each
remediation item must leave a bounded diff and record its path set. A clean checkpoint after the
joined qualification is required before P6; the executor must not create it unless commit authority
is explicit in its invocation.
