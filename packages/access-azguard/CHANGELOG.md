# Changelog

## Unreleased — Packman governance

- Adopt Packman API/SPI attributes and shared configuration/error contracts directly, with explicit Internal classification and no compatibility aliases. Declare package ownership in `extra.packman` and the monorepo manifest.


## Unreleased — AzGuard 2 adapter

- Replace the obsolete AzGuard 0.3 mirror with public AzGuard 2 decision and exact-query APIs.
- Select AzGuard as the authorization owner; remove cross-package table access, role mirrors, schema, configuration pins and legacy Blade directives.
- Add the public `AccessContextMapper` SPI and fail closed without a trusted account.
- Reject lossy legacy `scopeFor()` projection with `ScopeProjectionNotSupported`; use record decisions or query visibility. This is a breaking change to the unreleased bridge.

## Unreleased — Laravel 13 platform (P09.9, D132)

- Require Laravel 13 and PHP 8.4+; support PHP 8.4–8.5. Laravel 12 support is removed. Testbench uses the Laravel 13 family; package versions and runtime contracts are unchanged.

## Unreleased — PHP 8.4 modernization (P09.4)

- Modernize maintained PHP for PHP 8.4 with final concrete classes, immutable value state and controlled named calls; preserve current public native parameter names and extension behavior.

## Unreleased — baseline v1

- Схема: набор(ы) `tenant`, baseline v1. Baseline заменяет невыпущенную upgrade-цепочку; новая установка использует финальную схему.

## Unreleased — P07.4

- Schema delivery now uses tagged `SchemaSetProvider` declarations and `packman:schema:migrate`; migration publishing is removed.

- Introduced the optional AzGuard bridge for native CoreX Access authorization.
- Moved the AzGuard context guard, permission layer, grant source, catalog builder, and role model adapter out of `corex-access`.
- Added synchronous matrix mirroring and the tenant-local `aut_azguard_role_links` table.
