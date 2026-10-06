# axiomasoft/corex-custom-fields

Typed PostgreSQL attribute-store, filter, and schema-operation ports for CoreX.

`axiomasoft/corex-custom-fields` is a neutral owned engine. It reads active scalar declarations from the existing
`EntityRegistry`/`ModuleRegistry`; it never creates a second field registry or product dependency.
Its only tables are additive `sys_attribute_*` tables.

The application selects either `jsonb` or `eav` storage. Both drivers enforce a trusted `DataScope`,
including the physical `pgsql:<database>` identity, authorization callback, record version and write
token. Reads and writes always validate the active field snapshot and all fifteen supported scalar
types when `axiomasoft/corex` is installed and both registries are bound.

Without `axiomasoft/corex`, `FieldCatalogue`, `AttributeStore`, and `SchemaOperations` resolve to
`UnavailableAttributes`. Dynamic attribute fields are unavailable in that configuration; this is an
observable fail-closed state, not a silent degradation.

Schema requests persist an idempotent operation only. They do not execute SQL in the request path.
A trusted asynchronous worker invokes `SchemaOperationWorker` with a fresh `DataScope`; it claims
the operation transactionally, executes PostgreSQL concurrent index DDL outside that transaction,
checks the catalog, preserves a 30-day retirement grace period before a managed-index drop, and
denies stale restore epochs. The application is responsible for dispatching that worker from its
queue or console boundary; it must never be called from HTTP handling.

## Installation

Install with `composer require axiomasoft/corex-custom-fields` through the configured CoreX channel. Provide a trusted PostgreSQL tenant connection and apply the package-owned `sys_attribute_*` migrations before enabling writes. Bind the module/entity registries when dynamic attribute definitions are required; without them the package reports its unavailable state.

## Configuration

`corex-custom-fields.storage_driver` defaults to `jsonb`; select `eav` when needed. Use
`corex-custom-fields.storage_by_entity` to map individual entities to either driver. The application
controls these settings and the `drivers` enablement map. `sources` selects definition sources
(`static` by default), while `definitions` maps entities to lists of field definitions.

Package-owned `sys_attribute_*` table names are literal in the migrations and composed into an
immutable `AttributeTables` object at provider registration. Host configuration does not rename
these tables; explicit storage composition can still use the validated `AttributeTables` DTO.

## Extension contracts

Use the exported `FieldCatalogue`, `AttributeStoreResolver`, `AttributeStore`, `AttributeFilter` and `SchemaOperations` ports. The host supplies its trusted scope and authorization callback. Replace the definition source through the catalogue boundary; storage implementations and worker internals are not extension points.

## Typed errors

Package error identities use backed enums and a boot compiled kernel registry.
Error constructors require `ErrorCode`; descriptors keep scoped string wire codes.
See the monorepo [error contract](../../docs/reference/errors.md) for contributions,
validated replay and safe mapping.

## Schema installation

Register the package provider, then run `php artisan corex:schema:migrate --scope=tenant --database=<tenant>`. Module sets are installed by `ModuleLifecycle::enable()`; template preparation includes them with `--modules`. Inspect the journal with `corex:schema:status`.
