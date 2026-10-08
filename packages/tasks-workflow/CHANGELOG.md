# Changelog

## Unreleased — kernel audit repairs (2026-10-08)

- Resume durable materialization checkpoints at committed occurrence boundaries. Register the optional scoped wakeup handler through kernel tags; give each window/revision/checkpoint its own immutable request identity, expose schedule conflicts and recover failed post-commit scheduling on operation replay.


## Unreleased — Laravel 13 platform (P09.9, D132)

- Require Laravel 13 and PHP 8.4+; support PHP 8.4–8.5. Laravel 12 support is removed. Testbench uses the Laravel 13 family; package versions and runtime contracts are unchanged.

## Unreleased — PHP 8.4 modernization (P09.4)

- Modernize maintained PHP for PHP 8.4 with final concrete classes, immutable value state and controlled named calls; preserve current public native parameter names and extension behavior.

## Unreleased — baseline v1

- Схема: набор(ы) `tenant`, baseline v1. Baseline заменяет невыпущенную upgrade-цепочку; новая установка использует финальную схему.

## Unreleased — wakeup suggest removed (P07.5)

- Schema delivery now uses tagged `SchemaSetProvider` declarations and `corex:schema:migrate`; migration publishing is removed.

- The package no longer suggests `axiomasoft/corex-wakeup`. Materialization schedules through the kernel `CoreX\Scheduling\Contracts\WakeupScheduler` when that binding exists.

## Unreleased — typed Options and runtime configuration checks (P05.1)

- Added internal `TasksWorkflowOptions` and a `RuntimeConfigCheck`: in `production`, `corex-tasks-workflow.wakeup.accept_legacy_unstamped=true` stops the process unless that exact path is acknowledged. `CaptureTaskCompletion` and `SqlTaskRecurrenceMaterializer` receive values instead of the config repository.

## Unreleased — kernel package and API migration

- Breaking (PLAN3.P02–P04, pre-1.0): TaskRecurrenceCommand accepts SeriesReference and MaterializationWindow and returns MaterializationReceipt; completion bindings are explicit, and materialization conflicts use package-owned typed errors.
- Package installation uses the `axiomasoft` Composer vendor and PHP 8.4–8.5 / Laravel 12–13 constraints; `resources/contracts/php-api.json` records the explicitly attributed API and SPI. Old names have no compatibility aliases.

## Unreleased — PHP surface strict cutover

- Breaking (PLAN3.P04, pre-1.0): supported PHP symbols are declared individually with Api/Spi attributes. Concrete implementation types moved to Internal and typed ports/DTOs and error contracts replace predecessor surfaces as recorded in PLAN3.D63/D64; namespace metadata no longer grants compatibility.
- Generated `resources/contracts/php-api.json` records supported layers and signatures. Strict source/graph/BC checks enforce the declared surface; Internal has no external BC promise.

## Unreleased — typed error contract (P04.3)

- Added package owned error enums, definitions and safe coded exception descriptors.
- Error constructors require enum identities; existing error wire values are scoped.
- Contributions are validated and frozen at boot; persisted identities use validated lookup.

## Unreleased — package identifier alignment

### Changed

- PLAN3.D57: implementation classes moved to `<Module>\Internal\…`, public types to module categories
  (`Contracts|Data|Enums|Events|Exceptions`); see `docs/architecture/constitution.md` STR-003. Drain queues before
  upgrading; stored `*_type` values holding an old model FQCN no longer resolve (no aliases, PLAN3.D01).

- Breaking before 1.0: Rename the config file and key to `corex-tasks-workflow`.

## Unreleased

- PHP API manifests now record direct inheritance, interfaces and traits separately and include only members declared by this package; external dependency members are omitted without changing the `php-api/1` schema.

- Add `corex/tasks-workflow` package with `TaskRecurrenceCommand` materialization (P7.4).
- Add `corex_task_series`, `corex_task_series_state`, and `corex_task_occurrences` mapping tables.
- Add MAT-01..09 local conformance suite.
