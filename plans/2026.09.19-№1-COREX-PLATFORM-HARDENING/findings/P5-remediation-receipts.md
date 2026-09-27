# P5 remediation receipts

## P5.13 — Root PG harness tasks-workflow

- Change: `MaterializationPgTest.php` explicitly uses `PgTestCase` and group `pg`; the package-local `tests/Pest.php` declaration remains intact.
- `cd packages/tasks-workflow && ../../vendor/bin/pest --exclude-group=pg` — GREEN, 7 tests / 25 assertions.
- `cd packages/tasks-workflow && ../../vendor/bin/pest --group=pg` — GREEN, 6 tests / 43 assertions.
- `composer test` — expected foreign RED: 402/403 pass; only `PackageGraphTest` fails on the P5.15 undeclared-surface owner. No `MaterializationPgTest` executes in the root SQLite lane and the former five `resetPgSchema()` errors are absent.
- Corrective run after P5.18: `packages/tasks-workflow/tests/Pest.php` now assigns only the
  `pg` group to `Pg/`; `MaterializationPgTest.php` remains the sole `PgTestCase` binding.
- Exact package command `composer --working-dir=packages/tasks-workflow test` — GREEN,
  13 tests / 68 assertions, including both PostgreSQL files; the duplicate binding is absent.

## P5.14 — standalone clock composition

- Change: `WakeupServiceProvider` binds `CoreX\Wakeup\Internal\SystemClock` only when `ClockInterface` is unbound. A host instance, including `FrozenClock`, is left identical. Notifications tests do not load `CoreServiceProvider`.
- `composer --working-dir=packages/wakeup test` — GREEN, 10 tests / 43 assertions.
- `composer --working-dir=packages/wakeup test:pg` — GREEN, 1 test / 1 assertion. Postgres `corex_test` on `127.0.0.1:5434` was accepting connections.
- `composer --working-dir=packages/notifications test` — GREEN, 14 tests / 51 assertions.
- `composer --working-dir=packages/wakeup analyse` and `composer --working-dir=packages/notifications analyse` — GREEN, 0 errors. `composer analyse:packages` — exit 0.
- `vendor/bin/pint --test` on the four changed PHP files — passed.
- `composer package-graph-gate` — foreign RED owned by P5.15: 14 pre-existing `Messaging` R4b undeclared-surface violations. No wakeup clock symbol is in that list.
- `composer test:packages` — notifications passed inside the loop; the loop then aborted in `tasks-workflow` on a foreign Pest double-bind of `PgTestCase` (`MaterializationPgTest.php` plus `tests/Pest.php`). That failure is outside this item. Direct wakeup `test` is GREEN.

## P5.15 — Messaging public/internal surface

- Change: `packages/core/composer.json` adds only `CoreX\Messaging\Contracts` and `CoreX\Messaging\Data` to `extra.corex.public-api`. Existing entries stay in their previous order. `CoreX\Messaging\Internal` stays non-public. No package-graph exception and no `CoreX\Messaging` wildcard.
- Commands `PruneOutboxMessages`, `RelayOutbox`, and `ShowDeliveryHealth` carry a class-level `@internal` docblock. They are implementation details, not supported API.
- Gate repair: `docblockBeforeIndex` stopped at `final`, so a class-level docblock above `final class` was invisible to R4b. The lookup now skips `abstract`, `final`, and `readonly`, and still stops on any other token, so it cannot borrow a previous symbol's docblock.
- `composer package-graph-gate` — GREEN, `package-graph-gate: OK`, zero R4/R4b violations.
- Negative witness: temporary `packages/core/src/Messaging/SurfaceProbe.php` (`final class CoreX\Messaging\SurfaceProbe`, no `@internal`) made `php scripts/package-graph-gate.php` exit 1 with `[R4b]` for `CoreX\Messaging\SurfaceProbe`. The probe was removed with `unlink` in `finally`. The following gate run exited 0, and the probe file was absent.
- `composer test:arch` — GREEN, 35 tests / 95 assertions.
- `composer --working-dir=packages/core test` — GREEN, 84 tests / 197 assertions.
- `composer --working-dir=packages/core analyse` — GREEN, 0 errors.
- `composer --working-dir=packages/core lint:check` — GREEN.

## P5.16 — configurable messaging table identity

- Authority: D115 permits this bounded local source/test remediation in the current owner session; no DDL was applied to a non-test database and no Swiss Knife Man runtime code was changed.
- Change: both messaging migrations resolve table names through `CoreX\Support\Config`; each raw outbox partial-index target is wrapped by the active connection grammar. Default table and index semantics are unchanged.
- Regression witness: `MessagingSchemaTest` migrates `custom_outbox_messages` and `custom_inbox_messages`, proves both custom tables and all five configured-table indexes exist, proves both default tables are absent, and restores both config values in `finally`.
- Test DB preflight: Docker service `pgsql` healthy; `pg_isready` accepts connections; target `corex_test` satisfies the `_test` isolation guard.
- Focused `vendor/bin/pest packages/core/tests/Pg/MessagingSchemaTest.php --group=pg` — GREEN, 5 tests / 30 assertions.
- `composer --working-dir=packages/core test:pg` — GREEN, 37 tests / 180 assertions.
- `composer --working-dir=packages/core test` — GREEN, 84 tests / 197 assertions.
- `composer --working-dir=packages/core analyse` — GREEN, 0 errors.
- `composer --working-dir=packages/core lint:check` — GREEN.
- `php scripts/table-ownership-gate.php` — GREEN, 104 tables / 101 catalog rows.

