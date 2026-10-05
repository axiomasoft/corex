# axiomasoft/corex-access-azguard

Optional AzGuard integration for `axiomasoft/corex-access`. The native Access role matrix remains authoritative. When Access compiles a role, this bridge mirrors its active permission keys into AzGuard and stores the role ID link in `aut_azguard_role_links`.

The bridge also registers AzGuard's context guard, workspace cache discriminator, matrix grant source, permission catalog builder, role model pin, and `@scope` / `@entity` Blade directives. Access works without this package.

## Installation

The package is unreleased while AzGuard has no stable published release. Configure the application-owned Composer channel for `axioma-studio/azguard-core` and install this bridge only in a consumer that opts into AzGuard. Composer repositories in a dependency are not inherited by that consumer.

Publish and apply `corex-access` tenant migrations, then publish and apply `corex-access-azguard-migrations-tenant`. The bridge migration creates `aut_azguard_role_links`; it does not change `aut_roles`. Publish `corex-access-azguard-config` to change the AzGuard pins or disable this package's singular `PermissionLayer` binding when another integration owns it.

The listener runs synchronously inside Access compilation's tenant transaction. An exception rolls back native compilation and the mirror. A missing role is an error. Without this bridge, Access compiles and evaluates permissions through its own data.
