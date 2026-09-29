# Changelog

## Unreleased — package identifier alignment

### Changed

- PLAN3.D57: implementation classes moved to `<Module>\Internal\…`, public types to module categories
  (`Contracts|Data|Enums|Events|Exceptions`); see `docs/architecture/constitution.md` STR-003. Drain queues before
  upgrading; stored `*_type` values holding an old model FQCN no longer resolve (no aliases, PLAN3.D01).

- Breaking before 1.0: Rename the config file and key to `corex-custom-fields`.

## Unreleased

- Added owned neutral JSONB/EAV attribute storage, active EntityRegistry field binding, and typed value validation.
- Added durable schema-operation requests and a restore-safe asynchronous PostgreSQL concurrent-index worker.