## P5.17 — Close reproduced P5 formatting debt

- Scope: Pint only on the three EA01 files; no semantic edit. Owner authorized continuation
  after an Auto-route observe (`routing-rejected` then resume in the same session).
- Pre: `vendor/bin/pint --test` on the three files — already GREEN (hashes matched the
  P5.17 bundle digests). Forced `vendor/bin/pint` on the same paths — no-op.
- `git diff --word-diff=porcelain` / byte compare vs pre-snapshot — IDENTICAL for all three
  paths (formatting-only classification: empty diff).
- Post: root `composer lint:check` — GREEN (`vendor/bin/pint --test` passed).
- Focused PG: `composer --working-dir=packages/core test:pg -- --filter='DeliveryGuarantees|OutboxAppend'`
  — GREEN, 7 tests / 41 assertions. Target DB `corex_test` on `127.0.0.1:5434` accepting.
- Focused SQLite: `composer --working-dir=packages/core test -- --filter='OutboxAppend|DeliveryHealth|ShowDelivery'`
  — GREEN, 5 tests / 15 assertions.
- Product bytes unchanged this item; deliverable is the lint/diff receipt proving the root
  gate is green for the declared trio.

## P5.18 — Joined qualification

- Verdict: RED; owning continuation P5.13. P6.1 remains blocked.
- PostgreSQL preflight: Docker `pgsql` healthy, host `pg_isready` GREEN, target
  `corex_test` passed the `_test` guard and exists.
- Core: SQLite 84/197, PostgreSQL 37/180, analysis and lint GREEN.
- Root: lint, analysis, Arch 35/95, package graph and SQLite 403/1284 GREEN.
- `composer test:packages` stopped in `packages/tasks-workflow` after 431 tests / 1446
  assertions from preceding packages: Pest rejected duplicate `PgTestCase` bindings before
  executing tasks-workflow tests. Direct `composer --working-dir=packages/tasks-workflow test`
  reproduced exit 255.
- Exact evidence and skipped fail-fast carriers:
  `findings/P5-qualification-2026-09-22.md`.
- Current Task 0.20.5 plan lint (`--baseline HEAD --gate new`) reports FAIL with 49 errors
  and 5 warnings. Classification: 46 historical terminal items lack current write-site text;
  the 5 clarification warnings are historical; 3 diagnostics are specific to the honest RED
  projection (generated Blocked state has no hand-authored ESCALATION block, and lint rejects
  both the typed `waiting-owner` context and its non-runnable Next form). The terminal delivery
  reducer nevertheless validates the same typed `waiting-owner` carrier. `HEAD` predates the
  entire uncommitted P1–P5 worktree, so its reported 43-new count is not a P5.18 delta.
- No sealed item spec, generated state or historical journal line was hand-edited to suppress
  those lifecycle diagnostics.
- Product writes and commit: none.

### Corrective continuation — attempt 2

- P5.13 is GREEN and owns the completed harness correction; P5.18 made no product repair.
- Unchanged GREEN attempt-1 carriers were reused and the full P5.18 matrix was not replayed.
- PostgreSQL preflight remained GREEN for isolated target `corex_test`.
- `composer --working-dir=packages/tasks-workflow test` — GREEN, 13 tests / 68 assertions.
- `composer test:packages` — GREEN, exit 0 through all 21 package entries; the embedded
  `tasks-workflow` lane also reports 13 tests / 68 assertions.
- Exact evidence and the preserved original RED diagnosis:
  `findings/P5-qualification-2026-09-22.md`.
- Product writes and commit: none; remaining writes are Task lifecycle reconciliation only.

### Execution handoff after owner stop

- The owner stopped the P5.18 session after validation was GREEN because lifecycle inspection had
  become disproportionate. The session was shut down cleanly; no validation command was left running.
- Durable run remains `0a68eb430fe74bff0ca327c734ea5883ffb2c55c58992f78cdcb91dbac452b1a`
  in session `01a0c9a7-9738-7951-b0fa-ec325f815b9b`.
- Continuation is **lifecycle-only**: no tests, lint, analysis, package matrix, product repair, or
  additional model/session launch. Resume the existing Sol/xhigh session, persist terminal GREEN
  evidence, reconcile P5.18, emit the P6.1 handoff, and stop.
- After P5.18 closes, P6.1 is assigned to Codex `gpt-5.6-sol/high` under the declared
  `implementation/high` fallback route.
