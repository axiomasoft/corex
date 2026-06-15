# corex/tasks

Модуль задач CoreX поверх `corex/core`: **процессы → стадии → задачи** и **проекты**. Зависит только от
ядра — ULID, мульти-тенантность (`TenantModel` + `TenantScope`), морф-карта через `EntityRegistry`.

## Состав

- **Модели**: `Process`, `Stage` (порядок, цвет, `is_final`), `Task` (приоритет, иерархия
  parent/children, полиморфный `taskable`, time-tracking: estimated/spent), `Project`; пивоты
  `TaskParticipant`/`ProjectParticipant`.
- **Actions** (граница use-case = `DB::transaction`): `CreateProcess`, `CreateTask` (на первой стадии
  процесса; throw, если стадий нет), `MoveTaskToStage` (финальная стадия → `archived_at`),
  `CreateProject`, `AddTaskParticipant`.
- **Events** (`ShouldDispatchAfterCommit`): `TaskCreated`, `TaskMovedToStage`, `ProjectCreated`.
- **Enums**: `TaskPriority`, `TaskParticipantRole`, `ProjectType`, `ProjectStatus`, `ProjectParticipantRole`.
- Сущности зарегистрированы в `EntityRegistry` (алиасы `tsk_process/stage/task/project`); `taskable`
  резолвится через морф-карту — задачу можно прикрепить к **любой** сущности (например, к сделке `corex/crm`).

## Пример

```php
$process = app(CreateProcess::class)->handle('Kanban', 'KANBAN');
// + стадии Stage::create([...]) с is_final на терминальной
$task = app(CreateTask::class)->handle($process, 'Write docs', creatorId: $user->id, priority: TaskPriority::High);
app(MoveTaskToStage::class)->handle($task, $doneStage); // is_final → задача архивируется
```

## Зависимости

Только `corex/core` (+ `illuminate/*`). **Без связи с `corex/crm`**: `Project.company_id` — plain ulid,
`Task.taskable` — полиморф без жёсткой ссылки. Авторизация — забота потребителя / `corex/auth`.

## Тесты

Модуль вшит в **общий root-harness монорепо** (`tests/Tasks/`). Из корня `packages/corex`:

```bash
composer test      # Pest + Orchestra Testbench (sqlite :memory:)
composer analyse   # PHPStan (larastan) level 6
composer lint      # Pint
```

> Вне MVP (follow-up): системные шаблоны процессов / partial-unique индексы; GIN на jsonb (sqlite-харнес).
