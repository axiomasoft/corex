# Changelog

## Unreleased — scheduling contracts (P07.5)

- Added kernel module `CoreX\Scheduling`. Moved `WakeupScheduler`, `DueWakeupSource`, `DueWakeupHandler`, `WakeupRequest`, `WakeupWindow`, `WakeupDelivery`, `WakeupHandlerDefinition`, `ScheduleResult`, `CancelResult` and `SweepResult` from `CoreX\Wakeup` with no aliases. `DueWakeupSource::TAG` is `corex.scheduling.due-sources`; `WakeupHandlerDefinition::TAG` is `corex.scheduling.wakeup-handlers`.

## Unreleased — PLAN3.P07.3 mandatory synchronous audit

- Added the `SynchronousAuditWriter` SPI and stock committed database writer for mandatory support-access events. It bypasses optional audit disablement and queues, rejects ambient transactions, and requires an event-time snapshot.

## Unreleased — PLAN3.P07.2 tenancy implementation cleanup

- TenantDatabaseLifecycle documentation refers to the physical database implementation; the kernel contract
  signatures and boxed implementation are unchanged. Module connection documentation names TenancyManager.

## Unreleased — CloudEvents wire (P06.3)

- CloudEvents 1.0 structured JSON codec with internal/external profiles; MessageContract dataSchema, MessageEnvelope source URI-reference validation and withPayload. MessagePublisher wire uses application/cloudevents+json (PLAN3.D90/D92).

## Unreleased — ports instead of facades, helpers and static state (P05.3)

- Runtime code outside the Laravel bridge takes `ConnectionResolverInterface`, `ClockInterface`, `Dispatcher` and the other `Illuminate\Contracts` ports through constructors instead of facades, global helpers and static clocks; `Messaging\Internal\PubSubManager` is gone (the `PubSub` binding selects the driver from `CoreOptions::pubsubDriver`). Added the `CoreX\Database\Laravel\ResolvesCoreOptions` SPI trait for migrations and models that read `CoreOptions` through the container bridge.

## Unreleased — thin providers and registrars (P05.2)

- `CoreServiceProvider` is the only public provider of the kernel: it registers `ModulesServiceProvider` itself (after the kernel bindings, so module-aware services refine them) and delegates bindings to per-module registrars (`Config`, `Context`, `Data`, `Messaging`, `Settings`, `Audit`, `Features`, `Departments`, `Storage`, `Time`, `Observability`, `Errors`, modules). Apps no longer need to list `ModulesServiceProvider` separately. `corex:modules:repair` is a regular command class.

## Unreleased — typed Options and runtime configuration checks (P05.1)

- Removed the static `CoreX\Config\Internal\Config` holder: `CoreOptions` is a lazy shared singleton read once at boot (`app(CoreOptions::class)` in migrations and tests; call `forgetInstance(CoreOptions::class)` after writing `corex` config in a booted test). Added `CoreOptions::$acknowledgedDangerousModes` (`corex.acknowledged_dangerous_modes`, exact config paths only), the `RuntimeConfigCheck` SPI and `RuntimeConfigGate`: checks run when the HTTP application finishes booting or a console command starts, except build commands. `corex:config:validate` runs them too, accepts `--production`, lists acknowledged dangerous modes as warnings and reports the `corex-operations.require_physical_tenancy=false` opt-out as a notice. `CoreOptionsParser::current()` is deprecated and returns the shared `CoreOptions`.

## Unreleased — CI ownership and test isolation repair (P10.2)

- Added `Messaging\Laravel\InboxRetention` for explicit cleanup of a consumer's selected inbox message IDs. Other consumers and IDs remain retained; callers must establish that the selected messages cannot be replayed.

## Unreleased — kernel package and API migration

- Breaking (PLAN3.P02–P04, pre-1.0): CoreX owns the kernel feature namespaces under one PSR-4 root; Api/Spi attributes, CoreOptions, ExtensionContribution and coded error descriptors declare the supported kernel and extension contracts. Runtime models and scanners no longer grant an implicit public contract.
- Package installation uses the `axiomasoft` Composer vendor and PHP 8.4–8.5 / Laravel 12–13 constraints; `resources/contracts/php-api.json` records the explicitly attributed API and SPI. Old names have no compatibility aliases.

## Unreleased — PHP surface strict cutover

- Breaking (PLAN3.P04, pre-1.0): supported PHP symbols are declared individually with Api/Spi attributes. Concrete implementation types moved to Internal and typed ports/DTOs and error contracts replace predecessor surfaces as recorded in PLAN3.D63/D64; namespace metadata no longer grants compatibility.
- Generated `resources/contracts/php-api.json` records supported layers and signatures. Strict source/graph/BC checks enforce the declared surface; Internal has no external BC promise.

## Unreleased — typed error contract (P04.3)

- Added package owned error enums, definitions and safe coded exception descriptors.
- Error constructors require enum identities; existing error wire values are scoped.
- Contributions are validated and frozen at boot; persisted identities use validated lookup.

## Unreleased — P04.2

