# Changelog

## Unreleased — baseline v1

- Схема: набор(ы) `tenant`, baseline v1. Baseline заменяет невыпущенную upgrade-цепочку; новая установка использует финальную схему.

## Unreleased — scheduling contracts moved to the kernel (P07.5)

- Schema delivery now uses tagged `SchemaSetProvider` declarations and `corex:schema:migrate`; migration publishing is removed.

- Breaking (pre-1.0, PLAN3.D01/D96): the ten scheduling contracts and data types now live in `CoreX\Scheduling`. This package implements them and still owns `DueWakeupSweeper` and `WakeupScheduleHint`. Handler definitions come from `corex-wakeup.handlers` and the `corex.scheduling.wakeup-handlers` tag; a duplicate kind is `InvalidConfiguration`. Due sources use `corex.scheduling.due-sources`.

## Unreleased — thin providers and registrars (P05.2)

- `WakeupServiceProvider` delegates to `WakeupBindingsRegistrar`; bindings are unchanged.

## Unreleased — typed Options and runtime configuration checks (P05.1)

- Added internal `WakeupOptions` and a `RuntimeConfigCheck`. Breaking (pre-1.0, PLAN3.D01): the scalar `kind => class` handler form is rejected with an invalid-configuration error; configure `handler` and `version`. In `production`, `accept_legacy_unstamped=true` of any handler stops the process unless `corex-wakeup.handlers.<kind>.accept_legacy_unstamped` is acknowledged.

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
