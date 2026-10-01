# Changelog

## Unreleased — PHP surface strict cutover

- Breaking (PLAN3.P04, pre-1.0): supported PHP symbols are declared individually with Api/Spi attributes. Concrete implementation types moved to Internal and typed ports/DTOs and error contracts replace predecessor surfaces as recorded in PLAN3.D63/D64; namespace metadata no longer grants compatibility.
- Generated `resources/contracts/php-api.json` records supported layers and signatures. Strict source/graph/BC checks enforce the declared surface; Internal has no external BC promise.

## Unreleased

- Added bounded CSV and NDJSON codecs plus typed transfer ports and checkpoint DTOs.
- Added a fenced local PostgreSQL transfer runner and neutral transfer persistence migration.
- Added sealed coherent export manifests, verified immutable NDJSON parts, current-policy output
  fencing, and an explicitly separate display-safe CSV profile.
