# axiomasoft/corex-access

Install `axiomasoft/corex-access` to check permissions and constrain Eloquent queries in a CoreX application. Laravel discovers its provider and the package registers its authorization panel. A host supplies a CoreX principal and uses the current account context for each decision.

## Check records and list visible rows

```php
use CoreX\Access\Contracts\AccessChecker;
use CoreX\Access\Laravel\ScopeResolver;

$canView = app(AccessChecker::class)->canOnRecord($principal, 'view', $company);
$visible = app(ScopeResolver::class)->apply(
    Company::query()->where('archived', false), $principal, 'crm.company', 'view',
)->get();
```

`AccessChecker::allowed($principal, 'crm.company', 'view')` checks the entity-level permission. Use `canOnRecord()` for a particular record. An unknown or inactive entity and a missing trusted account context deny access. Both operations use the same registered model and entity handle. The query retains caller-supplied constraints.

## Manage roles and direct grants

```php
use CoreX\Access\Enums\Scope;
use CoreX\Access\Roles\DirectGrants;
use CoreX\Access\Roles\RoleManager;

$roles = app(RoleManager::class);
$role = $roles->create('account-manager', 'Account manager');
$roles->setScope($role, 'crm.company', 'view', Scope::Own);
$roles->assign($role, $principal);

$grants = app(DirectGrants::class);
$grants->grant($principal, 'crm.company', 'view', now()->addHour()->toDateTimeImmutable());
$grants->revoke($principal, 'crm.company', 'view');
```

On the current tenant connection, a matching direct grant gives entity-wide access until expiry or revocation. Role assignments may be workspace-specific and expire. Matrix cells support ownership, departments and their descendants, workspace, account and contextual overrides. Matrix compilation emits `RoleMatrixCompiled` inside the transaction and invalidates the scope version after commit.

## Host policies

The built-in matrix handles permissions by default. A host can explicitly assign an entity/action to a policy panel in `config/corex-access.php`:

```php
'policy_panels' => ['crm.company.view' => 'review'],
```

The registered `review` panel then owns the capability check, record decision and SQL visibility for that permission. The host policy must support exact query filtering to serve listings. Its denial is final. A similarly named panel has no effect without the exact mapping. CoreX maps principal, tenant and workspace internally.

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

Register the package provider, then run `php artisan packman:schema:migrate --scope=tenant --database=<tenant>`. Module sets are installed by `ModuleLifecycle::enable()`; template preparation includes them with `--modules`. Inspect the journal with `packman:schema:status`.

Схема: наборы `tenant` и `tenant-cloud`, baseline v1. Cloud FK требуют tenancy; boxed использует только `tenant`. Baseline заменяет невыпущенную upgrade-цепочку.
