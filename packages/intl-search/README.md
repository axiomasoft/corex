# corex/intl-search

Optional localized projection glue between `corex/intl` and `corex/search`. Disabled by default; no network or index work at boot.

## Capabilities

- `LocalizedSearchSchema` wraps an existing `SearchSchema` with locale-resolved searchable columns
- `LocalizedSearchHydrator` resolves localized result fields through `TranslationReader`
- `SavedViewLabelReader` resolves saved-view `localizedName` metadata without changing filter grammar
- `TranslationRevision` computes content revisions for the existing P6 dirty/outbox lane

## Installation

```bash
composer require corex/intl-search
```

Enable explicitly in configuration:

```php
'corex-intl-search.enabled' => true,
```

## Testing

```bash
composer --working-dir=packages/intl-search test
```
