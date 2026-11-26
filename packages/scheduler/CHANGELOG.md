# Changelog

## Unreleased — Packman governance

- Adopt Packman API/SPI attributes and shared configuration/error contracts directly, with explicit Internal classification and no compatibility aliases. Declare package ownership in `extra.packman` and the monorepo manifest.
- Describe module schema ownership through Packman `WithSchema` / `OwnsSchema`; the existing provider remains the registration boundary.


## Unreleased — package boundaries

- Breaking before release: Rename the package to `axiomasoft/corex-scheduler`, namespace to `CoreX\Scheduler`, provider to `SchedulerServiceProvider`, config to `corex-scheduler`, and sweep command to `corex:scheduler:sweep`. Kernel scheduling contracts keep their wakeup vocabulary for one-shot durable requests.

## Unreleased — Packman runtime

- Breaking: module declarations and schema registrations use Packman contracts directly; module lifecycle and schema execution are owned by Packman.

## Unreleased — last scheduling contracts in the kernel (audit 2026-10-08)

- `DueWakeupSweeper` and `WakeupScheduleHint` now live in `CoreX\Scheduling\Contracts` (hint tag: `WakeupScheduleHint::TAG`, value unchanged). The `CoreX\Scheduler\Contracts` interfaces are deprecated aliases that extend them and are removed in 2.0; existing implementers and type hints keep working.

## Unreleased — Laravel 13 platform (P09.9, D132)

- Require Laravel 13 and PHP 8.4+; support PHP 8.4–8.5. Laravel 12 support is removed. Testbench uses the Laravel 13 family; package versions and runtime contracts are unchanged.

## Unreleased — PHP 8.4 modernization (P09.4)

- Modernize maintained PHP for PHP 8.4 with final concrete classes, immutable value state and controlled named calls; preserve current public native parameter names and extension behavior.
- Refresh the PHP API metadata for equivalent readonly/type/promotion declarations (D130).

## Unreleased — baseline v1

- The initial `tenant` baseline replaced the unreleased migration chain for fresh installations.

## Unreleased — scheduling contracts moved to the kernel (P07.5)

- Schema delivery now uses tagged `SchemaSetProvider` declarations and `packman:schema:migrate`; migration publishing is removed.

- Breaking (pre-1.0, PLAN3.D01/D96): the ten scheduling contracts and data types now live in `CoreX\Scheduling`. This package implements them and still owns `DueWakeupSweeper` and `WakeupScheduleHint`. Handler definitions come from `corex-scheduler.handlers` and the `corex.scheduling.wakeup-handlers` tag; a duplicate kind is `InvalidConfiguration`. Due sources use `corex.scheduling.due-sources`.

## Unreleased — thin providers and registrars (P05.2)

- `SchedulerServiceProvider` delegates to `WakeupBindingsRegistrar`; bindings are unchanged.

## Unreleased — typed Options and runtime configuration checks (P05.1)

- Added internal `SchedulerOptions` and a `RuntimeConfigCheck`. Breaking (pre-1.0, PLAN3.D01): the scalar `kind => class` handler form is rejected with an invalid-configuration error; configure `handler` and `version`. In `production`, `accept_legacy_unstamped=true` of any handler stops the process unless `corex-scheduler.handlers.<kind>.accept_legacy_unstamped` is acknowledged.

## Unreleased — kernel package and API migration

- Breaking (PLAN3.P02–P04, pre-1.0): WakeupScheduler and due-work ports exchange WakeupRequest, WakeupDelivery and schedule/cancel/sweep result DTOs; WakeupHandlerDefinition declares registrations and durable storage remains Internal.
- Package installation uses the `axiomasoft` Composer vendor and PHP 8.4–8.5 / Laravel 12–13 constraints; `resources/contracts/php-api.json` records the explicitly attributed API and SPI. Old names have no compatibility aliases.

## Unreleased — PHP surface strict cutover

- Breaking (PLAN3.P04, pre-1.0): supported PHP symbols are declared individually with Api/Spi attributes. Concrete implementation types moved to Internal and typed ports/DTOs and error contracts replace predecessor surfaces as recorded in PLAN3.D63/D64; namespace metadata no longer grants compatibility.
- Generated `resources/contracts/php-api.json` records supported layers and signatures. Strict source/graph/BC checks enforce the declared surface; Internal has no external BC promise.

## [Unreleased]

- PHP API manifests now record direct inheritance, interfaces and traits separately and include only members declared by this package; external dependency members are omitted without changing the `php-api/1` schema.

### Changed

- PLAN3.D57: implementation classes moved to `<Module>\Internal\…`, public types to module categories
  (`Contracts|Data|Enums|Events|Exceptions`); see `docs/architecture/constitution.md` STR-003. Drain queues before
  upgrading; stored `*_type` values holding an old model FQCN no longer resolve (no aliases, PLAN3.D01).

### Added

- `wakeup/1`: tenant-local durable request scheduling, idempotency, cancellation, leases and
  transactional local sweep handlers.
