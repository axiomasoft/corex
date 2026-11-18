# Changelog

## Unreleased — Packman governance

- Adopt Packman API/SPI attributes and shared configuration/error contracts directly, with explicit Internal classification and no compatibility aliases. Declare package ownership in `extra.packman` and the monorepo manifest.


## Unreleased — package boundaries

- Breaking before release: Replace the duplicate panel catalogue, saved-view storage and schema with `axiomasoft/packman-panels`. CoreX retains its provider bridge and `PanelContexts` trusted user-context projection. Panel/SavedViews consumers now use Packman public types and `packman-panels` configuration; the sole table owner is `axiomasoft/packman-panels:tenant`. Remove duplicate engine tests and cover actual CoreX-to-Packman composition through public contracts.

## Unreleased — Laravel 13 platform (P09.9, D132)

- Require Laravel 13 and PHP 8.4+; support PHP 8.4–8.5. Laravel 12 support is removed. Testbench uses the Laravel 13 family; package versions and runtime contracts are unchanged.

## Unreleased — PHP 8.4 modernization (P09.4)

- Modernize maintained PHP for PHP 8.4 with final concrete classes, immutable value state and controlled named calls; preserve current public native parameter names and extension behavior.

## Unreleased — tenant baseline v1

- Split saved-view scope and view tables into baseline files with explicit object names and bigint positions. The module schema set supports PostgreSQL and SQLite; repository tests use the shipped schema.

## Unreleased — deterministic schema (P08.2)

- Saved-view queries and migrations use fixed sys_saved_views and sys_saved_view_scopes tables.

## Unreleased — thin providers and registrars (P05.2)

- Schema delivery now uses tagged `SchemaSetProvider` declarations and `packman:schema:migrate`; migration publishing is removed.

- `PanelsServiceProvider` delegates to `SavedViewsBindingsRegistrar` and `PanelsBindingsRegistrar`; `SavedViewsRegistrar::register()` stays as a deprecated shim.

## Unreleased — typed Options and runtime configuration checks (P05.1)

- Added internal `PanelsOptions`, a lazy singleton resolved in `boot()`.

## Unreleased — kernel package and API migration

- Breaking (PLAN3.P02–P04, pre-1.0): PanelCatalogue and PanelProvider return PanelDescriptor/PanelReference DTOs; SavedViewRepository and view schema/policy ports return SavedQuery and SaveViewInput instead of exposing saved-view models.
- Package installation uses the `axiomasoft` Composer vendor and PHP 8.4–8.5 / Laravel 12–13 constraints; `resources/contracts/php-api.json` records the explicitly attributed API and SPI. Old names have no compatibility aliases.

## Unreleased — PHP surface strict cutover

- Breaking (PLAN3.P04, pre-1.0): supported PHP symbols are declared individually with Api/Spi attributes. Concrete implementation types moved to Internal and typed ports/DTOs and error contracts replace predecessor surfaces as recorded in PLAN3.D63/D64; namespace metadata no longer grants compatibility.
- Generated `resources/contracts/php-api.json` records supported layers and signatures. Strict source/graph/BC checks enforce the declared surface; Internal has no external BC promise.

## Unreleased — package identifier alignment

- Breaking before 1.0: Publish SavedViews migrations through `corex-panels-migrations` and `corex-panels-migrations-tenant`.

## [Unreleased]

- PHP API manifests now record direct inheritance, interfaces and traits separately and include only members declared by this package; external dependency members are omitted without changing the `php-api/1` schema.

### Added

- `panel/1`: headless panel descriptor and current-context access catalogue.
