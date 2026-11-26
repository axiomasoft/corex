# CoreX package module layout

**Version** 2.0.0 · **Status** normative · **Level** 0 (`axiomasoft/corex*`)

Articles STR-002 through STR-006 define a common package layout regardless of the number of files. Decisions PLAN3.D57 and PLAN3.D58 established it. The historical glossary in Git defines the terms and the closed list of kernel modules (N5). The product in `products/flex-crm` retains its own layout (N8). See the [constitution](constitution.md#str-package-structure).

## Root and feature modules

A package's `src/` directory is its root module. It contains the public categories `Contracts/`, `Data/`, `Enums/`, `Events/` and `Exceptions/`, and the bridges `Internal/`, `Laravel/`, `Testing/`, `PHPStan/` and `Tooling/`. Providers, `<Pkg>Module.php` and feature module directories also live here. Each feature module uses the same categories and bridges directly under its own root. A feature module's provider belongs in that module. Only the package root may contain `<Pkg>Module.php`.

The `axiomasoft/corex` kernel has no root module. Its `src/` directory permits only `CoreServiceProvider.php` and the approved kernel modules. Every PHP file's path must match its namespace relative to the package's PSR-4 root. See the [kernel module list](core-zones.md).

## Applying STR-002 through STR-006

- **STR-002. One use case per module.** Identify the use case and its root or feature module. Place public types in that module's public categories and implementations in its `Internal/`. Reviewers check whether the code belongs to the use case. The layout gate checks its placement.
- **STR-003. Package roots and kernel modules.** Check the PSR-4 path and the permitted categories, bridges and composition roots. Check kernel module names against the closed glossary list. `composer module-layout-gate` enforces rules R0–R3.
- **STR-004. Single-class directories.** Reviewers may accept a single-class directory if it represents a boundary that a named work item will populate. Otherwise place the class one level higher in an allowed category or bridge.
- **STR-005. Module internals.** Put `Internal/` directly under its module. `package-graph-gate` R2 prohibits references from other packages. `module-layout-gate` R5 prohibits references from other modules of the same package, except in composition roots. The permitted roots are providers, the package-root `<Pkg>Module.php` and `Laravel/` directly under the module. Nested `Internal/Laravel/` and `Data/Laravel/` do not qualify. R4 prohibits nested `Internal` directories.
- **STR-006. Public categories.** Choose `Contracts`, `Data`, `Enums`, `Events` or `Exceptions` to match the type's role and place that category directly under the module. Module-level `Support`, `Models`, `Commands` and the other directories listed in `scripts/module-layout.json` are prohibited. Implementations can use these groupings inside `Internal/`. `composer module-layout-gate` enforces rules R1–R3.

## Verification and existing exceptions

`composer module-layout-gate` scans `packages/*/src/` for R0–R5. Its configuration is in `scripts/module-layout.json`. For a full report, run `python3 scripts/module-layout-gate.py --report`. The baseline in `scripts/module-layout-baseline.json` records existing exceptions. The gate detects new violations and stale entries.

`Tooling/`, `PHPStan/` and `Testing/` provide bridges for development tools and tests. R6 prohibits references to these bridges in runtime code. The surface scanner lives in `CoreX\Foundation\Tooling`. Laravel's adapter for the current principal is `CoreX\Access\Laravel\PrincipalIdentity`. Both follow PSR-4 naming without aliases.
