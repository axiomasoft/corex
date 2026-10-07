# Changelog

## Unreleased — Laravel 13 platform (P09.9, D132)

- Require Laravel 13 and PHP 8.4+; support PHP 8.4–8.5. Laravel 12 support is removed. Testbench uses the Laravel 13 family; package versions and runtime contracts are unchanged.

## Unreleased — PHP 8.4 modernization (P09.4)

- Modernize maintained PHP for PHP 8.4 with final concrete classes, immutable value state and controlled named calls; preserve current public native parameter names and extension behavior.

## Unreleased — baseline v1

- Схема: набор(ы) `tenant`, baseline v1. Baseline заменяет невыпущенную upgrade-цепочку; новая установка использует финальную схему.

## Unreleased — P07.4

- Schema delivery now uses tagged `SchemaSetProvider` declarations and `corex:schema:migrate`; migration publishing is removed.

- Introduced the optional AzGuard bridge for native CoreX Access authorization.
- Moved the AzGuard context guard, permission layer, grant source, catalog builder, and role model adapter out of `corex-access`.
- Added synchronous matrix mirroring and the tenant-local `aut_azguard_role_links` table.
