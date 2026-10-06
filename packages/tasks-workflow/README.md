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

Materialization is bounded and uses durable task operation receipts to deduplicate effects. Invalid context, stale write tokens and unsupported recurrence data are rejected rather than bypassed. Wakeup and completion capture are disabled by default; enabling them requires their declared bindings and versioned registrations.

## Typed errors

Package error identities use backed enums and a boot compiled kernel registry.
Error constructors require `ErrorCode`; descriptors keep scoped string wire codes.
See the monorepo [error contract](../../docs/reference/errors.md) for contributions,
validated replay and safe mapping.

## Schema installation

Register the package provider, then run `php artisan corex:schema:migrate --scope=tenant --database=<tenant>`. Module sets are installed by `ModuleLifecycle::enable()`; template preparation includes them with `--modules`. Inspect the journal with `corex:schema:status`.

Схема: набор(ы) `tenant`, baseline v1. Baseline заменяет невыпущенную upgrade-цепочку; новая установка использует финальную схему.
