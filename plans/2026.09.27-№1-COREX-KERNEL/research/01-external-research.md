# R1 — Внешний ресерч (синтез)

Дата: 2026-09-27. Инструменты: perplexity-web (`search`, `search_deep`), curl к Packagist.
Дословные ответы — `artifacts/research-rag/` (индекс — `artifacts/research-rag/README.md`). Ответ поисковика — провенанс,
не доказательство; утверждения, меняющие контракт, подтверждены первичным источником (`http-01`) либо помечены
`VERIFY-AT-EXEC`.

| ID | Вопрос | Вывод | Влияние | Capture |
|:--|:--|:--|:--|:--|
| R-01 | Как Symfony определяет public API | Use-based BC promise: всё не-`@internal` покрыто; `@final` = используемый, но не точка наследования; `@internal` — вне обещания | Классификация на символе, final по умолчанию (D06, D17) | `pplx-01` |
| R-02 | Как устроены модульные PHP-ядра (Shopware, Sylius, Spiral, Tempest, Bitrix D7, Filament) | Пакет = независимая capability/шов, не слой; три обещания API/SPI/internal; Shopware — машиночитаемые BC-атрибуты + PHPStan; typed config → validate → freeze; compiled registries (builder → freeze); decorators > наследование; Laravel — внешний адаптер; публичная поверхность минимальна (каждый шов — многолетний контракт) | D04–D09, D12, D16 | `pplx-02` |
| R-03 | Версии платформы | Laravel 13 — 2026-03-17 (PHP ≥8.3, security до 2028-03); Laravel 12 — security до 2027-02-24; PHP 8.4 active до 2026-12-31, 8.5 active до 2027-12-31 | PHP ^8.4, Laravel ^12\|^13 (D03); версии тест-стека — VERIFY-AT-EXEC P01 | `pplx-03` |
| R-04 | stancl/tenancy v4 | Не опубликован: Packagist `v3.10.1` (L10–13, MIT), jobpipeline `v2.0.0-rc7`; v4 документирован, код/лицензия приватного v4 не подтверждены | Снимок `dev-master` недопустим в релизе; движок за адаптером (D13) | `pplx-04`, `pplx-06`, `http-01` |
| R-05 | Supply-chain CI | Full-SHA pin + комментарий версии; `permissions: {}` + job-level; zizmor блокирующий; pinact; Dependabot github-actions; `composer audit --locked` на каждый installable корень; CODEOWNERS на workflows | D18 | `pplx-05` |
| R-06 | Typed config и providers в Laravel | `config()` только на границе интеграции → `Options::fromArray()` → singleton по типу → DI; no static mutable (Octane); CI `config:cache`; split providers по жизненному циклу за одним публичным provider; DeferrableProvider — только чистые bindings | D08, D09 | `pplx-07` |
| R-07 | Таксономия ошибок | Wire-код — строка с namespace-префиксом; enum — удобство внутри пакета (не расширяется плагинами); registry `ProblemDefinition` компилируется при boot, конфликт — ошибка; RFC 9457 `type` URI ≠ `code` ≠ status | D10 | `pplx-08` |
| R-08 | Roave BC | 8.22.0 (2026-09-16), PHP `~8.4\|~8.5`; baseline — `ignored-regex` по тексту диагностики (не исключение namespace); запуск per package с явными ref; для точного internal-исключения — собственный API-манифест | D03, D06, D17 | `pplx-09` |
| R-09 | Registry probes | Vendor `corex` занят (`github.com/corex/*`, 17 пакетов); `axiomasoft`, `axioma-studio` пусты; stancl v4 нет | D02, D13, D14 | `http-01` |
| R-10 | PHPStan internal / API-атрибуты / границы | PHPStan ≥2.1.13 + bleedingEdge: `@internal` ограничен **первым** сегментом namespace — при общем корне `CoreX\` бесполезен для границ пакетов; `#[Api]/#[Spi]` требуют собственного гейта; Deptrac/custom rule для рёбер; staged rollout report → fail | D04, D06, D17 | `pplx-10` |
| R-11 | Монорепо и релиз | Lockstep + read-only split (`danharrin/monorepo-split-github-action` v2.4.5) + metapackage; path repositories для разработки; `self.version` только для `replace` | D19 | `pplx-11` |

## Аналоги: что берём

| Источник | Приём | Где в PLAN3 |
|:--|:--|:--|
| Symfony | use-based BC, `@final`, `*\Tests\` вне обещания | D06, D17 |
| Shopware 6 | `@internal` до релиза, BC-атрибуты с PHPStan-проверкой, декорация вместо наследования, доменные строковые коды ошибок | D06, D09, D10 |
| Tempest / Spiral | typed config-объекты, bootloaders, discovery-cache в production | D08, compiled registries (EXT-004 уже есть) |
| Bitrix D7 | ядро = загрузчик модулей + EventManager | ядро владеет `Modules` (target architecture §2) |
| Laravel / Filament | read-only split, lockstep, один публичный provider, plugins per panel | D09, D19 |
| chatom PLAN2 | `#[Api]/#[Spi]`, feature modules + `Internal`, views вместо моделей, CloudEvents/Standard Webhooks/problem+json | D05–D07, D10–D11, D22 |
