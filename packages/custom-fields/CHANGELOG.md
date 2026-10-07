# Changelog

## Unreleased — Laravel 13 platform (P09.9, D132)

- Require Laravel 13 and PHP 8.4+; support PHP 8.4–8.5. Laravel 12 support is removed. Testbench uses the Laravel 13 family; package versions and runtime contracts are unchanged.

## Unreleased — PHP 8.4 modernization (P09.4)

- Modernize maintained PHP for PHP 8.4 with final concrete classes, immutable value state and controlled named calls; preserve current public native parameter names and extension behavior.
- Refresh the PHP API metadata for equivalent readonly/type/promotion declarations (D130).
- Remove verified unused Internal helpers: `CachedFieldCatalogue::invalidate`.

## Unreleased — baseline v1

- Схема: набор(ы) `tenant`, baseline v1. Baseline заменяет невыпущенную upgrade-цепочку; новая установка использует финальную схему.

## Unreleased — deterministic schema (P08.2)

- Remove the tables configuration and internal Options table map; storage tables use package-owned literals while AttributeTables remains available for explicit composition.

## Unreleased — thin providers and registrars (P05.2)

- Schema delivery now uses tagged `SchemaSetProvider` declarations and `corex:schema:migrate`; migration publishing is removed.

- `AttributesServiceProvider` delegates to messaging, composition and bindings registrars; multi-statement closures moved to `AttributeStoreFactory`, `SchemaOperationsFactory` and `FieldCatalogueFactory`. With the kernel provider the module registry is always bound, so `SchemaOperations` resolves to the queued DDL implementation.

## Unreleased — typed Options and runtime configuration checks (P05.1)

- Added internal `CustomFieldsOptions`, a lazy singleton resolved in `boot()` that carries the validated composition and table map; `AttributeTables` and the composition are built from it.

## Unreleased — kernel package and API migration

- Breaking (PLAN3.P02–P04, pre-1.0): AttributeStore, FieldCatalogue and SchemaOperations expose AttributeId, AttributeDefinition, FieldSelection and write-result DTOs; physical attribute storage and catalogue implementation are Internal.
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

- Breaking before 1.0: Rename the config file and key to `corex-custom-fields`.

## Unreleased

- Declare Symfony Process as a development dependency used by package tests.

- PHP API manifests now record direct inheritance, interfaces and traits separately and include only members declared by this package; external dependency members are omitted without changing the `php-api/1` schema.

- Added owned neutral JSONB/EAV attribute storage, active EntityRegistry field binding, and typed value validation.
- Added durable schema-operation requests and a restore-safe asynchronous PostgreSQL concurrent-index worker.
