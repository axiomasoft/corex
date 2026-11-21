# Changelog

## [Unreleased] — localized Search projection

- Move `LocalizedFieldBinding` to `CoreX\SearchIntl\Projection\Data` and replace its `column` constructor argument with the underlying stable `fieldId`.
- Implement `LocalizedSearchValues::locales` to supply every locale shape and fallback chain for the current entity. `LocalizedFieldBinding::shapeId` creates a semantic query ID from the stable field and a canonical Intl locale.
- Compile bounded digest keys for the backend and check both field IDs for each Search use. Locale fallback and projection mapping changes invalidate the generation fingerprint.

## Unreleased — Packman governance

- Adopt Packman API/SPI attributes and shared configuration/error contracts directly, with explicit Internal classification and no compatibility aliases. Declare package ownership in `extra.packman` and the monorepo manifest.


## Unreleased — Laravel 13 platform (P09.9, D132)

- Require Laravel 13 and PHP 8.4+; support PHP 8.4–8.5. Laravel 12 support is removed. Testbench uses the Laravel 13 family; package versions and runtime contracts are unchanged.

## Unreleased — PHP 8.4 modernization (P09.4)

- Modernize maintained PHP for PHP 8.4 with final concrete classes, immutable value state and controlled named calls; preserve current public native parameter names and extension behavior.
- Remove verified unused Internal helpers: `TranslationRevision::nextRevision`.

## Unreleased — typed Options and runtime configuration checks (P05.1)

- Added internal `SearchIntlOptions`, a lazy singleton resolved in `boot()`.

## Unreleased — kernel package and API migration

- Breaking (PLAN3.P02–P04, pre-1.0): The bridge exposes SavedViewLabelReader and LocalizedFieldBinding with its own provider; label decoration is separate from the host search and localization implementations.
- Package installation uses the `axiomasoft` Composer vendor and PHP 8.4–8.5 / Laravel 12–13 constraints; `resources/contracts/php-api.json` records the explicitly attributed API and SPI. Old names have no compatibility aliases.

## Unreleased — PHP surface strict cutover

- Breaking (PLAN3.P04, pre-1.0): supported PHP symbols are declared individually with Api/Spi attributes. Concrete implementation types moved to Internal and typed ports/DTOs and error contracts replace predecessor surfaces as recorded in PLAN3.D63/D64; namespace metadata no longer grants compatibility.
- Generated `resources/contracts/php-api.json` records supported layers and signatures. Strict source/graph/BC checks enforce the declared surface; Internal has no external BC promise.

## Unreleased — package identifier alignment

- Breaking before 1.0: Rename configuration and publish identifiers to `corex-search-intl` and the enabled variable to `COREX_SEARCH_INTL_ENABLED`.

## [Unreleased]

- PHP API manifests now record direct inheritance, interfaces and traits separately and include only members declared by this package; external dependency members are omitted without changing the `php-api/1` schema.

### Changed

- Hard rename from `corex/intl-search` / `CoreX\IntlSearch\` (P12.6).

### Added

- Optional localized search schema/hydration bridge over `corex/search`
- Saved-view label metadata reader over `TranslationReader`
- Translation revision helper for P6 index dirty/outbox integration
