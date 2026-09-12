# CoreX Tasks

Базовый модуль проектов и задач для DB-per-account приложений CoreX.

## Контракт

- lifecycle manifest: `CoreX\Tasks\TasksModule`, package type `corex-module`;
- восемь tenant-таблиц с префиксом `tsk_`: projects, stages, sprints, tasks,
  targets, watchers, time logs и timers;
- UUIDv7 генерируются через `HasConfiguredId` при стандартной конфигурации
  CoreX;
- публичные write-границы: `CreateTaskCommand`, `MoveTaskToStageCommand`,
  `UpdateTaskCommand` и `CompleteTaskCommand`;
- переносимые ссылки на внешние сущности передаются как `EntityReference`,
  без зависимости от моделей host-приложения;
- host обязан предоставить `TaskAccess` и `TaskMutationAccess`; пакетные
  реализации по умолчанию запрещают все команды.

Команды проверяют активность модуля в текущем tenant context, работают через
текущее соединение модели и открывают вложенную транзакцию. Поэтому standalone
вызов атомарен, а вызов внутри внешней tenant-транзакции участвует в её
commit/rollback.

```php
$task = app(\CoreX\Tasks\Contracts\CreateTaskCommand::class)->handle(
    new \CoreX\Tasks\Data\TaskDraft(
        workspaceId: $workspaceId,
        title: 'Подготовить предложение',
        actor: $actor,
        projectId: $projectId,
        stageId: $stageId,
    ),
);
```

Миграции применяет lifecycle `corex/modules`; service provider их не загружает.
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
