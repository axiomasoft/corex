# axiomasoft/corex-search-intl

Optional localized projection glue between `axiomasoft/corex-intl` and `axiomasoft/corex-search`. Disabled by default; no network or index work at boot.

## Capabilities

- A tagged `SearchProjectionContributor` describes the locale-resolved projection fields and their permitted uses
- The host binds `LocalizedSearchValues` to supply declared fields and current translations from the record owner
- Selected values follow the host locale policy and resolve through `TranslationReader`
- `SavedViewLabelReader` resolves saved-view `localizedName` metadata without changing filter grammar
- `TranslationRevision` computes content revisions for the existing P6 dirty/outbox lane

## Installation

```bash
composer require axiomasoft/corex-search-intl
```

Enable explicitly in configuration:

```php
'corex-search-intl.enabled' => true,
```

## Testing

```bash
composer --working-dir=packages/search-intl test
```

## Failure semantics

The bridge is opt-in. When disabled, the base search path remains available without localized projection. With the bridge enabled, translation and locale fallback follow the installed intl contracts; missing localized data must not change filter grammar or grant access. Handle host search/provider failures through the base public contracts.