- Закрыта API/SPI-поверхность: скалярные контракты, JSON-документы и снимки CoreOptions; конкретные реализации принадлежат Internal.


## Unreleased — source API metadata (PLAN3.P04.1)

- Add final readonly `CoreX\Attributes\Api` and `Spi` metadata and the `SpiKind` backed enum.
- Add a source-only API/SPI tooling scanner and deterministic `php-api/1` manifests, with nested PHPDoc, named-parameter and inheritance diagnostics.
- The report command exposes classification debt; existing namespace/package gates remain required until the strict cutover. Parser dependencies remain development-only.


## Unreleased — package identifier alignment

### Changed

- PLAN3.D57: implementation classes moved to `<Module>\Internal\…`, public types to module categories
  (`Contracts|Data|Enums|Events|Exceptions`); see `docs/architecture/constitution.md` STR-003. Drain queues before
  upgrading; stored `*_type` values holding an old model FQCN no longer resolve (no aliases, PLAN3.D01).

- Breaking before 1.0: Move module configuration into `corex.modules` within `config/corex.php`; publish it through `corex-config`, combine kernel migration tags, and rename the PubSub prune command to `corex:messaging:prune-pubsub`. Module cache identifiers use `corex.modules`.

## Unreleased

- Public promoted properties are captured even when their constructor is private. Runtime entity registry bindings now use the shared Internal implementation while the Testing SPI keeps its signatures. Parser and analyser dependencies are declared as optional development tooling.

- PHP API manifests now record direct inheritance, interfaces and traits separately and include only members declared by this package; external dependency members are omitted without changing the `php-api/1` schema. Source scanners move to the Attributes/Errors Tooling bridges and the durable payload checker moves to Messaging/PHPStan; runtime references to development bridges are forbidden.

- Added package-contributed configuration reports through `ConfigReportSection` and public messaging test fixtures. Saved Views default bindings now register with panels.

- Added immutable `DataScope`, `RecordRef`, and `WriteToken` boundary DTOs for optional data services.

