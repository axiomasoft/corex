# Changelog

## Unreleased — P07.4

- Schema delivery now uses tagged `SchemaSetProvider` declarations and `corex:schema:migrate`; migration publishing is removed.

- Introduced the optional AzGuard bridge for native CoreX Access authorization.
- Moved the AzGuard context guard, permission layer, grant source, catalog builder, and role model adapter out of `corex-access`.
- Added synchronous matrix mirroring and the tenant-local `aut_azguard_role_links` table.
