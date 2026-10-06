# axiomasoft/corex-access

Native authorization for CoreX. The package owns tenant role matrices, role assignments, direct grants and scope checks. It works without an external authorization engine.

## Authorization

`AccessChecker::allowed()` checks an active role assignment in the current workspace with a non-`none` matrix row, then an active `aut_direct_grants` row. Unknown or inactive entities always deny. Direct grants must match the principal type, UUID and `{panel}.{entity_key}.{action}` permission key; expired or revoked grants deny.

`scopeFor()` computes the effective role scope. `canOnRecord()` and `ScopeResolver::apply()` share the scope predicate and deny inactive entities before consulting direct grants. An active direct grant bypasses the record scope predicate. Scope normalization rejects a missing capability when the backing table has its corresponding column.

`RoleMatrixCompiler::compile()` publishes `RoleMatrixCompiled` synchronously on the tenant connection. A listener failure rolls back the transaction; the scope map version changes only after commit. The optional bridge listens to this event.

The package publishes tenant migrations for `aut_roles`, `aut_role_assignments`, `aut_role_matrix` and `aut_direct_grants`. The consuming application assembles these in its tenant schema. It may additionally publish the separate cloud FK migrations when the dependent tables exist.

## Extension contracts

Consumers bind `CoreX\Access\Matrix\Contracts\EntityActivity` to decide whether an entity is active and may implement `CoreX\Access\Contracts\RoleMatrixCompiler` when using another compiler. The default bindings are installed by `CoreAuthServiceProvider`.

## Identity provider

`PrincipalUserProvider` registers the `corex-principal` Laravel auth driver. Configure `corex-access.identity` for the user table and columns, then opt into the driver in the application's own auth configuration:

```php
'providers' => [
    'users' => ['driver' => 'corex-principal'],
],
```

The provider reads users through the configured connection and returns `PrincipalIdentity`. It does not write `auth.*` configuration. Password rehashing uses `IdentityPasswordRehasher`; remember tokens are unsupported.

## Installation

Install `axiomasoft/corex-access`, configure the package and assemble its migrations on the intended tenant connection. Composer repositories are application owned and are not inherited from dependencies.

## Development

```bash
composer install
composer test
composer test:pg
composer analyse
composer lint:check
```

## Typed errors

Package error identities use backed enums and a boot compiled kernel registry. See the [error contract](../../docs/reference/errors.md) for contributions and safe mapping.

## Schema installation

Register the package provider, then run `php artisan corex:schema:migrate --scope=tenant --database=<tenant>`. Module sets are installed by `ModuleLifecycle::enable()`; template preparation includes them with `--modules`. Inspect the journal with `corex:schema:status`.
