# Schema policy

Each package owns the schema of its tables, ships its migration sources in its Composer distribution, and executes them through schema sets. See the [table ownership register](table-ownership.md) and articles DATA-001 through DATA-007 of the [constitution](constitution.md).

## Schema sets and registration

A `Packman\Schema\Data\Migration\SchemaSet` is the unit of execution.

| Field | Rule |
| --- | --- |
| `id()` | A unique registry key in the form `<composer-name>:<set>`. The set name matches the directory. |
| `package` | Composer name of the owning package. |
| `scope` | `SchemaScope::Central`, `Tenant` or `Dedicated`. |
| `lifecycle` | `SchemaLifecycle::Platform`, or `Module` for a package declaring `extra.packman.module`. |
| `path` | Absolute path to the package's `database/migrations/<set>` directory. |
| `version` | Integer generation starting at 1. The original baseline is generation 1. |
| `drivers` | Includes `pgsql` and, for sets supporting SQLite, `sqlite`. |
| `requires` | Composer names of packages whose sets must be registered for this set to be active. |
| `connection` | Connection name from the owner's options. Required only for `Dedicated`. |

An internal class implements `Packman\Schema\Contracts\Migration\SchemaSetProvider`. Its service provider registers the class under the contract's tag in `register()`.

```php
$this->app->tag([PackageSchemaSets::class], SchemaSetProvider::class);
```

On first resolution, the `Packman\Schema\Contracts\Migration\SchemaSets` singleton collects tagged sets, sorts them by ID and stores an immutable registry. It rejects invalid paths, duplicate IDs, duplicate migration basenames within a scope, a missing `pgsql` driver, and invalid scope/connection combinations. The static gate also requires repository-wide uniqueness of migration basenames.

Package migrations stay in their package. Packages neither publish them nor register them with `loadMigrationsFrom`. Laravel's `php artisan migrate` handles application migrations. Use the Packman commands below for package tables.

## Scope, lifecycle and migration history

| Set directory | Scope | Execution database | Ownership register's `tenant_scope` |
| --- | --- | --- | --- |
| `root` | `Central` | Central tenancy database | `root` |
| `tenant` | `Tenant` | Account database, or the default connection in a boxed deployment | `tenant` |
| `tenant-cloud` | `Tenant` | Account database, requires `axiomasoft/corex-tenancy` | `tenant` |
| `budget` | `Dedicated` | Set owner's connection | `shared-budget` |

`SchemaMigrator::migrate()` runs active Platform sets in the selected scope. Passing `includeModules: true` includes Module sets. `ModuleLifecycle::enable()` also installs Module sets using registry paths. `migrateSets()` runs explicitly selected IDs, including those selected by the `MigratesSchemaSets` testing trait. On PostgreSQL, both schema migrator paths for Module sets use the lifecycle's per-migration schema diff. The migration journal, DDL and `mod_records` ownership claims commit together under the lifecycle graph lock. For an explicit Module selection on a new database, the migrator includes the prerequisite `axiomasoft/packman-modules:tenant` for the registry and ownership claims.

The `axiomasoft/corex-tenancy:tenant` set references department tables, so `axiomasoft/corex:tenant` runs first. The `axiomasoft/corex-access:tenant-cloud` set requires both table owners, `axiomasoft/corex-access:tenant` and `axiomasoft/corex-tenancy:tenant`. Selecting the cloud set installs its prerequisites. The base Access set works independently of Tenancy.

An account template clone must include both the Laravel migration journal and `mod_records`. Subsequent activation reuses the stored claims. A table name or prefix cannot establish ownership of an existing table without a claim. SQLite sets retain their own installation path. Lifecycle and purge operations that require PostgreSQL run only on PostgreSQL.

Central and Tenant sets accept a connection override and otherwise use the default connection. Each Dedicated set runs on its owner's connection and rejects a shared `--database` override. The migrator checks driver support before executing anything. Laravel orders files from all paths in a group by basename.

The execution database's Laravel `migrations` table is the sole journal. Each row records one file basename. `status()` reports `applied`, `pending` and `unknown`. An unknown row has a package code and no matching file in the registry.

## Installation and inspection

```bash
php artisan packman:schema:migrate --scope=tenant
php artisan packman:schema:status --scope=tenant
php artisan packman:schema:migrate --scope=central --database=central
php artisan packman:schema:migrate --scope=dedicated
```

The application supplies the connection names. Production migrations require `--force`. To prepare an account template, run `packman:schema:migrate --scope=tenant --modules --database=<template>`. Activate modules normally through `ModuleLifecycle`. The Tenancy wave runner migrates Tenant Platform sets on the `tenant` connection under its existing lock and transaction. `ModuleLifecycle::upgrade()` brings Module sets up to date. `packman:schema:status` exits with code 1 for pending or unknown migrations. Re-running a migration without new files executes no DDL.

