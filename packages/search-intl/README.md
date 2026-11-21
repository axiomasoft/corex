# axiomasoft/corex-search-intl

Optional localized projection glue between `axiomasoft/corex-intl` and `axiomasoft/corex-search`. Disabled by default; no network or index work at boot.

## Capabilities

- The host binds `LocalizedSearchValues` to describe the stable owner fields and every supported locale shape for a generation.
- The owner uses the exact `RecordRef` to supply current translations. `TranslationReader` resolves the selected shapes using the supplied Intl fallback policies.
- `LocalizedFieldBinding::shapeId($resolution->used)` gives the semantic ID to use in a Search query. Search projects results under this ID and resolves a separate digest key for the backend.
- Search checks the requested use against both the locale shape and its underlying stable field ID. A generation fingerprint covers the field schema, physical mapping and locale fallback policy.
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

The bridge is opt-in. With the bridge enabled, describe locale and field schemas independently of the requesting principal so index workers and Search clients share the same generation fingerprint. Duplicate semantic or physical IDs fail projection compilation. Missing or stale translations are omitted and current Search authorization controls each use.
