# Changelog

## Unreleased

### Changed

- PLAN3.D57: implementation classes moved to `<Module>\Internal\…`, public types to module categories
  (`Contracts|Data|Enums|Events|Exceptions`); see `docs/architecture/constitution.md` STR-003. Drain queues before
  upgrading; stored `*_type` values holding an old model FQCN no longer resolve (no aliases, PLAN3.D01).

- Add the neutral search contracts and bounded PostgreSQL full-text fallback.
- Add a durable, fenced index lane and rebuild/swap recovery state machine.
