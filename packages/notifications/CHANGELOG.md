# Changelog

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
