# axiomasoft/corex

The CoreX kernel provides UUIDv7 models, settings inherited from system to tenant to user, append-only auditing, tenant context, department trees, feature flags and a PubSub contract. It integrates with the Packman entity registry and defines the deferred wakeup contracts in `CoreX\Scheduling`. Install `corex-scheduler` to execute them. CoreX uses the MIT license.

Install the package as an application dependency. Modules expose their public contracts under `<Module>/Contracts/`. Packman attributes mark API DTOs (`#[Api]`), integration adapters (`#[Spi]`) and module-owned implementations (`#[Internal]`). CoreX imports shared configuration and error contracts directly from Packman. Run `php artisan packman:config:validate` to validate runtime configuration for the installed packages. The report redacts secret values.

## Database requirements

Tenant databases require PostgreSQL 16 or later. The `sys_*` migrations use `ltree`, BRIN indexes, `jsonb`, `UNIQUE NULLS NOT DISTINCT` and `pg_advisory_xact_lock`. The SQLite suite (`composer test`) exercises portable behavior. Run `composer test:pg` to check data integrity and concurrency on the supported production database.

## Schema installation

Publish configuration with `php artisan vendor:publish --tag=corex-config`. Packman applies both the CoreX `sys_*` tables and the Packman Modules `mod_*` tables on the tenant PostgreSQL connection.

```bash
php artisan packman:schema:migrate --scope=tenant --database=<tenant>
php artisan packman:schema:status --scope=tenant --database=<tenant>
```

Single-database installations can omit `--database` to use the default connection. In database-per-account deployments, install the tenant schema in each account database. Tenancy owns the central schema. Enable module schema sets through `ModuleLifecycle::enable()`, or pass `--modules` when preparing a template. Production migration requires confirmation or `--force`. The application's own migrations run through `artisan migrate`.

### Schema versions and upgrades

Inspect `Packman\Schema\Data\Migration\SchemaSet::version` in the `Packman\Schema\Contracts\Migration\SchemaSets` registry. Packman runs migration files from each installed package. After publishing a baseline, ship subsequent changes in new migration files and increment the set version. Running `packman:schema:migrate` again applies only the files missing from Laravel's `migrations` journal.

## Trusted scope for workers

Configure `corex.scope.deployment_id` and `corex.scope.control_connection`. Keep the control store outside the tenant backup and restore process, including in boxed installations. Install it with `ScopeControlSchema::install()` and register each account's active binding with its `ScopeAuthority`. The host implements `ControlStoreIsolation` and verifies ownership of the physical connection. In cloud deployments, Tenancy binds this verifier through `TenantBindingAuthority`.

`DataScopeFactory` issues a scope for the authenticated principal in the current account and workspace. The host verifies current membership and named service grants through `WorkPrincipalAuthority`. Console operations and central handlers explicitly set a grant through `NamedWorkPrincipal`. Declare job handlers in `corex.scope.tenant_jobs` and `corex.scope.central_jobs` or through `WorkQueueHandlers`. Store the proof from `StoredWorkIssuer` with each durable local job.

Before moving or restoring a database, close admission in the control store, wait for active tickets to settle and resolve unknown outcomes. Verify the new physical binding and reopen admission with a new generation or restore epoch. Each worker validates that binding before invoking its handler.

## Mandatory support audit

Mandatory support-access events use `CoreX\Audit\Contracts\SynchronousAuditWriter`.
The stock writer commits directly to the configured audit table, requires an event-time
snapshot and rejects ambient transactions. It bypasses the optional audit logger and queue.
Custom implementations must provide the same committed, fail-closed behavior.

## Timestamp precision

All `timestamptz` columns use microsecond precision (`precision: 6`). PostgreSQL rounds `timestamp(0)`, which could reorder events in the append-only `sys_audit_log`. Keyset pagination and audit ordering use the monotonic `sys_audit_log.seq` (`bigserial`). Event IDs and timestamps from individual nodes do not provide that ordering.

## Provider composition

Public providers are thin composition roots: they merge configuration, publish assets and register commands, and
delegate every binding to `PackageRegistrar` services listed in a `REGISTRARS` constant. A registrar keeps the
original binding order and lifetimes; a factory closure in a provider or registrar is a single `return`, and
anything with branching moves to a named `<Thing>Factory`. `CoreServiceProvider` registers `ModulesServiceProvider`
itself, so applications list only `CoreX\CoreServiceProvider`.

## Configuration report contributions

`packman:config:validate` collects services tagged with `Packman\Config\Contracts\ConfigReportSectionInterface`.
Each package owns its options types, secret keys and defaults, drivers and adapters. Sections
supply an integer order; the command retains its stable report format and redacts secret values.
Kernel installations require no optional package to build the report.

Each package validates the shape of its configuration once, when its provider boots, through an
internal `<Pkg>Options` type; the shape check passes on an empty environment, so an image build
(`package:discover`, `config:cache`) needs no secrets. Requirements of the running process are
separate `Packman\Config\Contracts\RuntimeConfigCheck` services tagged by the owning package: they
run when the HTTP application finishes booting or a console command starts (build commands such as
`package:discover`, `config:cache`, `optimize` and `packman:config:validate` are exempt). In
`production`, setting `corex-workflow.enforce_declarations=false` requires its exact config path
in `corex.acknowledged_dangerous_modes`. Patterns are rejected. `packman:config:validate`
runs the same checks (`--production` evaluates them as
production), prints acknowledged modes as warnings and the `corex-operations.require_physical_tenancy=false`
opt-out as a notice, and exits non-zero on a violation.

Consumer tests can create messaging adapters through
`CoreX\Messaging\Testing\MessagingFixtures`; the factory returns the public messaging contracts.

The module runtime belongs to `axiomasoft/packman-modules`; CoreX contributes context, audit and feature adapters. See the [package map](../../docs/architecture/package-map.md).

## JSON in public DTOs

`CoreX\Data\Data\JsonDocument` carries immutable JSON values. The integration adapter `CoreX\Data\Laravel\JsonDocuments` validates the input tree and preserves the distinction between integers and floats. Objects, resources and infinite numbers are rejected. Persistence and HTTP APIs use ordinary JSON values.

```php
use CoreX\Data\Laravel\JsonDocuments;

$document = JsonDocuments::capture(['enabled' => true, 'weight' => 1.5]);
$value = JsonDocuments::map($document);
```

`SettingsRepository::get()` and `FeatureFlags::value()` return a JSON document. Pass it to `SettingsRepository::set()` to store it. `CoreOptions` is an immutable configuration snapshot. Resolve it again from the container to read the current configuration.

## Typed errors

Package error identities use backed enums and a boot compiled kernel registry.
Error constructors require `ErrorCode`; descriptors keep scoped string wire codes.
See the monorepo [error contract](../../docs/reference/errors.md) for contributions,
validated replay and safe mapping.

## Tenant schema

The PostgreSQL `tenant` set started at baseline v1 and owns the `sys_*` tables. The `mod_*` tables belong to `axiomasoft/packman-modules:tenant`. Run `packman:schema:migrate --scope=tenant` to apply the migrations directly from their packages.
