# Changelog

## [Unreleased]

### Changed

- PLAN3.D57: implementation classes moved to `<Module>\Internal\…`, public types to module categories
  (`Contracts|Data|Enums|Events|Exceptions`); see `docs/architecture/constitution.md` STR-003. Drain queues before
  upgrading; stored `*_type` values holding an old model FQCN no longer resolve (no aliases, PLAN3.D01).

### Added

- `notification/1`: durable tenant-local inbox intents and claim-epoch protected in-app delivery.
- Settings-backed recipient preferences, registered channel resolution, and recovery sweep support.
- Optional shared-wakeup digest admission with frozen run items, residual closed-window catch-up,
  and a digest-owned delivery foreign key.
