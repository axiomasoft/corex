# corex/panels

Headless, read-only **panel descriptors** for CoreX modules: named, entity-linked UI surfaces
that applications can discover and authorize in the current module/entity context. Products register
tagged `PanelProvider` implementations; `PanelCatalogue` resolves descriptors for each request and
fails closed when policy is unavailable.

## What “panel” means here

In CoreX, a **panel** is metadata about a surface (identity, entity binding, access hints)—not an
admin application, layout shell, or rendered page. The public contract is `PanelProvider` +
`PanelCatalogue` with value objects under `CoreX\Panels\Data`. This is intentionally narrower than
“panel” in several Laravel admin ecosystems, where the same word often names a full UI scope.

| Ecosystem | Primary term (official docs) | Relevance to CoreX |
|:--|:--|:--|
| Filament | Panel, Resource, Page, Dashboard, Navigation | Panel is an aggregate runtime shell; Resource/Page are children. |
| Laravel Nova | Resource, Dashboard, Menu | No required public “Panel” aggregate. |
| Orchid | Screen, Menu | Screen is a page-level unit, not a headless descriptor registry. |
| Backpack | admin panel, CRUD panel | CRUD panel is model/operation-oriented; admin panel is the app area. |
| MoonShine | admin panel, Resource, Page, Dashboard, Menu | Panel names the product shell; pages/resources remain child concepts. |

There is **no shared external term** for what this package does. Renaming to `admin`, `dashboard`,
`screens`, or `resources` would either narrow or confuse the neutral headless contract (see plan
decision D93). Bounded comparison and URLs are listed in `docs/reference.md` under design
provenance for panel vocabulary.

## Boundaries (non-ownership)

This package does **not** own and does **not** ship:

- HTTP routes or route registration
- Widgets, Blade/Livewire/Inertia UI, or any rendering pipeline
- Persistent panel registry storage or migrations
- Command/action execution or side effects
- Cached grant materialization (reads current context; no cache layer)

Applications and other packages own routing, UI, persistence, and execution. `corex/panels` only
describes and gates access to named surfaces in the active context.

## Structure (profile A, no move)

- **11** production PHP files under `src/` in layer-first layout: `Contracts/`, `Data/`,
  `Internal/`, plus module bootstrap types at the root of `src/`.
- Profile **A** (< 40 files): layer-first grouping remains appropriate; a new top-level directory
  for a single class would violate **STR-004** without a named follow-up item (P16.9 records a
  deliberate **no structural move**).

Package identity stays `corex/panels`, namespace `CoreX\Panels`, with public API
`CoreX\Panels\Contracts` and `CoreX\Panels\Data` only.

## Installation

Install with `composer require corex/panels`. The consuming application registers tagged `PanelProvider` implementations and uses `PanelCatalogue` with its current entity/module context. This package has no database migration or UI deployment step.

## Configuration

The `corex-panels.provider_tag` default is `corex.panels.providers`. Tag providers with that configured value during application composition. Panel descriptors carry application-owned identity and access hints; no routes or rendered pages are configured by this package.

## Failure semantics

Catalogue resolution fails closed when current policy/context is unavailable. Treat a missing or inaccessible descriptor as unavailable to the caller; do not infer permission from a provider tag or reuse a descriptor as a cached access grant.
