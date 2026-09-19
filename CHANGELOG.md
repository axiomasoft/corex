# Changelog

Все заметные изменения монорепозитория CoreX документируются здесь. Формат — [Keep a Changelog](https://keepachangelog.com/en/1.1.0/).

## [Unreleased]

### Fixed

- PLAN-CLOSE-CI: снят `scripts/.plan-close-deferred`; CI на `academici/corex-mirror` — stancl provider staging, PG test DB prep, Meilisearch в coverage job, jobs auth/tasks/tenancy; публичный AzGuard VCS (`axiomasoft/azguard`) без секретов.
- Гейт графа пакетов: allowlist проверяет содержимое `guard_file` по `guard_contains`; host-reserved namespace валидируется по трём условиям §2.3; резервации и именованные исключения печатаются в отчёте (P12A.4, A1).
- `corex/core`: `DataScope` больше не несёт `Closure $authorize` — проверка доступа через контракт `DataScopeAuthorizer`, резолвимый на исполняющей стороне; объект полностью сериализуем (P12.8, G-API/D41).
- `corex/intl`: `IntlModule` приведён к контракту `CorexModule` — discovery модулей больше не падает на `InvalidManifestException`.
- `corex/core`: контракт `TenancyManager` больше не импортирует реализацию из `corex/tenancy`.
- `corex/search`: коллизия биндинга `SearchProvider` снята — единый реестр драйверов `SearchManager`, fail-closed конфигурация (P12.5).
- `corex/attributes`: обязательная schema-валидация в store-движках; nullable `FieldCatalogue` больше не пропускает writes (P12.7).

### Added

- P8.1 source qualification: `findings/P8-source-qualification.md` (QUAL-01–05), delivery `tool-source-qualification-20260915/`; кандидат `opis/json-schema` 2.4.1 квалифицирован, не установлен в root vendor.
- P8.2 tool declarations: `Api::tool` / `Api::exposeTool` в `corex/modules`, `ToolDescriptor` и bounded `tool-schema/1` validation (`ToolSchemaSupervisor` + optional Opis) в `corex/api`; `findings/P8-descriptors.md` (DESC-01–09).
- P8.3 tool catalogue/policy: `ToolCatalogue`, `ToolPolicy`, `CompiledToolCatalogue`, `DenyToolPolicy`, `ToolServiceProvider` в `corex/api`; current-policy discover без authority cache; `findings/P8-catalogue-policy.md` (CAT-01–09).
- P8.4 guarded invocation: `ToolInvoker`, `ToolExecutionGuard`, `GuardedToolInvoker`, `ReadOnlyToolExecutionGuard`, `ToolCall`, `ToolResult`, `ToolRequestContext` в `corex/api`; schema/contract-hash guarded headless invoke; `findings/P8-invocation.md` (INVOKE-01–12).
- P8.5 registry introspection: `RegistryIntrospection`, `RegistryMap`, `ModuleExplanation`, `ReadRegistryIntrospection` и CLI `corex:modules:why` в `corex/modules`; bounded extension-point map и read-only activation diagnostics; `findings/P8-introspection.md` (INTRO-01–07).
- P8.6 contribution bindings: `packages/api/docs/tool-integration.md`, neutral `ArchiveToolModule`/`LabToolModule` fixtures, `ToolContributionsTest`; selected P3–P7 bindings и external19 ownership map; `findings/P8-contributions.md` (CONTRIB-01–08).
- P8.7 installed tool seal: `McpToolsProjection`, `InstalledToolContractTest`, delivery `tool-install-qualification-20260915/`; cold ZIP install + fake MCP parity; `findings/P8-installed.md` (INST-01–06) и `findings/P8-tool-receipts.md` (p8-tool-receipts/1 seal).
- P9.2 cohort dist export: `scripts/distribution/remaining-cohort-export.py`, `remaining-cohort-policy.json`, delivery `remaining-cohort-20260915/build/` + `public/packages.json`; `findings/P9-artifacts.md` (DIST-01–08).
- P9.3 local matrix: `scripts/distribution/remaining-cohort-quality.py`, `remaining-cohort-runner.py`, `remaining-cohort-publish.py`, `.github/workflows/remaining-cohort-proof.yml`, CI `packages-tests` aggregator; delivery `local/matrix.json` + `runner-plan.json`; `findings/P9-local-matrix.md` (MATRIX-01–07).
- P9.4 installed candidate: `scripts/distribution/remaining-cohort-installed.py`, `remaining-cohort-private.py`, neutral lab fixtures, delivery `installed-candidate/receipt.json`, `runner-packet.json`, `private-read-packet.json`; `findings/P9-installed-candidate.md` (INSTALL-01–09).
- P9.5 hosted cohort proof: `scripts/distribution/remaining-cohort-hosted.py`, `stage-stancl-providers.py`, mirror execution on `academici/corex-mirror`; `findings/P9-hosted-runs.md` (RUN-01–07).
- Печать корректирующей фазы P12A: `findings/P12A-correction-receipts.md` (`p12a-correction-receipts/1`); разблокировка P8 без автозапуска (P12A.6).
- Исполняемый гейт графа пакетов: `scripts/package-graph-gate.php` + `tests/Arch/PackageGraphTest.php` (R1 undeclared dependency, R2 foreign `Internal`, R3 namespace ownership); конфиг `scripts/package-graph-gate.json`; отчёт `artifacts/P12-package-graph/report.json` (P12.3).
- Триада разбора аудитов 2026-09-17: `docs/audit-2026-09-17.md`, `docs/audit-2026-09-17-remediation.md`, `docs/audit-2026-09-17-deferred.md` — ledger девяти отложенных дефектов и пяти отклонённых идей (P12.9).
- Печать фазы P12: `findings/P12-rebaseline-receipts.md` (receipt'ы P12.1–P12.9).

### Changed

- `phases/P9/P9.2.md`: пути `Files` обновлены с `import-export`/`intl-search` на `transfer`/`search-intl` после rename P12.4/P12.6 (P12A.6, A8).
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
