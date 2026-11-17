# axiomasoft/corex-panels

Trusted CoreX context integration for `axiomasoft/packman-panels`. Packman owns panel
catalogues, descriptors, admission and saved views. This package registers that engine and
projects authenticated CoreX user invocations into its context types.

## Installation

Install `axiomasoft/corex-panels` and register `CoreX\Panels\PanelsServiceProvider`.
The provider registers Packman's provider once. The application supplies its panel providers
and saved-view access policy through Packman's public contracts.

## Configuration

Configure `packman-panels` and use its `packman.panels.providers` tag. CoreX does not maintain
another panel configuration, registry or storage engine. Routes and rendering remain host-owned.

## Extension contracts

Use `Packman\Panels\Contracts\Registry\PanelCatalogue` and `PanelProvider` for descriptors.
Saved-view ports and values live under `Packman\Panels\SavedViews`.

`CoreX\Panels\Laravel\PanelContexts::forUser($request)` creates a Packman panel context from a trusted
`CoreX\Invocation\Contracts\RequestContext`.
`PanelContexts::savedViewsForUser($request)` creates its saved-view context. Both preserve the
current tenant and authenticated user identity. They reject agent/API-key principals rather
than reinterpreting them as users. Hosts needing another actor kind compose the upstream
context explicitly at their trusted authentication boundary.

## Failure semantics

Packman's default saved-view policy denies access. Supply `ViewAccessPolicy` before writes;
failed policy checks, unavailable entities and invalid view definitions remain errors.
A descriptor or saved view is not a cached authorization grant.

## Schema installation

`axiomasoft/packman-panels:tenant` owns `sys_saved_view_scopes` and `sys_saved_views`.
Run `php artisan packman:schema:migrate --scope=tenant --database=<tenant>` on the intended
connection. CoreX registers no duplicate schema or migrations for these tables.

## Development

The package tests exercise the CoreX context adapter and the upstream public repository on
an isolated in-memory SQLite connection. Engine-specific unit and storage tests belong to Packman.
