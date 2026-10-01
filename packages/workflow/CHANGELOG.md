# Changelog

## Unreleased — PHP surface strict cutover

- Breaking (PLAN3.P04, pre-1.0): supported PHP symbols are declared individually with Api/Spi attributes. Concrete implementation types moved to Internal and typed ports/DTOs and error contracts replace predecessor surfaces as recorded in PLAN3.D63/D64; namespace metadata no longer grants compatibility.
- Generated `resources/contracts/php-api.json` records supported layers and signatures. Strict source/graph/BC checks enforce the declared surface; Internal has no external BC promise.

## Unreleased

- Pin workflow trigger intents to definition id and version inside the existing payload. Registry fingerprint is stored provenance and is excluded from the replay hash. Legacy non-empty refs keep the `engine-binding/1` comparison and are not rewritten.
- Add the initial `engine-binding/1` ports and durable intent store.