Все заметные изменения `corex/core` документируются здесь. Формат — [Keep a Changelog](https://keepachangelog.com/en/1.1.0/).
Пакет pre-1.0 — breaking-изменения публичных контрактов легальны в MINOR до 1.0 (D17).

## [Unreleased]

- CloudEvents 1.0 structured JSON codec with internal/external profiles; MessageContract dataSchema, MessageEnvelope source URI-reference validation and withPayload; MessagePublisher wire now uses application/cloudevents+json (PLAN3.D90/D92, P06.3).

- PHP API manifests now record direct inheritance, interfaces and traits separately and include only members declared by this package; external dependency members are omitted without changing the `php-api/1` schema. Source scanners move to the Attributes/Errors Tooling bridges and the durable payload checker moves to Messaging/PHPStan; runtime references to development bridges are forbidden.

### Changed

- Replaced closure-based `DataScope` authorization with serializable `DataScopeAuthorizer` contract (P12.8, G-API/D41).

### Fixed

- Scoped storage rejects recursively encoded backslashes/NUL and replaced local
  root symlinks, including directory listings and usage measurement.

- Feature definitions isolate duplicate inserts in a savepoint, preserving outer
  PostgreSQL transactions and administrator toggles. Database-poll publishers
  serialize per channel until commit so durable cursors cannot skip pending rows.

### Added

- Exposed durable scope and account/workspace context types as a closed public signature graph; added the `IdentityPasswordRehasher` owner port. The guarded restorer implementation remains private.

- Declared the exact shared configuration support types and tenancy contracts used by package composition as public API; no Support namespace wildcard.

- `TenantStorage` with scoped durable/ephemeral local disks, explicit usage capability and an
  explicit unsupported-export outcome. The boxed adapter derives its root exclusively from the
  current account context and does not require tenancy, auth, Stancl, cloud storage, or archives.

- **`ImpersonationService` contract + `ImpersonationState` + boxed `NullImpersonationService`
  (P2.11, B-11 §5.9, D139/D140).** Lives in `corex/core` — the sole consumer, `CurrentActor`,
  is level 0 and must not import `corex/tenancy`; the cloud implementation rebinds via
  `Config::impersonationServiceClass()` (D10). `ImpersonationGuard` (L3 — semantic layer of the
  read-only invariant, D141) + `ImpersonationRestrictedAction` enum (closed, five cases §5.9) +
  `ImpersonationRestrictedException`. `ActorType::Support` added (D137) — `sys_audit_actor_ck`
  widened by a new additive migration (closed P1.8 file untouched). `CurrentActor` now resolves
  the impersonation contract from the container: `type()`/`id()`/`context()` distinguish a
  support actor from `user`/`system`.
- `FeatureFlagDefinition::make()` — статическая фабрика (+ `module()`/`payload()` fluent-сеттеры),
  зеркалит `SettingDefault`-канон; конструктор не тронут (P3.6).

### Changed

- Публичные тексты пакета продукт-нейтральны: composer-`description` («… for the Flex ecosystem»
  → «… for any Laravel application») и шапка `README.md` — уровень 0 доктрины (D91) не несёт
  привязки к продукту-потребителю (P2.17).
- Продуктовые докблоки публичной поверхности `Contracts/**`: убраны внутренние спека-ссылки
  (B-10 §…) из основного текста, перенесены в трейлинг `@internal spec: …`; `ModuleRegistrar`
  получил контрактный докблок (аддитивность регистрации, канонический путь динамического
  выключения через lifecycle-FSM corex/modules + рестарт воркера) (P3.6).

## [0.1.0] - 2026-07-15

Первый тегированный релиз. Реализует roadmap-фазу P0 / блупринт B-10 (примитивы ядра).

### Requires

- **PostgreSQL 16** для тенантской БД. Миграции ядра (`sys_*`) используют `ltree`, BRIN-индексы,
  `jsonb`, `UNIQUE NULLS NOT DISTINCT`, `pg_advisory_xact_lock` — PG-нативные конструкции без
  SQLite-эквивалента. SQLite-лейн (`composer test`) покрывает только pure-PHP код пакета для
  удобства unit-тестирования — это **не заявленная поддержка SQLite в продакшене** (OQ-12). Все
  гарантии целостности/конкурентности проверяются исключительно на PG-лейне (`composer test:pg`).

### Added

- Конфигурируемая ID-стратегия: `IdStrategy::Uuid7` добавлена и стала дефолтом (нативный PG `uuid`,
  time-sortable), наряду с существующими `ulid`/`int`-швами (P1.1, P1.20).
- `TenantContext`/`AccountRef`/`WorkspaceRef` — контракты для модели tenancy P2 (реализация — фаза P2) (P1.12).
- Settings v2: namespaced, scoped (`sys_settings`), обработка sensitive-значений, события изменений (P1.7);
  каскад скоупов и payload-фолбэк (P1.22).
- Audit v2: очередной `AuditEntry`, `sys_audit_log`, авто-observer, sensitive-фильтр колонок,
  killswitch-guard на шве записи, монотонный курсор `seq`, retention-шов (P1.8, P1.21).
- `DomainEvent` база (eventId/occurredAt/tenant), заменяет пустой маркер `CoreEvent`;
  `Cancellable` — отдельный опциональный интерфейс (P1.9, P1.25).
- FeatureFlags: персистентные `sys_feature_flags`/`sys_feature_overrides` вместо config-driven резолвера (P1.9, P1.22).
- Departments (`sys_departments`, `ltree`): `DepartmentRepository` с lock-then-read `move()`,
  after-commit доменные события, soft-delete (P1.10, P1.23).
- PubSub-контракт + драйверы `redis`/`db-poll`/`sync-null`, рантайм-guard, курсор/retention-шов (P1.11, P1.24).
- Новое доменное исключение `CrossWorkspaceMoveException` (guard в `Departments::move()`, P1.29).

### Changed

- Старый `EntityRegistry` (`CoreX\Registry\EntityRegistry`/`CoreX\Contracts\EntityRegistry`) **удалён** —
  заменён `EntityRegistry` v2 из `flex/modules` (см. его CHANGELOG). Breaking, легально pre-1.0 (P1.2).
- `IdColumns` эмитят нативные PG-типы по сконфигурированной стратегии (P1.20).

### Fixed

- `Audit`: sensitive-фильтр больше не пропускает помеченные атрибуты в `sys_audit_log`;
  killswitch теперь гардит непосредственно шов записи (P1.21).
- `Departments::move()`: теперь проверяет `workspace_id` нового родителя под уже взятым advisory-локом
  до перемещения — устраняет тихий cross-workspace перенос поддерева (M-2, P1.29).

### Deprecated / Will break in 0.2

- **Row-level tenancy** — `CoreX\Concerns\BelongsToTenant`, `CoreX\Scopes\TenantScope`,
  `CoreX\Contracts\TenantService`. Реализуют **опциональную row-level** мульти-тенантность и
  **замещаются физической DB-per-account моделью**, спроектированной для фазы P2 (ADR-004,
  stancl/tenancy v4). В 0.1.0 остаются рабочими (on-prem/boxed-инсталляции могут по-прежнему
  биндить `TenantService`), но новые фичи на этот шов строить не стоит — в 0.2 он будет заменён, не расширен.
- `AuditEntry`, `SettingsScope`, `TenantContext` — контракты расширены волной и будут расширены P2;
  считать их текущую форму предварительной.
- **`int`-стратегия ID** — ожидаемо конфликтует с DB-per-account (у каждой аккаунт-БД своя
  последовательность); избегать для новых tenant-scoped моделей.

### Known limitations

- Каскад скоупов `FeatureFlags` (`findOverride()`) — single-scope exact-match; narrow→wide не
  реализован (ждёт P2 tenancy).
- Secret-колонки вне `password`/`remember_token` (`api_token`, `two_factor_secret`) на
  `$guarded=[]`-моделях могут утекать в аудит, если хост не объявил `auditSensitive()`.
