# Changelog

## Unreleased — package identifier alignment

### Changed

- PLAN3.D57: implementation classes moved to `<Module>\Internal\…`, public types to module categories
  (`Contracts|Data|Enums|Events|Exceptions`); see `docs/architecture/constitution.md` STR-003. Drain queues before
  upgrading; stored `*_type` values holding an old model FQCN no longer resolve (no aliases, PLAN3.D01).

- Breaking before 1.0: Rename the config file and key to `corex-tasks-workflow`.

## Unreleased

- Add `corex/tasks-workflow` package with `TaskRecurrenceCommand` materialization (P7.4).
- Add `corex_task_series`, `corex_task_series_state`, and `corex_task_occurrences` mapping tables.
- Add MAT-01..09 local conformance suite.
