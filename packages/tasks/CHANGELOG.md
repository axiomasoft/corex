# Changelog

## Unreleased

- Добавили `TaskPlanningCommand`/`TaskPlanningAccess` и typed planning DTO для
  project/sprint/time-log с дедупликацией `corex_task_operations`.
- BREAKING: заменили legacy process/participant-схему на утверждённый UUIDv7-контракт из восьми `tsk_*` таблиц.
- Добавили lifecycle manifest `corex/tasks`; миграции выполняет CoreX module lifecycle, а не service provider.
- Добавили DTO-команды создания и перемещения задачи с явной tenant/workspace-проверкой и безопасным default-deny доступом.
- Добавили атомарные команды изменения и завершения задачи, after-commit события
  и отдельный default-deny контракт авторизации мутаций.
