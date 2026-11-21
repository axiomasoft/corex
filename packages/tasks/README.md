# CoreX Tasks

Базовый модуль проектов и задач для DB-per-account приложений CoreX.

## Контракт

- lifecycle manifest: `CoreX\Tasks\TasksModule`, package type `packman-module`;
- восемь tenant-таблиц с префиксом `tsk_`: projects, stages, sprints, tasks,
  targets, watchers, time logs и timers;
- UUIDv7 генерируются через Laravel `HasUuids`;
- публичные write-границы: `CreateTaskCommand`, `MoveTaskToStageCommand`,
  `UpdateTaskCommand`, `CompleteTaskCommand`, а также планирование
  `TaskPlanning` (`createProject`, `createSprint`, `recordTime`);
- переносимые ссылки на внешние сущности передаются как `EntityReference`,
  без зависимости от моделей host-приложения;
- host обязан предоставить `TaskAccess`, `TaskMutationAccess` и
  `TaskPlanningAccess`; пакетные реализации по умолчанию запрещают все команды.
- `TaskPlanning` проверяет scope, переиспользует результат запроса в его
  рабочем пространстве и атомарно обновляет `spent_minutes` при `recordTime`.
- Порт `TaskOperationReceipts` принимает соединение владельца и
  `TaskOperationIdentity`. Внутри транзакции поиск блокирует найденную запись.
  Повтор требует того же типа операции, субъекта и digest. Конфликт откатывает
  команду. У завершения, операций аккаунта и операций рабочего пространства
  раздельные области ключей. Запись хранит исходный физический fence и типизированную JSON-ссылку
  на результат.

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

Миграции применяет `Packman\Modules\Contracts\Lifecycle\ModuleLifecycle`;
service provider их не загружает. Невыпущенная baseline-схема описывает новую
установку. Перенос данных из старой экспериментальной схемы требует отдельной миграции.

## Проверки

```bash
composer test -- --exclude-group=pg
PGSQL_HOST=127.0.0.1 PGSQL_PORT=5434 composer test -- --group=pg
composer analyse
composer lint:check
```

## Installation

Install with `composer require axiomasoft/corex-tasks` from the approved CoreX channel and register the module through Packman Modules. Prepare the tenant database with UUID primary keys in `users.id` and `wsp_workspaces.id` before enabling Tasks. CoreX Tenancy provides these tables in `axiomasoft/corex-tenancy:tenant`. A standalone host can supply the same columns through its own migrations. Enabling Tasks then applies the module’s owned schema. Bind the three task access ports before invoking writes. Completion and planning also require the host’s current work principal and a verified tenant writer.

## Завершение и чтение задачи

Команды и события возвращают скалярные `TaskView`, `StageView`, `ProjectView` и
`SprintView`; конкретные Eloquent-модели остаются внутри владельца. Для внешнего
чтения доступны `TaskRead`, `ProjectRead` и `SprintRead`.

Tasks объявляет событие `tsk.task/completed` через Packman. Для подключения
действия зарегистрируйте один `TaskCompletionCapture` с тегом
`TaskCompletionCapture::TAG`. Для Workflow эту роль выполняет отдельный пакет
`axiomasoft/corex-tasks-workflow`.

`CompleteTaskCommand` и переход в стадию с автоматическим статусом Done
проверяют текущий scope и блокируют строку задачи. Перед первым реальным
завершением contributor получает `TaskCompletionCandidate` и то же активное
соединение. `TaskCompletionAdmission` со статусом `created` или `existing`
подтверждает digest, ссылку на intent и версию определения. Tasks сохраняет
результат в одной транзакции с Done. Конфликт или ошибка откатывает обе записи.

Повторный вызов для Done сохраняет дату завершения. После переоткрытия задача
может снова перейти в Done с новой датой и существующим receipt. Ключ
завершения зависит от account ID, incarnation и Task ID. Когда contributor
отключён, завершение проходит без нового receipt. При переносе сохраняйте
исходный ключ завершения, digest и pinned intent/version.

`TaskOperationReceipt::originalFence` хранит deployment ID, binding ID,
generation, account incarnation и restore epoch на момент записи.
`TaskOperationReceipt::resultRef` представлен `JsonDocument`.

## Typed errors

Package error identities use backed enums and a boot compiled kernel registry.
Error constructors require `ErrorCode`; descriptors keep scoped string wire codes.
See the monorepo [error contract](../../docs/reference/errors.md) for contributions,
validated replay and safe mapping.

## Schema installation

Register the package provider, then run `php artisan packman:schema:migrate --scope=tenant --database=<tenant>`. Module sets are installed by `ModuleLifecycle::enable()`; template preparation includes them with `--modules`. Inspect the journal with `packman:schema:status`.

Схема: набор(ы) `tenant`, baseline v1. Baseline заменяет невыпущенную upgrade-цепочку; новая установка использует финальную схему.
