# axiomasoft/corex-tasks-workflow

Recurrence-to-task materialization for CoreX. Connects `axiomasoft/corex-recurrence` expansion with
`axiomasoft/corex-tasks` `CreateTaskCommand`, durable operation receipts, optional scheduling through the kernel `WakeupScheduler` contract (no package dependency on `axiomasoft/corex-wakeup`),
and optional `axiomasoft/corex-workflow` completion capture.

## Public API

- `TaskRecurrenceCommand::materialize(DataScope, SeriesReference, MaterializationWindow, WriteToken): MaterializationReceipt`

## Configuration

See `config/corex-tasks-workflow.php` for wakeup and completion-capture toggles. `corex-tasks-workflow.wakeup.accept_legacy_unstamped=true` is a dangerous mode: production starts only when `corex.acknowledged_dangerous_modes` lists that exact path.

## Installation

Install with `composer require axiomasoft/corex-tasks-workflow` through the configured channel. Supply the underlying tasks and recurrence packages, trusted tenant scope and task authorization. Install the package-owned series-mapping schema through the owning module lifecycle before materialization.

## Failure semantics

Materialization is bounded and uses durable task operation receipts to deduplicate effects. Invalid context, stale write tokens and unsupported recurrence data are rejected rather than bypassed. Wakeup and completion capture are disabled by default. Enabling wakeup requires a
bound `WakeupScheduler`; the package registers its scoped handler and immutable
`WakeupHandlerDefinition` through the kernel tag using its own handler options.

Fresh operation keys continue a saved partial cursor. Each task is committed before
its recurrence checkpoint advances; conflict or unknown leaves the remaining tail
for reconciliation. Changed windows/revisions/runtime bindings restart traversal,
while SQL occurrence identity deduplicates existing tasks. Restore-epoch mismatch
is rejected.

Scheduling follows the domain commit. A request key includes revision, definition,
window, restore epoch and continuation checkpoint. Identical operation replay retries
scheduling with the same identity without advancing tasks again; a new window or
partial checkpoint gets its own request. If scheduling throws after commit, retry the
same operation key to recover the missing request. Schedule conflicts are surfaced
as materialization conflicts. Wakeups continue the encoded window; callers explicitly
choose future windows.

## Typed errors

Package error identities use backed enums and a boot compiled kernel registry.
Error constructors require `ErrorCode`; descriptors keep scoped string wire codes.
See the monorepo [error contract](../../docs/reference/errors.md) for contributions,
validated replay and safe mapping.

## Schema installation

Register the package provider, then run `php artisan corex:schema:migrate --scope=tenant --database=<tenant>`. Module sets are installed by `ModuleLifecycle::enable()`; template preparation includes them with `--modules`. Inspect the journal with `corex:schema:status`.

Схема: набор(ы) `tenant`, baseline v1. Baseline заменяет невыпущенную upgrade-цепочку; новая установка использует финальную схему.
