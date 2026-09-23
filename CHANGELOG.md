# Changelog

Все заметные изменения монорепозитория CoreX документируются здесь. Формат — [Keep a Changelog](https://keepachangelog.com/en/1.1.0/).

## [Unreleased]

### Fixed

- P5.16: PostgreSQL-миграции messaging создают outbox/inbox по настроенным именам таблиц; цели raw partial indexes безопасно экранируются грамматикой соединения.
- P5.15: `corex/core` объявляет публичными корнями только `CoreX\Messaging\Contracts` и `CoreX\Messaging\Data`; команды `PruneOutboxMessages`, `RelayOutbox` и `ShowDeliveryHealth` помечены `@internal` и не входят в поддерживаемый API. R4b читает class-level docblock у `final` / `abstract` / `readonly` типов.
- P5.14: `corex/wakeup` связывает внутренние PSR-20 часы `SystemClock` только если контейнер ещё не содержит `ClockInterface`, поэтому `corex/notifications` резолвит wakeup без `CoreServiceProvider`; заранее переданный `FrozenClock` остаётся тем же экземпляром.
- P5.13: PostgreSQL-тесты materialization в `tasks-workflow` явно привязаны к `PgTestCase` и группе `pg` при запуске из корневого Pest.
- P3.3: гейт графа пакетов — правила R4 (импорт вне `public-api`), R4b (незаявленная поверхность со снимком `package-graph-undeclared-surface.json`), R5 (направление `package-type`); чтение `extra.corex` в `discoverPackages`; allowlist `public_api_exceptions` (176 frozen edges).
- P3.2: гейт графа пакетов — сбор рёбер через `token_get_all` (group-use, FQCN, extends/implements, traits); слепое пятно `parseUseImport` снято (G-GATE-BLIND). снят `scripts/.plan-close-deferred`; CI на `academici/corex-mirror` — stancl provider staging, PG test DB prep, Meilisearch в coverage job, jobs auth/tasks/tenancy; публичный AzGuard VCS (`axiomasoft/azguard`) без секретов.
- Гейт графа пакетов: allowlist проверяет содержимое `guard_file` по `guard_contains`; host-reserved namespace валидируется по трём условиям §2.3; резервации и именованные исключения печатаются в отчёте (P12A.4, A1).
- `corex/core`: `DataScope` больше не несёт `Closure $authorize` — проверка доступа через контракт `DataScopeAuthorizer`, резолвимый на исполняющей стороне; объект полностью сериализуем (P12.8, G-API/D41).
- `corex/intl`: `IntlModule` приведён к контракту `CorexModule` — discovery модулей больше не падает на `InvalidManifestException`.
- `corex/core`: контракт `TenancyManager` больше не импортирует реализацию из `corex/tenancy`.
- `corex/search`: коллизия биндинга `SearchProvider` снята — единый реестр драйверов `SearchManager`, fail-closed конфигурация (P12.5).
- `corex/attributes`: обязательная schema-валидация в store-движках; nullable `FieldCatalogue` больше не пропускает writes (P12.7).

### Added

- P7.5: workspace и OIDC-контроллеры читают `ImpersonationPolicy` /
  `TenancyOptions` вместо `config()`.
- P7.4: lifecycle, host, template integrity и membership читают
  `AccountLifecyclePolicy` / `TenancyOptions` вместо `config()`.
- P7.3: impersonation/status HTTP читают `ImpersonationPolicy`,
  `StatusGatePolicy` и `TenancyOptions` вместо `config()`.
- P7.2: `TenancyOptions` и три policy связываются в `TenancyServiceProvider`;
  `oidc.client_secret` типизирован как `SecretRef`, boot-валидация накапливает
  нарушения через `InvalidConfiguration`.
- P7.1: `CoreOptions` и `SecretRef` связывают снимок `corex` в `Config`
  при загрузке провайдера. Saved views берут явное соединение из
  конструктора и текущий default на каждую операцию.
- P6.5: канонический `autoload-dev` `CoreX\Testing` → `src/Testing`
  в `corex/core` и arch-гейт R-T1. Production autoload сохранён до P16 (D33).
- P6.4: roadmap candidates money package and departments level-1
- P6.3: arch-гейт `tests/Arch/CoreLitmusTest` проверяет шесть условий
  литмуса только для Foundation. Условие 3 закрыто правилом R-L3,
  кроме трёх путей `LITMUS_ELOQUENT`.
- P6.2: корневой arch-гейт `tests/Arch/CoreZoneTest` (R-Z0, R-Z1, R-Z2).
  `PubSub/` входит в Messaging по пути; FQCN не двигается (D32). `MessagingZoneTest` поглощён.
  Шесть публичных импортов закрыты по паре «файл + символ» (D116), матрица зон не открыта.
- P6.1: `docs/architecture/core-zones.md` — нормативная карта десяти внутренних зон `corex/core`, Composition/Testing, полный текущий контур 146 PHP-файлов, закрытые исключения и целевые FQCN для P15.
- P5.11: arch-гейт `tests/Arch/MessagingZoneTest` (R-M1 закрытый allow-list импортов зоны `Messaging`, R-M2 запрет `Messaging\Internal` вне composition root); `findings/P5-zone-map.md` для P6.
- P5.8: `corex/core` — контракт `Inbox`, `SqlInbox::claim()` (`insertOrIgnore` / MSG-006, только внутри транзакции), `InboxRefused`; `InboxClaimTest` §7.4(1–4), pg `InboxConcurrencyTest` §7.4(5).
- P5.12: pg-доказательства `DeliveryGuaranteesTest` (шесть witnesses §12: at-least-once после падения relay, идемпотентный inbox-потребитель, конкурентный lease, атомарность outbox+бизнес, durable handoff, bounded reclaim/dead); фикстура `RecordingPublisher`.
- P5.6: `corex/core` — контракт `OutboxRelay`, `SqlOutboxRelay` (короткий lease со `SKIP LOCKED`, fence-отметки, backoff, dead-letter), `LeasedMessage`, `OutboxLeaseLost`; pg-тесты §5.11.
- P5.10: `corex/core` — `DeliveryHealth`, `SqlDeliveryHealth`, команда `corex:messaging:health` (probe `--max-age`); pg-тесты §9.4.
- P5.9: `corex/core` — команда `corex:messaging:prune` (два окна retention outbox, inbox не трогается); тесты §8.
- P5.7: `corex/core` — контракты `MessagePublisher` / `DurableMessagePublisher` (durable-ack, без lossy default), команда `corex:messaging:relay` с кооперативной остановкой; тесты §6.3.
- P5.5: `corex/core` — контракт `Outbox`, `SqlOutbox::append()` в открытой транзакции (`OutboxRefused` / `MSG-008`, `MSG-007`), `scoped`-привязка; тесты sqlite и pg-отката.
- P5.4: `corex/core` — tenant DDL `sys_outbox_messages` / `sys_inbox_messages` (частичные индексы горячего пути), блок `corex.messaging` и аксессоры в `Support/Config`; записи владения вне `DB_SCHEMA.md` FROZEN v1.0.
- P5.2: `corex/core` — словарь messaging: `MessageEnvelope`, `MessagePayload`, `MessageContract`, `PiiClass`, `MessageClass`, `InvalidMessage` (канонизация payload, `DurablePayload` на конверте).
- P5.3: `corex/core` — `MessageContractRegistry`, `ArrayMessageContractRegistry`, `UnknownMessageContract`; пустой реестр по умолчанию в `CoreServiceProvider` (`MSG-002`).
- P5.1: `docs/architecture/message-policy.md` — типы и пределы полей конверта, процедура объявления интеграционного контракта, не-обещания (порядок, at-least-once, `MSG-008` vs `afterCommit`), форма потребителя, retention outbox/inbox.
- P4.12: Arch-гейт `SingletonInjectionTest` + `SingletonInjectionGate` — запрет `Application` / `Request` / `Config\Repository` в конструкторах классов, зарегистрированных через `->singleton(` с литералом `::class`; allowlist `scripts/singleton-injection-allowlist.json`; аудит `findings/P4-binding-audit.md`.
- P4.9: `corex/core` — зависимость `psr/clock` и `provide` `psr/clock-implementation`; `SystemClock`, `FrozenClock`, `LaravelClock` (`CoreX\Support\Time`); singleton-привязка `Psr\Clock\ClockInterface` → `LaravelClock`.
- P4.10: `corex/wakeup` — durable-пути `SweepRunner` и `SqlWakeupScheduler` на инъектированном `Psr\Clock\ClockInterface`; тесты backoff и границы lease через `FrozenClock`.
- P4.8: `corex/core` — контракт `DataScopeCodec`, `ArrayDataScopeCodec` (versioned wire-массив с фиксированным порядком ключей), `InvalidScopePayload`; singleton-привязка в `CoreServiceProvider`.
- P4.2: `corex/core` — контракт `TenantContextRestorer`, `GuardedTenantContextRestorer` (сверка скоупа с активным контекстом, D22) и `ScopeContextMismatch`; `scoped`-привязка в `CoreServiceProvider`.
- P4.1: `corex/core` — транзитная форма `DataScope`: публичные скаляры `accountId`, `workspaceId`, `boxed`, `version`, константа `VERSION` и именованный конструктор `fromTenantContext()`; поле `TenantContext $context` сохранено до P4.7 (D21).
- P3.7: Arch-тесты инвариантов A57/A61/A99 и гейт владения таблиц `scripts/table-ownership-gate.php` (T1/T2/T4; DATA-003 вне скоупа — D19).
- P3.6: гейт имён публичного API (`scripts/naming-gate.php`, снимок `naming-gate-grandfather.json`) и наблюдательный гейт `config()` вне composition boundary (`scripts/config-composition-gate.php`, `composition-boundary.json`; N12 allowlist для `Support/Config.php`).
- P3.5: маркер `CoreX\Contracts\DurablePayload` и PHPStan-правило `corex.durablePayload.nonSerializableProperty` на объявленных свойствах помеченных типов (deny-list; `array<mixed>` — предел до P4).
- P3.4: Roave BC-дифф — `roave/backward-compatibility-check` ^8.22, `scripts/bc-check.sh`, `composer bc-check`, CI job `backward-compatibility` на PHP 8.4; blocking только для `stability=stable` (D98).
- P3.1: контур PHPStan — `scripts/phpstan-contour.php`, `composer analyse:packages`, `phpstan-contour.json` (20 пакетов); корневой `phpstan.neon` расширен с 7 до 19 зелёных `src` (debt: `corex/attributes`).
- P2.6: базовый архитектурный scorecard пакетов (docs/architecture/scorecard.md).
- P2.4: реестр конфигурации (docs/architecture/configuration.md) — 161 ключ из 18 config-файлов, 17 undeclared-read, CFG-006 exception для corex-intl-search.
- P2.3: реестр владения таблицами (docs/architecture/table-ownership.md) — 99 таблиц, 4 пакета-владельца sys_*, DATA-004 exception aut_role_assignments.
- P1.7: реестр референсов, отклонённые решения, README каталога architecture (docs/architecture/references.md, docs/rejected-optimizations.md, docs/architecture/README.md).
- P1.6: четыре политики CFG/DATA/MSG/EXT — классификации, процедуры, конверт и реестры (docs/architecture/config-policy.md, data-ownership-policy.md, message-policy.md, extension-policy.md).
- P1.5: политика публичной поверхности — три носителя, граница `@internal` vs пакет, BC-список (docs/architecture/api-policy.md).
- P1.2: схема `extra.corex` и заполнение 20 `composer.json` (docs/architecture/taxonomy.md).
- P1.3: словарь имён и реестр переименований (docs/architecture/naming.md, rename-registry.md).
- P1.4: профили структуры A/B/C и назначение по пакетам (docs/architecture/structure-profiles.md).
- P1.1: архитектурная конституция — 58 статей, 22 анти-цели, каталог исключений (docs/architecture/constitution.md).
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

- **BREAKING** `corex/core`: `DataScope` — скалярный versioned конструктор, удалено публичное свойство `TenantContext $context`, тип помечен `DurablePayload`; PHPStan-правило `corex.durablePayload.untypedArrayProperty` отвергает `array` без типа значения и `array<mixed>` (P4.7, D6/D21, G-API).
- PLAN1 `2026.09.15-№1-COREX-FLEXCRM-REMAINING` перенесён в `plans/archive/` после терминального закрытия; скрипты `remaining-cohort-*` и `plans/ACTIVE.md` указывают на архивный путь.
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
