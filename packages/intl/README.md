# corex/intl

Typed locale resolution, explicit translation fallback, immutable money/rate snapshots, and offline geography reference lookup for CoreX.

## Capabilities

- `LocaleResolver` / `TranslationReader` with request-scoped locale stacks
- `CurrencyMetadata`, `RateProvider`, `RateImporter`, `CurrencyConverter`, `MoneyFormatter`
- `GeographyProvider` / `DatasetImporter` with fixture and SQL-backed providers
- `AddressSuggestProvider` optional consumer-bound address suggestion port (offline by default)

## Installation

```bash
composer require corex/intl
```

Register `CoreX\Intl\IntlServiceProvider` or enable the `intl` module descriptor.

## Testing

```bash
composer --working-dir=packages/intl test
composer --working-dir=packages/intl test:pg
```
