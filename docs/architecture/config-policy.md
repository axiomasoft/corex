# Configuration policy

The [constitution](constitution.md) defines configuration invariants CFG-001 through CFG-006.

## Key ownership

| Class | Owner | When it changes | Storage |
| --- | --- | --- | --- |
| `deployment` | Installation administrator | At deployment | Configuration file and environment variables |
| `product-policy` | Package developer | At release | Package configuration file with a default |
| `runtime-settings` | User or account administrator | During operation | Settings hierarchy (System → Tenant → User) |
| `secret` | Installation administrator | At rotation | Environment or secret store |

Assign exactly one class to each key. If two classes appear to apply, resolve the ownership conflict in favor of the stricter class.

## Adding a key

1. Assign its class.
2. Provide a default that allows an unconfigured installation to start.
3. Declare the key on the package's typed options object. Services receive the value through their constructors.
4. Add the key to `rows` in `scripts/configuration-classes.json`, including owner, secret, mode, reloadable, validation, deprecated_aliases and notes. Run `php scripts/configuration-reference.php --write` to regenerate [the configuration reference](configuration.md). The generator reports keys without a corresponding options field. Mark reference-only entries such as Composer pins with `(reference)`. Use `(registrar-read)` for keys that a registrar reads directly.
5. Mask the default of a secret as `***` in the reference.

## Reading configuration

Read configuration at the point where the package assembles dependencies, in a service provider or a console command. Passing values into services makes them available for startup validation, diagnostics and substitution in tests.

## Validation

Detect invalid configuration during application startup. Waiting for the first use exposes the administrator's configuration error to the user who triggers that feature.

1. **Shape.** `<Pkg>Options::fromArray()` checks types, ranges, permitted values and `class-string` values. It supplies defaults and accepts an empty configuration without environment variables. The public provider resolves its options in `boot()` so a shape error fails startup.
2. **Runtime checks.** Tagged `RuntimeConfigCheck` implementations report `violations(bool $production)` for dangerous modes and missing secrets required by enabled features. Check after boot for HTTP and on `CommandStarting` for console work. Image builds, including `package:discover` and `config:cache`, can run without production secrets.
3. **Deployment check.** `php artisan packman:config:validate --production` always runs the same production checks.

### Acknowledging dangerous modes

In production, `corex-workflow.enforce_declarations = false` requires its exact configuration path in `corex.acknowledged_dangerous_modes`. An unacknowledged dangerous mode raises `InvalidConfiguration` during startup. The list accepts literal paths only. The validation command reports an acknowledged mode as a warning.

`corex-operations.require_physical_tenancy = false` explicitly selects the supported boxed deployment mode. The report shows it as a notice and requires no acknowledgement.

### Configuration caching and resident workers

`php artisan config:cache` serializes configuration with `var_export`. Configuration files may contain scalar values, arrays and enum cases. Closures and other objects cannot be included. `composer config-cache-check` runs `tests/Feature/ConfigCacheTest.php`, round-trips every `packages/*/config/*.php` through `var_export` and `eval`, and boots the application from the cached repository.

A lazy singleton is suitable for immutable deployment options that are independent of the request. Options that vary by scope and services holding tenant state or a connection use `scoped` bindings. `Application::forgetScopedInstances()` marks the boundary. `ResidentLifecycleTest` and `ResidentTenantSwitchPgTest` exercise this requirement under RUN-005.
