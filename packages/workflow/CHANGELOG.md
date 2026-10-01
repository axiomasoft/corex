# Changelog

## Unreleased — kernel package and API migration

- Breaking (PLAN3.P02–P04, pre-1.0): EngineAdapter and WorkflowBinding use typed TriggerRequest, StartResult, SignalRequest/SignalResult and transition DTOs; workflow trigger persistence is Internal and the provider owns composition.
- Package installation uses the `axiomasoft` Composer vendor and PHP 8.4–8.5 / Laravel 12–13 constraints; `resources/contracts/php-api.json` records the explicitly attributed API and SPI. Old names have no compatibility aliases.

## Unreleased — PHP surface strict cutover

- Breaking (PLAN3.P04, pre-1.0): supported PHP symbols are declared individually with Api/Spi attributes. Concrete implementation types moved to Internal and typed ports/DTOs and error contracts replace predecessor surfaces as recorded in PLAN3.D63/D64; namespace metadata no longer grants compatibility.
- Generated `resources/contracts/php-api.json` records supported layers and signatures. Strict source/graph/BC checks enforce the declared surface; Internal has no external BC promise.

## Unreleased

- PHP API manifests now record direct inheritance, interfaces and traits separately and include only members declared by this package; external dependency members are omitted without changing the `php-api/1` schema.

- Pin workflow trigger intents to definition id and version inside the existing payload. Registry fingerprint is stored provenance and is excluded from the replay hash. Legacy non-empty refs keep the `engine-binding/1` comparison and are not rewritten.
- Add the initial `engine-binding/1` ports and durable intent store.
