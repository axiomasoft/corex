# CoreX Tasks

Базовый модуль проектов и задач для DB-per-account приложений CoreX.

## Контракт

- lifecycle manifest: `CoreX\Tasks\TasksModule`, package type `corex-module`;
- восемь tenant-таблиц с префиксом `tsk_`: projects, stages, sprints, tasks,
  targets, watchers, time logs и timers;
- UUIDv7 генерируются через Laravel `HasUuids`;
- публичные write-границы: `CreateTaskCommand`, `MoveTaskToStageCommand`,
  `UpdateTaskCommand`, `CompleteTaskCommand`, а также планирование
  `TaskPlanningCommand` (`createProject`, `createSprint`, `recordTime`);
- переносимые ссылки на внешние сущности передаются как `EntityReference`,
  без зависимости от моделей host-приложения;
- host обязан предоставить `TaskAccess`, `TaskMutationAccess` и
  `TaskPlanningAccess`; пакетные реализации по умолчанию запрещают все команды.
- `TaskPlanningCommand` дедуплицирует запросы через `corex_task_operations`,
  проверяет tenant/workspace-контекст и атомарно обновляет `spent_minutes` при
  `recordTime`.
- Публичный `TaskOperationReceipts` даёт интеграциям read/record операции в
  транзакции вызывающей стороны; только `axiomasoft/corex-tasks` знает физическую таблицу.
  Повтор ключа вызывает ошибку уникальности: клиент читает сохранённый receipt
  и сверяет payload hash перед признанием повтора.

Команды проверяют активность модуля в текущем tenant context, работают через
текущее соединение модели и открывают вложенную транзакцию. Поэтому standalone
вызов атомарен, а вызов внутри внешней tenant-транзакции участвует в её
commit/rollback.

```php
$task = app(\CoreX\Tasks\Task\Contracts\CreateTaskCommand::class)->handle(
    new \CoreX\Tasks\Task\Data\TaskDraft(
        workspaceId: $workspaceId,
        title: 'Подготовить предложение',
        actor: $actor,
        projectId: $projectId,
        stageId: $stageId,
    ),
);
```

Миграции применяет lifecycle `axiomasoft/corex`; service provider их не загружает.
Alignment migration автоматически заменяет только пустую legacy-схему. При
наличии legacy-строк она останавливается до DDL и требует отдельного
согласованного переноса данных.

## Проверки

```bash
composer test -- --exclude-group=pg
PGSQL_HOST=127.0.0.1 PGSQL_PORT=5434 composer test -- --group=pg
composer analyse
composer lint:check
```

## Compatibility evidence

The factual compatibility passport, its local proof class, and explicit limits
are in [PACKAGE.md](PACKAGE.md). It does not extend Composer constraints or
constitute a publication promise.

## Installation

Install with `composer require axiomasoft/corex-tasks` from the approved CoreX channel and register the module through `axiomasoft/corex`. Enable it for the trusted tenant so module lifecycle applies its owned schema. Bind the three task access ports before invoking writes; their default implementations deny commands.

## Завершение и чтение задачи

Команды и события возвращают скалярные `TaskView`, `StageView`, `ProjectView` и
`SprintView`; конкретные Eloquent-модели остаются внутри владельца. Для внешнего
чтения доступны `TaskRead`, `ProjectRead` и `SprintRead`.

Интеграция завершения реализует `TaskCompletionCapture` и получает
`TaskCompletionCandidate` через тег `corex.tasks.completion-captures`. Вызов
синхронный, до сохранения, внутри транзакции команды: ошибка capture откатывает
изменение статуса. Tasks Workflow проверяет реальный переход в Done, сохраняет
receipt без повторов и пропускает отключённую либо отсутствующую интеграцию.

`TaskOperationReceipt::resultRef` содержит `JsonDocument`; JSON-представление
результата восстанавливает `JsonDocuments::map()`.

## Typed errors

Package error identities use backed enums and a boot compiled kernel registry.
Error constructors require `ErrorCode`; descriptors keep scoped string wire codes.
See the monorepo [error contract](../../docs/reference/errors.md) for contributions,
validated replay and safe mapping.

## Schema installation

Register the package provider, then run `php artisan corex:schema:migrate --scope=tenant --database=<tenant>`. Module sets are installed by `ModuleLifecycle::enable()`; template preparation includes them with `--modules`. Inspect the journal with `corex:schema:status`.
