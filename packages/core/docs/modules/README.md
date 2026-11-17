# CoreX modules on Packman

CoreX uses `axiomasoft/packman-modules` for discovery, manifest compilation, entity registries
and module lifecycle. Module definitions and executable schema sets belong to their owning
Composer packages; the application selects and activates the installed modules.

## Public contracts

- `Packman\Modules\Contracts\Definition\ModuleDefinition` and
  `Packman\Modules\Data\Lifecycle\ModuleManifest` declare a module.
- `Packman\Modules\Contracts\Registry\ModuleRegistry` exposes the compiled catalogue and
  checks current context activation.
- `Packman\Modules\Contracts\Lifecycle\ModuleLifecycle` owns activation, upgrade and removal.
- `Packman\Schema\Contracts\Migration\SchemaSetProvider` supplies package-owned schema sets.

CoreX's `CoreServiceProvider` registers its Packman adapters. `CoreX\Context\Data\TenantContext`
implements Packman's context scope; CoreX projects actor, feature flag and entity-audit behavior
through public upstream contracts. There is no second CoreX loader, registry or lifecycle engine.

## Schema ownership

`axiomasoft/packman-modules:tenant` owns `mod_modules`, `mod_records` and `mod_lifecycle_log`.
The schema registry is provided by Packman Schema. Install the relevant tenant sets with
`php artisan packman:schema:migrate --scope=tenant --database=<tenant>`; the host selects the
trusted connection and supplies `--force` in production. Package migration files are executed
in place rather than copied into the application.

For application-specific entities and operations, declare the corresponding Packman extenders
in the owning module manifest. CoreX operations, access, custom fields and search consume those
public declarations without depending on an internal compiler implementation.
