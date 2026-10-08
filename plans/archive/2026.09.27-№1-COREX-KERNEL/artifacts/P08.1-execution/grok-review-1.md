**RED.** `corex:schema:status` reports other scopes' journal rows as unknown and exits 1 after a successful single-database migrate. `flex/crm` omits SQLite from `drivers` even though its SQLite lane applies those migrations. The required module-lifecycle acceptance case is not in the suite.

Driver preflight itself is in the right place. `LaravelSchemaMigrator::groups()` checks every selected connection's driver before `createRepository()` or `Migrator::run()` (`packages/core/src/Database/Internal/LaravelSchemaMigrator.php:83-120`). An unsupported driver does not create the journal. `migrate(Tenant, 'tenant')` does not run Central sets. The wave runner keeps that call on the `tenant` connection inside the existing transaction and lock (`packages/tenancy/src/Account/Laravel/MigrationWaveOrchestrator.php:160-168`). Publish tags and `loadMigrationsFrom` are gone from package and product providers. `SCHEMA_VERSION` is gone. The 22 declared sets match the current directories, including the temporary core `tenant` / `tenant-modules` split and the idempotency `git mv`.

## Findings

### 1. Major — status treats a shared Laravel journal as per-set baseline damage

`packages/core/src/Database/Internal/LaravelSchemaMigrator.php:57-68`  
`packages/core/src/Database/Internal/Commands/SchemaStatusCommand.php:30-36`

`status()` takes the first two digits of every current migration sequence and treats them as that set's baseline prefix. Shipped names are not baseline names, so those digits collide. `2026_07_13_000000_create_mod_modules_table` and `2026_10_04_000001_create_aut_direct_grants_table` both yield prefix `00`. The Central file `2026_09_13_000001_add_signup_key_index` yields the same `00`, but it is not one of the Tenant files in `$known`.

On the single-database install documented in `packages/core/README.md:32-33`, Central and Tenant are applied to one connection and one `migrations` table. Tenant status then lists that Central row as `unknown` on every Tenant set whose files use sequence prefix `00`, and the command returns exit code 1 with nothing pending. The same row is attached to more than one set. §3.4 defines unknown as a missing file whose name carries that set's baseline prefix.

**Repair:** Derive a prefix only from the baseline grammar `2026_10_01_<PP><NNNN>_` (package code PP). Do not read PP from legacy sequences such as `000000` or `100000`. Ignore journal rows that belong to another registered set. Add a regression that applies `axiomasoft/corex-tenancy:root` and `axiomasoft/corex:tenant-modules` on one connection and expects Tenant `unknown` to be empty and `corex:schema:status` to exit 0.

### 2. Major — `flex/crm` drops SQLite support at the migrator

`products/flex-crm/src/Internal/CrmSchemaSets.php:20`  
`products/flex-crm/tests/Support/CrmTestCase.php:27-64`  
`products/flex-crm/database/migrations/2026_09_15_000001_full_storage_parity.php:221-227`

The set uses the default `drivers: ['pgsql']`. `CrmTestCase` still applies that directory on the SQLite `testbench` connection, and the parity migration already branches for SQLite. `composer test:packages` runs this lane with `--exclude-group=pg`. §4.6 / F4 require `sqlite` in `drivers` when a current test executes the set on SQLite.

**Impact:** `SchemaMigrator::migrate(Tenant, includeModules: true)` and `migrateSets(['flex/crm:tenant'])` on that SQLite connection throw `unsupported_driver` before any set in the batch runs. The package tests never see it because they call `loadMigrationsFrom` instead of the migrator.

**Repair:** Declare `drivers: ['pgsql', 'sqlite']` and cover `migrateSets(['flex/crm:tenant'])` on SQLite.

### 3. Major — package tests still migrate by path

`packages/tenancy/tests/Feature/MigrationWaveTest.php:33-37`  
`packages/tenancy/tests/Feature/TenantSessionScopeTest.php:103-114`  
`packages/tasks-workflow/tests/TestCase.php:98-101`  
`products/flex-crm/tests/Support/CrmTestCase.php:27-29`

D111.8 and the item rule say tests build package tables through `MigratesSchemaSets` and do not name package migration paths. Fixture directories under `packages/*/tests/database/migrations` may stay paths. The harness still passes registry paths to `artisan migrate --path` and `loadMigrationsFrom`. `tasks-workflow` also loads one shipped file, `…/2026_09_15_000071_create_task_operation_receipts.php`.

**Impact:** Those tests skip driver preflight, scope grouping, and the shared journal. That is why finding 2 stays green under V11.

**Repair:** Call `migrateSchemaSets` / `migrateSchemaScope` with an explicit connection. Leave only test-fixture directories on `loadMigrationsFrom`.

### 4. Major — examples §8 case 7 is not in the acceptance test

`tests/Feature/SchemaSetsTest.php` (no `ModuleLifecycle::enable()` call; file ends at the declaration and dedicated-preflight cases)

V1 requires the eight §8 scenarios to be present. Cases 1–6 are present. Case 7, `ModuleLifecycle::enable()` running the fixture module set from `SchemaSets` rather than `installed.json`, is not. `tests/Feature/ModuleLifecycleTest.php:111-117` still substitutes stub paths into `corex.modules.wiring`.

The production wiring in `LifecycleRegistrar::moduleWiring()` (`packages/core/src/Modules/Laravel/LifecycleRegistrar.php:73-81`) does read Tenant+Module paths from the registry. V1's green run does not lock that.

**Repair:** Add the fixture enable case to `SchemaSetsTest`: a module set registered only through `SchemaSetProvider` must be the path `enable()` migrates, with no `installed.json` migration path.

## Coverage

Reviewed the item contract, D111, design §3, §6.1 and §7, and examples §1–§5, §7 and §8. Read the SPI, registry, migrator, commands, trait, registrar, core/tenancy/access/operations/CRM declarations, lifecycle wiring, wave runner, both schema feature tests, error codes, and the install sections of the core and tenancy READMEs. Inventoried every `loadMigrationsFrom` and `artisan migrate --path` call site and the shipped migration basenames used for the prefix collision. Migration hunks in the candidate diff are comment replacements; baseline DDL was not reshaped.

Not re-run: V1–V25. Not line-read: every package CHANGELOG and the full `php-api.json`. The executor summary reports those checks green; the gaps above are visible in the tests those checks execute.

Verdict: **RED**