## Baseline migration rules

- Place migration files in `database/migrations/<set>/`. Each returns an anonymous `Migration` class. Use one `2026_10_01_<PP><NNNN>_create_<table>.php` file per table and its objects. Put a deferred foreign key within a package or in `tenant-cloud` in a separate `2026_10_01_<PP><NNNN>_add_<table>_<purpose>.php` file. `PP` is the package code from [schema-sets.json](../../scripts/schema-sets.json). Number steps `NNNN` as 0010, 0020 and so on. Create tables before references to them.
- Give every primary key, unique constraint, index, foreign key and check constraint an explicit literal name `<table>_<purpose>_<kind>`, with kind `pk|uq|ix|fk|ck`. Name the primary key `<table>_pk`. Names must be unique within a scope and fit in 63 bytes. To shorten a long name, take its first 51 bytes, an underscore, the first eight characters of its full SHA-1, another underscore and the kind suffix. Write the resulting literal in the migration. Avoid unnamed objects and the implicit names generated by `constrained()` and `*Morphs()`.
- Use `uuid` for CoreX keys and references. A polymorphic reference consists of `<name>_type string(64)` and `<name>_id string(64)` with an explicit composite index. Store closed sets of values as strings and add a PostgreSQL CHECK where needed. Database enum and set types are prohibited.
- Use signed bigint for counters, positions, attempts, durations, generations and Unix timestamps. A bounded percentage, HTTP status, port, size, envelope version or configuration limit may use a smaller integer only with a `table.column → reason` entry in the [allowlist](../../scripts/schema-gate-allowlist.json).
- Keep `up()` limited to PHP and Illuminate schema operations. The migrator selects the connection. Do not use application configuration, dependency injection, models, package domain classes or data writes. `down()` removes objects in reverse order and preserves existing rollback guards. The purity rule applies to `up()`.
- The baseline captures the final schema of the consolidated migration chain with its approved normalizations. Preserve other fields, nullability, defaults and objects. The schema is determined by the migration files.
- Preserve SQLite support for sets that offer it. Tables, columns and ordinary indexes must agree across drivers. Guard PostgreSQL-specific DDL, including CHECK via ALTER, driver-specific partial or expression indexes, jsonb operators, COMMENT and triggers, with a driver check such as `Schema::getConnection()->getDriverName() === 'pgsql'`.

## Enforcement

`scripts/php84 composer schema-gate` checks all migrations shipped by packages and products. Test fixtures are outside its scope. There are no temporary package exemptions.

| Rule | Check |
| --- | --- |
| S1 | Every file belongs to exactly one registered directory, and every set contains files. |
| S2 | Filenames follow the naming convention, contain the correct package code and have repository-wide unique basenames. |
| S3 | Indexes and constraints have explicit names. Implicit Blueprint shorthand is prohibited. |
| S4 | Object names are literals, have the correct suffix, fit in 63 bytes and are unique within their scope. |
| S5 | Database enum and set types are prohibited. |
| S6 | Smaller integer types require an allowlist entry. |
| S7 | The purity rule for `up()` includes internal methods it calls. |
| S8 | Schema set generations match the release baseline and its declared upgrades. The CoreX 2.0 baseline is generation 2, FlexCRM starts at generation 1, and baseline files carry the date `2026_10_01`. |
| S9 | Every created table appears in the ownership register with matching package and scope. |

[schema-sets.json](../../scripts/schema-sets.json) reflects the live registry. Regenerate it with `scripts/php84 scripts/schema-gate.php --write-sets` and verify it with `--check`. `composer table-ownership-gate` applies S9 to registered paths and T4 to cross-package foreign keys. `composer table-write-ownership-gate` detects writes to another owner's tables.

The [schema goldens](../../tests/Golden/schema) record canonical catalogs from fresh PostgreSQL databases for Tenant, Central and Dedicated scopes, and from SQLite for supported sets. Comparisons cover types, defaults, nullability, index definitions and names, foreign keys and checks. They also check the absence of database enum types and the 63-byte name limit. Regenerating an expected catalog with `GOLDEN_UPDATE=1` requires classifying the change during release review. These comparisons verify the resulting DDL. Migration file integrity is a separate release requirement.

## After `1.0.0-rc.1`

Released migrations are immutable. Add each schema change as a new `<date>_<PP><NNNN>_<create|add>_<table>[_<purpose>].php` file with a new step, and increment `SchemaSet::version` in the same commit. Preserve the Laravel `migrations` history and record the owner of every new table. At the next release, check the new schema and golden catalog against the released migrations. S8 must admit the new generation. Changing a released migration to satisfy a golden check violates the immutability policy.
