# axiomasoft/corex-intl

Typed locale resolution, explicit translation fallback, immutable money/rate snapshots, and offline geography reference lookup for CoreX.

## Capabilities

- `LocaleResolver` / `TranslationReader` with request-scoped locale stacks
- `CurrencyMetadata`, `RateProvider`, `RateImporter`, `CurrencyConverter`, `MoneyFormatter`
- `GeographyProvider` / `DatasetImporter` with fixture and SQL-backed providers
- `AddressSuggestProvider` optional consumer-bound address suggestion port (offline by default)

## Installation

```bash
composer require axiomasoft/corex-intl
```

Register `CoreX\Intl\IntlServiceProvider` or enable the `intl` module descriptor.

## Testing

```bash
composer --working-dir=packages/intl test
composer --working-dir=packages/intl test:pg
```

## Configuration

Configure `corex-intl.locale.supported_tags`, `app_tag` and `explicit_fallback_tags` for the application. Defaults support en/en-US/ru/ru-RU with en fallback and allow parent tags. Money defaults to the manual rate source with batches of 1000. Applications select a reference provider/dataset and provision their own reference cursor secret.

## Failure semantics

Keep fallback explicit: a missing translation follows the declared locale fallback chain. A currency conversion requires a usable rate snapshot; unavailable data is not a fabricated rate. Reference lookup is bounded and provider-owned; consumer adapters must handle unavailable datasets and invalid cursor/input failures at the application boundary.

## Typed errors

Package error identities use backed enums and a boot compiled kernel registry.
Error constructors require `ErrorCode`; descriptors keep scoped string wire codes.
See the monorepo [error contract](../../docs/reference/errors.md) for contributions,
validated replay and safe mapping.
