# P16 — bounded external evidence

## 1. Boundary

Perplexity browser MCP was used for one narrow comparison of five panel ecosystems and one
focused MoonShine follow-up. It does not define CoreX package structure. Official Composer docs
were checked directly for the two autoload facts used by the design.

## 2. Panel vocabulary

| Ecosystem | Official primary term | Role relevant to CoreX |
|:--|:--|:--|
| Filament | Panel, Resource, Page, Dashboard, Navigation | Panel is the aggregate shell; Resource/Page are children. |
| Laravel Nova | Resource, Dashboard, Menu | No equivalent public Panel aggregate is required. |
| Orchid | Screen, Menu | Screen is a page-level unit, not a registry of headless descriptors. |
| Backpack | admin panel, CRUD panel | CRUD panel is model/operation-oriented; admin panel is the application area. |
| MoonShine | admin panel, Resource, Page, Dashboard, Menu | Panel names the shell/product; pages/resources remain child concepts. |

RAG:✅ official URLs and bounded MCP capture are in `artifacts/P16-design-rag/P16-Q1.md`.
The terms are inconsistent across frameworks; this supports an explicit CoreX definition rather
than an ecosystem-driven rename. D93 therefore keeps `panels` and forbids importing UI framework
concepts into its 11-file headless contract.

## 3. Composer facts

RAG:✅ Composer's schema allows one PSR-4 prefix to point at multiple directories and states that
`autoload-dev` is root-only. The former permits physical subsystem grouping with stable FQCN;
the latter forbids relying on `corex/core` dev autoload from another split package. Capture and
links: `artifacts/P16-design-rag/P16-Q2.md`.

## 4. What external evidence does not decide

Thresholds, package profiles, folder names, use-case spans, item boundaries, CoreX FQCN and the
Testing migration are derived from repository code and accepted plan decisions. No external code
or documentation is vendored.
