# Changelog

Все заметные изменения монорепозитория CoreX документируются здесь. Формат — [Keep a Changelog](https://keepachangelog.com/en/1.1.0/).

## [Unreleased]

### Fixed

- `corex/core`: `DataScope` больше не несёт `Closure $authorize` — проверка доступа через контракт `DataScopeAuthorizer`, резолвимый на исполняющей стороне; объект полностью сериализуем (P12.8, G-API/D41).
- `corex/intl`: `IntlModule` приведён к контракту `CorexModule` — discovery модулей больше не падает на `InvalidManifestException`.
- `corex/core`: контракт `TenancyManager` больше не импортирует реализацию из `corex/tenancy`.
- `corex/search`: коллизия биндинга `SearchProvider` снята — единый реестр драйверов `SearchManager`, fail-closed конфигурация (P12.5).
- `corex/attributes`: обязательная schema-валидация в store-движках; nullable `FieldCatalogue` больше не пропускает writes (P12.7).

### Added

- Исполняемый гейт графа пакетов: `scripts/package-graph-gate.php` + `tests/Arch/PackageGraphTest.php` (R1 undeclared dependency, R2 foreign `Internal`, R3 namespace ownership); конфиг `scripts/package-graph-gate.json`; отчёт `artifacts/P12-package-graph/report.json` (P12.3).
- Триада разбора аудитов 2026-09-17: `docs/audit-2026-09-17.md`, `docs/audit-2026-09-17-remediation.md`, `docs/audit-2026-09-17-deferred.md` — ledger девяти отложенных дефектов и пяти отклонённых идей (P12.9).
- Печать фазы P12: `findings/P12-rebaseline-receipts.md` (receipt'ы P12.1–P12.9).

### Changed

- `corex/attributes`: имена таблиц через иммутабельный `AttributeTables`; обязательная schema-валидация в store-движках; `corex/modules` перенесён в `suggest` с guarded fallback (P12.7).
- `corex/search`: `SearchManager` с реестром драйверов (`null`, `postgres`); fail-closed конфигурация; дефолт `corex-search.driver` — `null` (P12.5).
- `corex/search-meilisearch`: неймспейс `CoreX\Search\Meilisearch\`; драйвер регистрируется через `SearchManager::extend()` вместо биндинга `SearchProvider`; ключ `enabled` удалён (P12.5).
- Пакет `corex/search-intl`: hard rename из `corex/intl-search` (PSR-4 `CoreX\SearchIntl\`; P12.6).
- Пакет `corex/transfer`: hard rename (PSR-4 `CoreX\Transfer\`, каталог `src/Data/` вместо `src/Support/`, конфиг `corex-transfer.*`; P12.4).
- `corex/intl`: в `require` добавлен `corex/modules` (наследование `CorexModule`).
- `corex/tasks-workflow`: в `require` добавлен `corex/workflow` (фактические импорты `CoreX\Workflow\*`).

### Deferred (named, not resolved — P12.9)

- Presence-семантика `ValuePatch`/`ValueBag`; разделение `DatabaseTransferRunner` (TR2–TR4); полная декомпозиция `DataScope`; сокращение `corex/core`; вынос `config()` из domain; tenant `singleton` → scoped; DTO в `Contracts/`; public API census; пин Meilisearch SDK — см. `docs/audit-2026-09-17-deferred.md`.
- Корневой `composer test:packages` расширен с 8 до 20 пакетов; `corex/core` и `corex/modules` получили скрипты `test`/`test:coverage`/`analyse`/`lint:check` (прогон через корневой `phpunit.xml` и testsuite `CorePackage`/`ModulesPackage`).
- Корневой `composer.json`: в `require` добавлены `corex/attributes`, `corex/transfer`, `corex/search-meilisearch`; в `autoload-dev` — тестовые неймспейсы пакетов без локального `vendor`.
- `corex/intl`: в манифест добавлен `tablePrefix('int')` — обязательное поле D37 для `corex:modules:compile` (таблицы пакета по-прежнему `corex_*`; выравнивание префикса со схемой — отдельная работа).
- Корневой `composer.json`: скрипт `package-graph-gate`; `corex/tasks-workflow`: привязка `RecurrenceExpander` без `use` чужого `Internal` (совместимость с R2).
