# corex/tasks-workflow

Recurrence-to-task materialization for CoreX. Connects `corex/recurrence` expansion with
`corex/tasks` `CreateTaskCommand`, durable operation receipts, optional `corex/wakeup` scheduling,
and optional `corex/workflow` completion capture.

## Public API

- `TaskRecurrenceCommand::materialize(DataScope, SeriesReference, MaterializationWindow, WriteToken): MaterializationReceipt`

## Configuration

See `config/corex-task-recurrence.php` for wakeup and completion-capture toggles.
