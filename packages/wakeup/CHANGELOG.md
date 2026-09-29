# Changelog

## [Unreleased]

### Changed

- PLAN3.D57: implementation classes moved to `<Module>\Internal\…`, public types to module categories
  (`Contracts|Data|Enums|Events|Exceptions`); see `docs/architecture/constitution.md` STR-003. Drain queues before
  upgrading; stored `*_type` values holding an old model FQCN no longer resolve (no aliases, PLAN3.D01).

### Added

- `wakeup/1`: tenant-local durable request scheduling, idempotency, cancellation, leases and
  transactional local sweep handlers.
