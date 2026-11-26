# Changelog

## CoreX 2.0 — Durable effects

- Keep completion contributors unresolved during application boot and validate their runtime type inside the trusted completion scope.
- Breaking: rename the planning entry point from `TaskPlanningCommand` to `TaskPlanning`.
- Breaking: `TaskCompletionCapture::capture` takes a trusted candidate and the verified `ConnectionInterface`, and returns a typed `TaskCompletionAdmission`.
- Publish `tsk.task/completed` from the Tasks module. Capture its first eligible Done transition through one optional contributor. The two completion commands atomically persist Done and the confirmed intent receipt, and reuse the receipt after reopening.
- Breaking: `TaskOperationReceipts` uses `TaskOperationIdentity` and `TaskOperationReceipt`. The owner schema separates workspace-scoped planning and materialization keys and preserves the original writer fence. Transfer historical receipts through the offline import path.

## Unreleased — Packman governance

- Eloquent relation adapters expose covariant Model contracts rather than private model classes.
- Adopt Packman API/SPI attributes and shared configuration/error contracts directly, with explicit Internal classification and no compatibility aliases. Declare package ownership in `extra.packman` and the monorepo manifest.
- Describe module schema ownership through Packman `WithSchema` / `OwnsSchema`; the existing provider remains the registration boundary.
- Delegate provider composition to focused registrars and keep service providers within 80 lines.


## Unreleased — Packman runtime

- Breaking: module declarations and schema registrations use Packman contracts directly; module lifecycle and schema execution are owned by Packman.

## Unreleased — Laravel 13 platform (P09.9, D132)

- Require Laravel 13 and PHP 8.4+; support PHP 8.4–8.5. Laravel 12 support is removed. Testbench uses the Laravel 13 family; package versions and runtime contracts are unchanged.

## Unreleased — PHP 8.4 modernization (P09.4)

- Modernize maintained PHP for PHP 8.4 with final concrete classes, immutable value state and controlled named calls; preserve current public native parameter names and extension behavior.
- Refresh the PHP API metadata for equivalent readonly/type/promotion declarations (D130).

## Unreleased — baseline v1

- The initial `tenant` baseline replaced the unreleased migration chain for fresh installations.

## Unreleased — deterministic schema (P08.2)

- Generate model identifiers with Laravel HasUuids (UUIDv7); schema keys use literal UUID columns.

## Unreleased — ports instead of facades, helpers and static state (P05.3)

- Schema delivery now uses tagged `SchemaSetProvider` declarations and `packman:schema:migrate`; migration publishing is removed.

- Planning services take `ConnectionResolverInterface` and `ClockInterface` through constructors instead of facades and helpers. `Planning\Laravel\PlanningCommand` accepts an optional trailing `TaskOperationReceipts` (resolved from the container when absent).

## Unreleased — thin providers and registrars (P05.2)

- The task-saving listener is a named method instead of a multi-statement closure.

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

- Commands and events expose scalar views. Synchronous `TaskCompletionCapture` participates in the save transaction.


## Unreleased

- PHP API manifests now record direct inheritance, interfaces and traits separately and include only members declared by this package; external dependency members are omitted without changing the `php-api/1` schema.

### Changed

- PLAN3.D57: implementation classes moved to `<Module>\Internal\…`, public types to module categories
  (`Contracts|Data|Enums|Events|Exceptions`); see `docs/architecture/constitution.md` STR-003. Drain queues before
  upgrading; stored `*_type` values holding an old model FQCN no longer resolve (no aliases, PLAN3.D01).

- Added the public `TaskOperationReceipts` owner port and typed operation receipt/kind for transactional integrations; the SQL implementation stays internal.
- Added `TaskPlanningCommand`, `TaskPlanningAccess` and typed project, sprint and time-log inputs with deduplication in `corex_task_operations`.
- Breaking schema change: eight UUIDv7 `tsk_*` tables replace the legacy process and participant tables.
- Registered the `corex/tasks` lifecycle manifest. The module lifecycle applies its migrations.
- Task creation and moves use explicit tenant and workspace checks and deny writes by default.
- Atomic update and completion commands publish after-commit events and use a dedicated mutation authorization contract that denies access by default.
