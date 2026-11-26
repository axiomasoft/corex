# CoreX Tasks

The Tasks module manages projects and tasks for applications that store each account in its own database.

## Contract

- `CoreX\Tasks\TasksModule` is a `packman-module` with eight tenant tables for projects, stages, sprints, tasks, targets, watchers, time logs and timers. Tables use the `tsk_` prefix. Laravel's `HasUuids` generates UUIDv7 identifiers.
- `CreateTaskCommand`, `MoveTaskToStageCommand`, `UpdateTaskCommand` and `CompleteTaskCommand` own task writes. `TaskPlanning` exposes `createProject`, `createSprint` and `recordTime`. External entities are represented by `EntityReference`.
- The host supplies `TaskAccess`, `TaskMutationAccess` and `TaskPlanningAccess`. All three deny access by default.
- Planning checks the current scope, reuses prior results within that workspace and updates `spent_minutes` atomically. `TaskOperationReceipts` uses the owner's connection and locks a matching `TaskOperationIdentity` in the transaction. A retry must match the operation kind, subject and digest. A conflict rolls the command back. Completion, account and workspace operations have separate key scopes. A receipt keeps the original physical fence and a typed JSON result reference.

Commands check the module in the current tenant context and use the model's current connection. A standalone call is atomic. A nested call participates in the host transaction's commit or rollback.

```php
$task = app(\CoreX\Tasks\Task\Contracts\CreateTaskCommand::class)->handle(
    new \CoreX\Tasks\Task\Data\TaskDraft(
        workspaceId: $workspaceId,
        title: 'Prepare a proposal',
        actor: $actor,
        projectId: $projectId,
        stageId: $stageId,
    ),
);
```

Packman's `ModuleLifecycle` applies the module's migrations. Moving data from an older experimental schema requires an explicit migration.

## Checks

```bash
composer test -- --exclude-group=pg
PGSQL_HOST=127.0.0.1 PGSQL_PORT=5434 composer test -- --group=pg
composer analyse
composer lint:check
```

## Installation

Install with `composer require axiomasoft/corex-tasks` from the approved CoreX channel and register the module through Packman Modules. Prepare the tenant database with UUID primary keys in `users.id` and `wsp_workspaces.id` before enabling Tasks. CoreX Tenancy provides these tables in `axiomasoft/corex-tenancy:tenant`. A standalone host can supply the same columns through its own migrations. Enabling Tasks then applies the module’s owned schema. Bind the three task access ports before invoking writes. Completion and planning also require the host’s current work principal and a verified tenant writer.

## Completing and reading tasks

Commands and events expose scalar `TaskView`, `StageView`, `ProjectView` and `SprintView` values. Consumers read through `TaskRead`, `ProjectRead` and `SprintRead`. Eloquent models belong to the package.

Tasks declares the `tsk.task/completed` event through Packman. Register one `TaskCompletionCapture` with `TaskCompletionCapture::TAG` to contribute an action. The optional `axiomasoft/corex-tasks-workflow` package supplies the Workflow integration.

`CompleteTaskCommand` and a move to a stage that automatically sets Done check the current scope and lock the task. Before the first completion the contributor receives a `TaskCompletionCandidate` and the same active connection. A `created` or `existing` `TaskCompletionAdmission` verifies the digest, intent reference and definition version. Tasks commits the result with the status change. Conflicts or failures roll both writes back.

Repeating a completion keeps the original date. A reopened task may complete with a new date and reuse its receipt. The completion key includes account ID, incarnation and task ID. With no contributor, completion needs no new receipt. Preserve the original key, digest and pinned intent/version when moving data.

`TaskOperationReceipt::originalFence` records deployment ID, binding ID, generation, account incarnation and restore epoch at write time. `TaskOperationReceipt::resultRef` is a `JsonDocument`.

## Typed errors

Package error identities use backed enums and a boot compiled kernel registry.
Error constructors require `ErrorCode`; descriptors keep scoped string wire codes.
See the monorepo [error contract](../../docs/reference/errors.md) for contributions,
validated replay and safe mapping.

## Schema installation

Register the package provider, then run `php artisan packman:schema:migrate --scope=tenant --database=<tenant>`. Module sets are installed by `ModuleLifecycle::enable()`; template preparation includes them with `--modules`. Inspect the journal with `packman:schema:status`.

Fresh installations use `axiomasoft/corex-tasks:tenant` v2.
