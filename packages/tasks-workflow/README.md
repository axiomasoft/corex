# axiomasoft/corex-tasks-workflow

Recurrence-to-task materialization for CoreX. Connects `axiomasoft/corex-recurrence` expansion with
`axiomasoft/corex-tasks` `CreateTaskCommand`, durable operation receipts, optional `axiomasoft/corex-wakeup` scheduling,
and optional `axiomasoft/corex-workflow` completion capture.

## Public API

- `TaskRecurrenceCommand::materialize(DataScope, SeriesReference, MaterializationWindow, WriteToken): MaterializationReceipt`

## Configuration

See `config/corex-task-recurrence.php` for wakeup and completion-capture toggles.

## Installation

Install with `composer require axiomasoft/corex-tasks-workflow` through the configured channel. Supply the underlying tasks and recurrence packages, trusted tenant scope and task authorization. Install the package-owned series-mapping schema through the owning module lifecycle before materialization.

## Failure semantics

Materialization is bounded and uses durable task operation receipts to deduplicate effects. Invalid context, stale write tokens and unsupported recurrence data are rejected rather than bypassed. Wakeup and completion capture are disabled by default; enabling them requires their declared bindings and versioned registrations.
