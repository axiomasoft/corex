# Changelog

## Unreleased — kernel package and API migration

- Breaking (PLAN3.P02–P04, pre-1.0): Access and planning features expose TaskAccess, TaskPlanningCommand and TaskOperationReceipts with PlanningSubject/PlanningToken/TaskOperationReceipt DTOs; task rows and planning persistence are Internal.
- Package installation uses the `axiomasoft` Composer vendor and PHP 8.4–8.5 / Laravel 12–13 constraints; `resources/contracts/php-api.json` records the explicitly attributed API and SPI. Old names have no compatibility aliases.

## Unreleased — PHP surface strict cutover

- Breaking (PLAN3.P04, pre-1.0): supported PHP symbols are declared individually with Api/Spi attributes. Concrete implementation types moved to Internal and typed ports/DTOs and error contracts replace predecessor surfaces as recorded in PLAN3.D63/D64; namespace metadata no longer grants compatibility.
- Generated `resources/contracts/php-api.json` records supported layers and signatures. Strict source/graph/BC checks enforce the declared surface; Internal has no external BC promise.

## Unreleased — typed error contract (P04.3)

- Added package owned error enums, definitions and safe coded exception descriptors.
- Error constructors require enum identities; existing error wire values are scoped.
- Contributions are validated and frozen at boot; persisted identities use validated lookup.

## Unreleased — P04.2

- Команды и события используют скалярные views; синхронный TaskCompletionCapture участвует в транзакции сохранения.


## Unreleased

### Changed

- PLAN3.D57: implementation classes moved to `<Module>\Internal\…`, public types to module categories
  (`Contracts|Data|Enums|Events|Exceptions`); see `docs/architecture/constitution.md` STR-003. Drain queues before
  upgrading; stored `*_type` values holding an old model FQCN no longer resolve (no aliases, PLAN3.D01).

- Added the public `TaskOperationReceipts` owner port and typed operation receipt/kind for transactional integrations; the SQL implementation stays internal.
- Добавили `TaskPlanningCommand`/`TaskPlanningAccess` и typed planning DTO для
  project/sprint/time-log с дедупликацией `corex_task_operations`.
- BREAKING: заменили legacy process/participant-схему на утверждённый UUIDv7-контракт из восьми `tsk_*` таблиц.
- Добавили lifecycle manifest `corex/tasks`; миграции выполняет CoreX module lifecycle, а не service provider.
- Добавили DTO-команды создания и перемещения задачи с явной tenant/workspace-проверкой и безопасным default-deny доступом.
- Добавили атомарные команды изменения и завершения задачи, after-commit события
  и отдельный default-deny контракт авторизации мутаций.
