# axiomasoft/corex-access-azguard

An optional adapter from CoreX access contracts to **AzGuard 2**. AzGuard owns roles,
grants, policy decisions and query visibility. The adapter owns identity mapping and
checks that the requested CoreX entity is active. It neither mirrors grants nor reads
AzGuard tables.

## Installation

Install `axiomasoft/corex-access-azguard` with `axiomasoft/azguard:^2.0` through your
application's configured release channel. Register the CoreX Access provider, AzGuard's
provider and `AccessAzguardServiceProvider`. Register an AzGuard panel whose ID matches
`corex-access.panel` (default `app`). The application defines permissions, roles,
policies and their query adapters using AzGuard's public API.

A CoreX entity/action pair such as `crm.deal` / `view` becomes the local permission
`crm.deal.view` in that panel; its canonical AzGuard key is `app:crm.deal.view`.
The adapter binds both `AccessChecker` and `ScopeResolver`. Record checks call
`PanelAccess::decide()`; list queries call AzGuard's exact `Visibility::visibleTo()`.
Unsupported visibility policies raise AzGuard's exception before exposing records.
Existing caller query constraints remain in effect.

## Context mapping

The default `AccessContextMapper` maps:

- principal type and ID to `SubjectRef` (`user`, `agent`, `api_key`);
- a trusted account to `TenantRef::of('account', $id)`;
- an active workspace to `AssignmentScopeRef::of('workspace', $id)`;
- a `RecordContext` supplied to a list query to its explicit assignment scope;
- a boxed installation to the global tenant.

An absent CoreX account context denies access; it is never treated as global. Configure
AzGuard's accepted subject types, tenant policy and assignment-scope definitions to
match these identities. Hosts with different aliases may bind the public
`CoreX\AccessAzguard\Contracts\AccessContextMapper` interface before this provider.
The mapper reads current context on every call and carries no worker-local authorization
cache. It never authenticates a request or accepts a tenant ID from user input.

## Migration from the unreleased bridge

This replaces the old AzGuard 0.3 integration. The removed `azguard_pins`, permission
layer, Blade directives and matrix mirror are not supported by AzGuard 2. The bridge
has no schema or migration. Existing unpublished databases may retain the unused
`aut_azguard_role_links` table until their owner deliberately removes it; the adapter
never drops existing data. Migrate role/grant definitions using AzGuard's public
change API before selecting the new provider in an existing application.

`AccessChecker::scopeFor()` cannot express arbitrary AzGuard policies using CoreX's
finite legacy `Scope` enum. For an active context it raises
`ScopeProjectionNotSupported`; use `canOnRecord()` or `ScopeResolver::apply()` instead.
It does not approximate a policy with account-wide access. Installing this adapter
selects AzGuard as the authorization engine; native matrix changes no longer affect
its decisions.
