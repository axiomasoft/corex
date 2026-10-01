# Changelog

## Unreleased — PHP surface strict cutover

- Breaking (PLAN3.P04, pre-1.0): supported PHP symbols are declared individually with Api/Spi attributes. Concrete implementation types moved to Internal and typed ports/DTOs and error contracts replace predecessor surfaces as recorded in PLAN3.D63/D64; namespace metadata no longer grants compatibility.
- Generated `resources/contracts/php-api.json` records supported layers and signatures. Strict source/graph/BC checks enforce the declared surface; Internal has no external BC promise.

## Unreleased — package identifier alignment

- Breaking before 1.0: Rename configuration and publish identifiers to `corex-search-intl` and the enabled variable to `COREX_SEARCH_INTL_ENABLED`.

## [Unreleased]

### Changed

- Hard rename from `corex/intl-search` / `CoreX\IntlSearch\` (P12.6).

### Added

- Optional localized search schema/hydration bridge over `corex/search`
- Saved-view label metadata reader over `TranslationReader`
- Translation revision helper for P6 index dirty/outbox integration
