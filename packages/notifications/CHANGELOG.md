# Changelog

## Unreleased — Packman runtime

- Breaking: module declarations and schema registrations use Packman contracts directly; module lifecycle and schema execution are owned by Packman.
- Admit each digest and freeze its remaining bounded batch in one transaction; subsequent sweeps deliver every notification without duplicating an admitted batch.

## Unreleased — kernel audit repairs (2026-10-08)

- Recover concrete frozen digest requests through WakeupScheduler instead of direct handler calls; preserve durable admission/cancellation/lease/retry fencing and exclude terminal history from due selection.


## Unreleased — Laravel 13 platform (P09.9, D132)

- Require Laravel 13 and PHP 8.4+; support PHP 8.4–8.5. Laravel 12 support is removed. Testbench uses the Laravel 13 family; package versions and runtime contracts are unchanged.

## Unreleased — PHP 8.4 modernization (P09.4)

- Modernize maintained PHP for PHP 8.4 with final concrete classes, immutable value state and controlled named calls; preserve current public native parameter names and extension behavior.
- Remove unregistered Internal queue jobs: `DeliverDigest`.

## Unreleased — baseline v1

- Схема: набор(ы) `tenant`, baseline v1. Baseline заменяет невыпущенную upgrade-цепочку; новая установка использует финальную схему.

## Unreleased — digest wakeup without a wakeup package dependency (P07.5)

- Schema delivery now uses tagged `SchemaSetProvider` declarations and `packman:schema:migrate`; migration publishing is removed.

- Breaking (pre-1.0): removed the production `axiomasoft/corex-scheduler` requirement. The package keeps it as `require-dev` so the base API compare and the digest bridge tests can install the runner. An enabled digest tags `WakeupHandlerDefinition` and `DueWakeupSource` instead of writing `corex-scheduler.handlers`. `DigestSchedulerCheck` rejects an enabled digest when `WakeupScheduler` is not bound. Disabled digests still freeze a batch and never schedule.

## Unreleased — thin providers and registrars (P05.2)

- `NotificationsServiceProvider` delegates to `NotificationsBindingsRegistrar` and `NotificationsDigestRegistrar`; the digest wakeup adapter no longer uses a multi-statement closure.

## Unreleased — typed Options and runtime configuration checks (P05.1)

- Added internal `NotificationsOptions` and a `RuntimeConfigCheck`: in `production`, `corex-notifications.digest.accept_legacy_unstamped=true` stops the process unless that exact path is listed in `corex.acknowledged_dangerous_modes`.

## Unreleased — kernel package and API migration

- Breaking (PLAN3.P02–P04, pre-1.0): Delivery, digest and preference features expose recipient references, delivery outcomes and digest DTOs through declared ports; channel execution and persistence are Internal.
- Package installation uses the `axiomasoft` Composer vendor and PHP 8.4–8.5 / Laravel 12–13 constraints; `resources/contracts/php-api.json` records the explicitly attributed API and SPI. Old names have no compatibility aliases.

## Unreleased — PHP surface strict cutover

- Breaking (PLAN3.P04, pre-1.0): supported PHP symbols are declared individually with Api/Spi attributes. Concrete implementation types moved to Internal and typed ports/DTOs and error contracts replace predecessor surfaces as recorded in PLAN3.D63/D64; namespace metadata no longer grants compatibility.
- Generated `resources/contracts/php-api.json` records supported layers and signatures. Strict source/graph/BC checks enforce the declared surface; Internal has no external BC promise.

## Unreleased — typed error contract (P04.3)

- Added package owned error enums, definitions and safe coded exception descriptors.
- Error constructors require enum identities; existing error wire values are scoped.
- Contributions are validated and frozen at boot; persisted identities use validated lookup.

## [Unreleased]

- PHP API manifests now record direct inheritance, interfaces and traits separately and include only members declared by this package; external dependency members are omitted without changing the `php-api/1` schema.

### Changed

- PLAN3.D57: implementation classes moved to `<Module>\Internal\…`, public types to module categories
  (`Contracts|Data|Enums|Events|Exceptions`); see `docs/architecture/constitution.md` STR-003. Drain queues before
  upgrading; stored `*_type` values holding an old model FQCN no longer resolve (no aliases, PLAN3.D01).

### Added

- `notification/1`: durable tenant-local inbox intents and claim-epoch protected in-app delivery.
- Settings-backed recipient preferences, registered channel resolution, and recovery sweep support.
- Optional shared-wakeup digest admission with frozen run items, residual closed-window catch-up,
  and a digest-owned delivery foreign key.
