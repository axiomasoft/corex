# Changelog

## Unreleased — PHP surface strict cutover

- Breaking (PLAN3.P04, pre-1.0): supported PHP symbols are declared individually with Api/Spi attributes. Concrete implementation types moved to Internal and typed ports/DTOs and error contracts replace predecessor surfaces as recorded in PLAN3.D63/D64; namespace metadata no longer grants compatibility.
- Generated `resources/contracts/php-api.json` records supported layers and signatures. Strict source/graph/BC checks enforce the declared surface; Internal has no external BC promise.

## Unreleased — package identifier alignment

- Breaking before 1.0: Publish SavedViews migrations through `corex-panels-migrations` and `corex-panels-migrations-tenant`.

## [Unreleased]

### Added

- `panel/1`: headless panel descriptor and current-context access catalogue.
