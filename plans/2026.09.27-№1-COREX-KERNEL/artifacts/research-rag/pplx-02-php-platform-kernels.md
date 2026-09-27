# pplx-02 — Модульные PHP-ядра: API/SPI/internal, typed config, extension points, CI-гейты

- Tool: perplexity-web `search_deep`
- Date: 2026-09-27
- Query: "Modular PHP platform kernels in 2025-2026 (Shopware 6 platform, Sylius 2, Spiral Framework, Tempest framework, Bitrix D7, Laravel package ecosystems like Filament/Spatie): how do they (1) split packages by bounded context and layer (contracts/engine/bridge), (2) classify public API vs extension SPI vs internal (annotations, PHP attributes, Shopware @internal/@final deprecation tooling), (3) implement typed immutable configuration without static globals, (4) design extension points (plugin manifests, compiled registries, decorators, events/hooks), (5) enforce architecture and BC (roave/backward-compatibility-check, deptrac, phparkitect, shipmonk composer-dependency-analyser, PHPStan rules). Concrete examples and source links; recommendations for a Laravel-based multi-package platform kernel."
- Status: provenance, not authority.

## Answer (verbatim, key sections)

The most useful common pattern is to keep business capabilities independently installable, then add framework-specific bridges and a platform composition layer above them. For a Laravel-based kernel, I’d make the contracts and public extension points deliberately small, treat boot-time discovery as compilation into immutable registries, and enforce the boundaries and public API in CI.

### 1. Package boundaries
A package split should reflect a real independent capability or integration seam—not merely create a package for every layer. For example, avoid making CatalogEngine depend on Laravel’s container or Eloquent if the point is for the catalog capability to be reusable. Conversely, don’t force a separate Contracts package for every tiny module if no independent consumer or dependency direction needs it.
A practical dependency direction is: `contracts ← domain/application ← framework bridge ← host application ↑ optional adapters`. Adapters can depend on a module’s contracts; contracts should not depend on Laravel, Filament, or the host app.

### 2. Public API, SPI, and internal code
Treat these as three separate promises: Public API (ordinary consumers call/implement; compatibility policy), Extension SPI (plugin authors implement/decorate/register; explicit versioning and lifecycle contract), Internal (may change without guarantees).
Shopware: new public code can initially be marked @internal during development, then that marker is removed when the feature is released; distinguishes genuine deprecation from planned changes to symbols that remain. Shopware’s current BC guidance makes planned API changes machine-readable with PHP attributes such as #[BecomesFinal], #[ParameterNameChange], and #[ReturnTypeNarrowing]. It distinguishes changes affecting callers from changes affecting extenders, validates announcements with PHPStan, and says stale announcements fail once their target version is released. Shopware’s docs also advise replacing inheritance with decoration or composition when a class is announced to become final.
Rules: put public API and SPI in documented namespaces; use @internal (and static-analysis rules) for implementation classes public only for wiring; final by default for concrete classes, extension via interfaces/decorators/registries; deprecate with replacement and removal version; machine-readable change attributes + CI validation.

### 3. Typed configuration and lifecycle
Tempest: configuration as objects discovered from *.config.php, injected via container, typed constructor arguments. Spiral: component config objects + bootloaders at startup. For a long-lived kernel: mutable config only while assembling, then validate and freeze into an immutable runtime configuration object — avoid singleton config as hidden mutable global. Lifecycle: load package defaults + host overrides into a builder → resolve env at the application boundary → validate once at boot → bind immutable config by type → inject into services that need it. No env()/static config facades in core/domain code; Laravel config facilities in the integration layer only.

### 4. Extension points
Manifests/Composer metadata (Tempest extra.tempest.can-discover), bootloaders/service providers (Spiral, Filament plugins per panel), decorators (Shopware — preferred service-extension mechanism), events (Bitrix D7 EventManager), compiled registries (Tempest discovery cache in production). Compiled registry: `ModuleProvider::register(ModuleRegistryBuilder)` during bootstrap → `$builder->freeze()` → runtime consumers receive the immutable registry. Keep the manifest declarative (module ID, version, required capabilities, optional dependencies, provider class). Avoid executing arbitrary plugin code during Composer autoload or request-time scanning.

### 5. CI controls and recommendation
No single checker proves the architecture. Package by capability and integration; Laravel/Eloquent/queue/Filament integrations in bridge packages; small interfaces + registries + decorators, events for notifications; compile at boot; version API separately from internals, concrete classes final; run PHPStan, Deptrac or PHPArkitect, ShipMonk dependency analyser and Roave on each public package; Laravel is the outer adapter — no facades in contracts/domain. Tradeoff: every public interface, event payload, hook, and registration point becomes a years-long contract — start with extension points that have real consumers, keep the rest internal.

## Sources
1. https://docs.sylius.com/public/what-is-sylius/architecture
2. https://filamentphp.com/docs/5.x/plugins/panel-plugins
3. https://developer.shopware.com/docs/guides/development/extensions/architecture/internal.html
4. https://developer.shopware.com/docs/resources/guidelines/code/backward-compatibility.html
5. https://tempestphp.com/main/essentials/configuration
6. https://tempestphp.com/3.x/extra-topics/package-development
7. https://developer.shopware.com/docs/resources/guidelines/code/core/decorator-pattern.html
8. https://github.com/Roave/BackwardCompatibilityCheck
9. https://github.com/shipmonk-rnd/composer-dependency-analyser
