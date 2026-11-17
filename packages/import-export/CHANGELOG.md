# Changelog

## Unreleased — package boundaries

- Add `ImportExportFactory::openImport()` / `openExport()` and typed session handles. The package owns initial job persistence, idempotent reopen and executor composition; callers use exported ports without importing internals or writing private tables.
- Bind sessions to immutable request identity, trusted data scope and the active physical database. Execution and result reads reject scope/restore drift; import execution validates its mapping and refuses terminal jobs. Export manifests cannot cross job identities, and successful sealing completes the owning job.
- Export actionable `SessionUnavailable`, `ImportUnavailable` and `ExportUnavailable` exceptions from the public exception namespace.
- Breaking before release: Rename the package to `axiomasoft/corex-import-export`, namespace to `CoreX\ImportExport`, config to `corex-import-export`, and provider to `ImportExportServiceProvider`. Import/export of application records is distinct from infrastructure backup and restore.

## Unreleased — Packman runtime

- Breaking: module declarations and schema registrations use Packman contracts directly; module lifecycle and schema execution are owned by Packman.

## Unreleased — Laravel 13 platform (P09.9, D132)

- Require Laravel 13 and PHP 8.4+; support PHP 8.4–8.5. Laravel 12 support is removed. Testbench uses the Laravel 13 family; package versions and runtime contracts are unchanged.

## Unreleased — PHP 8.4 modernization (P09.4)

- Modernize maintained PHP for PHP 8.4 with final concrete classes, immutable value state and controlled named calls; preserve current public native parameter names and extension behavior.
- Refresh the PHP API metadata for equivalent readonly/type/promotion declarations (D130).

## Unreleased — baseline v1

- Схема: набор(ы) `tenant`, baseline v1. Baseline заменяет невыпущенную upgrade-цепочку; новая установка использует финальную схему.

## Unreleased — typed Options and runtime configuration checks (P05.1)

- Schema delivery now uses tagged `SchemaSetProvider` declarations and `packman:schema:migrate`; migration publishing is removed.

- Added internal `ImportExportOptions`, a lazy singleton resolved in `boot()`.

## Unreleased — kernel package and API migration

- Breaking (PLAN3.P02–P04, pre-1.0): Extracted transfer into its own CoreX\ImportExport package and provider; transfer/export ports expose canonical records, checkpoints, conflict rules and sealed export manifests, while codecs and persistence are Internal.
- Package installation uses the `axiomasoft` Composer vendor and PHP 8.4–8.5 / Laravel 12–13 constraints; `resources/contracts/php-api.json` records the explicitly attributed API and SPI. Old names have no compatibility aliases.

## Unreleased — PHP surface strict cutover

- Breaking (PLAN3.P04, pre-1.0): supported PHP symbols are declared individually with Api/Spi attributes. Concrete implementation types moved to Internal and typed ports/DTOs and error contracts replace predecessor surfaces as recorded in PLAN3.D63/D64; namespace metadata no longer grants compatibility.
- Generated `resources/contracts/php-api.json` records supported layers and signatures. Strict source/graph/BC checks enforce the declared surface; Internal has no external BC promise.

## Unreleased

- PHP API manifests now record direct inheritance, interfaces and traits separately and include only members declared by this package; external dependency members are omitted without changing the `php-api/1` schema.

- Added bounded CSV and NDJSON codecs plus typed transfer ports and checkpoint DTOs.
- Added a fenced local PostgreSQL transfer runner and neutral transfer persistence migration.
- Added sealed coherent export manifests, verified immutable NDJSON parts, current-policy output
  fencing, and an explicitly separate display-safe CSV profile.
