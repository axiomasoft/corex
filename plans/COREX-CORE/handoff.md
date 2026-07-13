# HANDOFF — 2026-07-13 — after P1.9

**Next:**

| Параметр | Значение |
|:--|:--|
| Model | Sonnet |
| Thinking | medium — механика по готовому ТЗ (Routing §3: P1.10 в списке sonnet/medium) |
| Context | continue (/clear) — ручной item |
| Суть | Исполнить P1.10 (sys_departments ltree-иерархия + sys_department_user + DepartmentRepository: find/subtree/primaryOf/move с pg_advisory_xact_lock + проверкой цикла + один UPDATE по `path <@`, событие DepartmentMoved наследует DomainEvent из P1.9) — следующий item фазы, переиспользует PG тест-лейн. |

````
/task:plan-exec COREX-CORE P1.10
````

Cold-start reads: `plans/COREX-CORE/plan.md` → `plans/COREX-CORE/handoff.md` (этот файл) →
`plans/COREX-CORE/phases/P1.md` (Phase Context + item P1.10) → Required Reads P1.10 (B-10 §3.1
DepartmentRepository 351–362, §2 sys_departments/sys_department_user DDL 184–214, DB_SCHEMA.md
§3.2 439–463, B-10 §5.7 перемещение поддерева ltree 970–972, AC-24 1089, B-10 §7.3 п.3
advisory-xact-lock 1035, plan.md §5 D12, packages/core/database/migrations (стиль/ltree extension),
`packages/core/src/Events/DomainEvent.php` (P1.9, база для DepartmentMoved)). Skills: не требуются
сверх стандартных (PG-лейн уже поднят P1.6–P1.9, переиспользуется без изменений).

**Done:** План создан 2026-07-06; обе фазы детализированы до 16-польных ТЗ 2026-07-08 (26/26);
2026-07-12 двойной design-пасс (DoR-финализация + max-design, детали в Update Log plan.md).
**2026-07-13 — P1.1 закрыт:** UUIDv7 — дефолт ID-strategy (ревизия ADR-003) — детали
phases/P1.md P1.1 Completion Notes, коммит `c6b4ce0`.
**2026-07-13 — P1.12 закрыт:** контракты `CoreX\Tenancy\AccountRef`/`WorkspaceRef`/`TenantContext`
+ `TenantContextResolver` + `SingleAccountResolver`-заглушка (boxed) — детали phases/P1.md P1.12
Completion Notes, коммит `1890e56`.
**2026-07-13 — P1.2 закрыт:** EntityRegistry v2 (`get`/`has`/`all(?ctx)`/`byCapability`/`forModel`,
`EntityDefinition`/`FieldDefinition`/`Capability`/`EntityNotFound`) в новом пакете `flex/modules`
(`Flex\Modules\`); `ArrayEntityRegistry` с инъектируемым module-active предикатом (шов P1.5);
`ModulesServiceProvider` биндит конкретный синглтон + алиасит контракт (D22; `booted()`-колбэк
строит `Relation::morphMap()`). Старый `CoreX\Registry\*`/`CoreX\Contracts\EntityRegistry` удалён;
`CrmServiceProvider`/`TasksServiceProvider` переведены на точечные handles. Известное отклонение:
правка задела `packages/auth` (единственный внешний потребитель удалённого контракта), верифицирован
отдельным прогоном. Детали phases/P1.md P1.2 Completion Notes, коммит `c5fd939`.
**2026-07-13 — P1.3 закрыт:** `Flex\Modules\CorexModule` (abstract) + `Manifest` (final fluent:
`make/title/description/tablePrefix/requires/suggests/editions/components/settings/featureFlags/
listens/onEnable/onDisable/onPurge`; `extends()` — P1.4, не создан здесь) + `Dep`/`SettingDefault`
(`final readonly` фабрики, `SettingDefault` — владелец P1.3, D20) + `InvalidManifestException`
(composer-имя/tablePrefix/пустой title/не-CorexModule). `Discovery\ModuleDiscovery`: инъектируемый
`fallbackModules` в приоритете, иначе скан `vendor/composer/installed.json` (`type==corex-module`).
`Commands\CompileModulesCommand` (`flex:modules:compile`) — discovery+базовая валидация,
компиляция extenders/запись реестра — P1.4/P1.5. `ModulesServiceProvider` биндит `ModuleDiscovery`
синглтоном + регистрирует команду в `boot()`. Стаб `tests/Stubs/StubCorexModule.php` (title+
tablePrefix `stb`+1 setting) — переиспользуется P1.4–P1.6. Тесты: `tests/Feature/ModuleDiscoveryTest.php`
(8 тестов). Прогнано: `composer dump-autoload && composer lint:check && composer analyse && composer test`
— Pint passed, PHPStan 0 ошибок, Pest 56/56 (было 48/48 до item'а). Детали phases/P1.md P1.3
Completion Notes, коммит `dabf1ab`.
**2026-07-13 — P1.4 закрыт:** `Extend\Extender` (маркер) + 8 `final`-extenders строго по сигнатурам
B-10 §4.2 (`Entity`/`EntityField`/`Api`/`Filament`/`Menu`/`Permissions`/`Routes`/`Workflow`);
`Manifest::extends(list<Extender>)` добавлен. `Compiler\RegistryCompiler::compile(list<Manifest>,
list<string> $installedPackages=['corex/core']): CompiledRegistry` — детерминированный пайплайн-
константа (Entity-этап строит `array<handle,EntityDefinition>`, EntityField-этап резолвит target
против уже построенных сущностей и метит `sleeping` при кросс-модульности, 6 остальных типов —
плоский сбор); `Entity` умеет интроспекцию `$casts` модели, если `fields()` не задан.
`CompilationValidator`: tablePrefix-уникальность, requires↔installed-set, EntityField-target-handle
резолвится, `#[AsComponent]`-хук — заглушка (D6). Архитектурное решение (несущий инвариант item'а):
«спящий» extender = собран всегда при известном handle, `sleeping=true` только когда declaring-module
≠ target-owner-module; активность модуля У ТЕНАНТА компилятор не знает — это P1.5 (сверено с
findings/P1-premortem.md кластер A). `CompileModulesCommand` интегрирует компилятор после discovery.
Тесты: `tests/Feature/RegistryCompilerTest.php` (7 тестов) + фикстуры `StubEntityAlphaThing`/
`StubEntityBetaThing`/`StubRogueExtender`. Прогнано: `composer dump-autoload && composer lint:check
&& composer analyse && composer test` — Pint passed, PHPStan 0 ошибок, Pest 63/63 (было 56/56 до
item'а). Детали phases/P1.md P1.4 Completion Notes, коммит `e8111bf`.
**Known Deviation P1.4:** исполнено Sonnet/medium (пин команды `/task:plan-exec`) вместо
предписанного `plan.md` §Routing `opus/high` — риск (неоднозначная семантика «спящего»
extender'а) закрыт явной сверкой с findings/P1-premortem.md перед реализацией, не пропущен молча.
**2026-07-13 — P1.5 закрыт:** `Contracts\{ModuleRegistry,ModuleStateReader,ModuleActivationGate}` +
`GateDecision` (allow/deny) + `Gate\AllowAllActivationGate` (boxed-дефолт, D10) +
`Registry\{CompiledModule,ArrayModuleStateReader,CompiledModuleRegistry}` + `Exceptions\ModuleNotFound`.
`CompiledModuleRegistry`: `all()`/`get()` читают `bootstrap/cache/flex_modules.php` (мемоизация,
0 fs-сканов после первого `require`); `active()`/`isActive()` — per-account tagged cache
(`account:{id}:modules`) поверх `ModuleStateReader`, cache-miss = 1 вызов, тёплый кеш reader не
зовёт (шпион-тест). Атомарная запись — tmp+`file_put_contents(LOCK_EX)`+`rename()`. `CompileModulesCommand`
дописан: пишет реестр после компиляции. `ModulesServiceProvider`: boxed-дефолты
(`ModuleStateReader→ArrayModuleStateReader`, `ModuleActivationGate→AllowAllActivationGate`,
`ModuleRegistry→CompiledModuleRegistry`). Тесты: `tests/Feature/ModuleRegistryTest.php` (8).
Прогнано: `composer dump-autoload && composer lint:check && composer analyse && composer test` —
Pint passed, PHPStan 0 ошибок, Pest 71/71 (было 63/63 до item'а). Детали phases/P1.md P1.5
Completion Notes, коммит `5865599`.
**2026-07-13 — P1.6 закрыт:** `ModuleLifecycle`-FSM (`enable`/`disable`/`uninstall`/`upgrade`/`status`,
`available→enabled→disabled→(archived|purged)`, `pg_advisory_xact_lock` сериализует конкурентный
enable) + `RecordsRegistrar`/`DatabaseModuleStateReader` (DB-backed, `?string $connection`-сеймом,
не мутируют ambient `database.default`) + `ModuleContext`/`{Enable,Disable,Purge}Hook` + 3 миграции
`mod_modules`/`mod_records`/`mod_lifecycle_log` (PG-first, CHECK-констрейнты, сверены с DB_SCHEMA
§3.3). Миграция K из N падает → 1..K-1 остаются применены (перехват исключения ВНУТРИ обёртывающей
транзакции — savepoint-семантика Laravel-мигратора разобрана в docblock `DatabaseModuleLifecycle`),
`mod_modules`-строка не создаётся, `mod_lifecycle_log.status=failed`, повторный `enable()` доводит.
Purge — `PurgeHook` → `DROP TABLE` по `mod_records(table)` `ORDER BY created_at DESC, reference DESC`
(reference — детерминированный tie-break) → `DELETE mod_records` → `state=purged`. Интерим lifecycle-
события (`Events\{ModuleEnabled,ModuleDisabled,ModuleArchived,ModulePurged,ModuleUpgraded}`, workaround
OQ-4, не `DomainEvent` — тот из P1.9) — `DB::afterCommit`. **Вводит PG16 тест-лейн**: `docker-compose.yml`
(postgres:16-alpine, порт `5434` — 5432 занят на машине исполнителя), `CoreXTestCase` — доп.
коннекшен `pgsql` аддитивно к sqlite-дефолту, `composer test` → `--exclude-group=pg` (дефолт,
sqlite-only), новый `composer test:pg` → `--group=pg`. Тесты: `tests/Feature/ModuleLifecycleTest.php`
(11, `->group('pg')`) на стабах `tests/Stubs/Lifecycle/*` — AC-5..AC-11, AC-13, derived-часть AC-12.
Прогнано: `composer dump-autoload && composer lint:check && composer analyse && composer test` —
Pint passed, PHPStan 0 ошибок, Pest 71/71 sqlite-лейн (было 71/71 до item'а, `group=pg` исключена из
дефолта); `docker compose up -d` + `vendor/bin/pest --group=pg` — 11/11, прогнано 3 раза подряд
стабильно зелёным. Детали phases/P1.md P1.6 Completion Notes/Pending Work/Known Deviations,
коммит `20853eb`.
**Known Deviation P1.6:** исполнено Sonnet/medium вместо предписанного `plan.md` §Routing `opus/high`
— риск (data-loss на purge, PG16-инфра) закрыт инженерным разбором транзакционной семантики миграций
(см. docblock `DatabaseModuleLifecycle`) и 11 PG-тестами. PG-лейн прогнан НЕ на host PHP (нет
`pdo_pgsql`, `apt-get`/`sudo` заблокированы sandbox-политикой) — вместо этого контейнер
`serversideup/php:8.4-fpm-nginx` (локально закэширован, содержит `pdo_pgsql`) в сети
`docker-compose.yml`; функционально эквивалентно `composer test:pg`.
**2026-07-13 — P1.7 закрыт:** `Contracts\{SettingsScope,SettingsRepository,SettingDefaultsProvider}`
(SettingsScope — `final readonly` фабрики `account()/workspace($id)/user($id)`; SettingsRepository v2
`get/set/forget/all(namespace,key,?scope,default)`; SettingDefaultsProvider — направление core←modules,
D20/D23) + `Settings\NullSettingDefaultsProvider` (боксед-дефолт, D10) +
`Exceptions\SettingValueTooLargeException` (квота §7.2). `Enums\SettingScope` заменён
`Account/Workspace/User` (был `System/Tenant/User`). `DatabaseSettingsRepository` — каскад
`user→account→default` / `workspace→account→default` (одна `SettingsScope` адресует один уровень —
Known Deviation ниже); `is_sensitive` шифруется `Crypt::encryptString`, маскируется (`••••••`) в
`SettingChanged` (интерим `CoreEvent`, retrofit на `DomainEvent` — P1.9); квота 64КБ enforced.
Миграция `sys_settings` — `packages/core/database/migrations/pg/` (PG-only, jsonb/CHECK/
UNIQUE NULLS NOT DISTINCT — не грузится дефолтным sqlite-лейном, мигрируется явно PG-тестом,
паттерн P1.6); старая `corex_settings` удалена (D1, greenfield). `CoreXTestCase` — добавлен
`app.key` (Testbench не даёт APP_KEY, нужен для `Crypt`). Тесты: `tests/Feature/SettingsTest.php`
переписан целиком в `->group('pg')` (5 тестов, AC-21 а/б/в/г/д) + `tests/Stubs/StubSettingDefaultsProvider.php`.
Прогнано: `composer dump-autoload && composer lint:check && composer analyse && composer test` —
Pint passed, PHPStan 0 ошибок, Pest 68/68 sqlite-лейн (было 71/71 до item'а — 3 старых sqlite-теста
Settings перенесены в pg-группу); `docker compose up -d` + `vendor/bin/pest --group=pg` (контейнер
`serversideup/php:8.4-fpm-nginx`, host без `pdo_pgsql`) — 16/16 (5 новых + 11 P1.6 без регресса).
Детали phases/P1.md P1.7 Completion Notes/Pending Work/Known Deviations, коммит `fd5eb21`.
**2026-07-13 — P1.8 закрыт:** `Contracts\AuditEntry` (`final readonly`: action/actorType
`ActorType`-enum/actorId/entityType/entityId/changes/context) + `AuditLogger::record(AuditEntry)`
(сигнатура переписана, breaking легально pre-1.0). `Audit\QueuedAuditLogger` (enqueue-only) +
`RecordAuditEntry` (`ShouldQueue`-воркер, резолвит `workspace_id` из `TenantContextResolver` на
записи, `$writeConnection`-сеймом — не `$connection`, коллизия с `Illuminate\Bus\Queueable`) +
`Audit\AuditObserver` (единый diff+маскировка sensitive для `HasAuditLog`-трейта И авто-observer) +
`CurrentActor` (actor-снапшот user/system + ip/ua/request_id). **Архитектурное решение
(D22-прецедент):** wiring `byCapability(Auditable)` → `Model::observe()` — в новом
`Flex\Modules\Audit\AuditCapabilityWiring`/`ModulesServiceProvider::boot()`, НЕ в `CoreServiceProvider`
(corex/core не может зависеть от flex/modules). Миграция `sys_audit_log` (PG-only, BRIN(occurred_at),
CHECK actor_type, БЕЗ updated_at — DB_SCHEMA §Конфликт-2 разрешён владельцем 2026-07-13, эскалация
НЕ потребовалась). `CoreXTestCase` — `corex.audit.enabled=false` дефолтом на sqlite-лейне (иначе
существующие Auditable-модели — Widget в MoneyCastTest/TenantScopeTest/UlidGenerationTest — падали
бы в missing-table); `AuditLogTest` включает явно. Тесты: `tests/Feature/AuditLogTest.php`
переписан целиком в `->group('pg')` (8 тестов: enqueue-only/AC-23, HasAuditLog create+update,
byCapability-авто-observer+маска/AC-20, no-double-record, actor=system, UUIDv7-курсор/AC-23,
BRIN-индекс). Прогнано: `composer dump-autoload && composer lint:check && composer analyse` —
Pint passed, PHPStan 0 ошибок; `php vendor/bin/pest --exclude-group=pg` — 66/66 sqlite-лейн (было
68/68 — 2 старых sqlite-теста Audit перенесены в pg-группу); `docker compose up -d` +
`PGSQL_HOST=127.0.0.1 PGSQL_PORT=5434 php vendor/bin/pest --group=pg` (host уже несёт `pdo_pgsql` —
контейнер-обходка P1.6/P1.7 не понадобилась) — 24/24 (8 новых + 16 без регресса). Детали
phases/P1.md P1.8 Completion Notes/Pending Work/Known Deviations, коммит `18ddf73`.

**2026-07-13 — P1.9 закрыт:** `Events\DomainEvent` (`abstract`: eventId `Str::uuid7()`, occurredAt
`DateTimeImmutable`, tenant — параметр или `app(TenantContextResolver::class)->current()`, abstract
static `name()`) заменяет пустой маркер `CoreEvent` (удалён) + `Events\Cancellable` (отдельный
опциональный интерфейс `cancel()`/`isCancelled()`, не примешан в базу — **D27**). `SettingChanged`
ретрофитнут (`name()`→`core.setting.changed`); новый `FeatureFlagToggled extends DomainEvent`
(`name()`→`core.feature.toggled`). `Contracts\{FeatureFlags,FeatureFlagDefinition}` (B-10 §3.1
сигнатура) + `Features\DatabaseFeatureFlags` (persisted на `sys_feature_flags`/`sys_feature_overrides`;
`define()` идемпотентен — не перетирает admin-toggled `is_enabled`; `active/value` — override
переданного `SettingsScope`, фолбэк на account-уровень флага) + `Models\{FeatureFlag,FeatureOverride}`.
2 PG-миграции (`migrations/pg/`, мирроят P1.7/P1.8 паттерн): `sys_feature_flags` (`key` UNIQUE),
`sys_feature_overrides` (FK CASCADE, CHECK `scope_type IN(workspace,user,department)`,
`UNIQUE(flag_id,scope_type,scope_id)`). Старый `Contracts\FeatureResolver`/`Features\ConfigFeatureResolver`
удалены (единый источник — D27, pre-1.0 breaking легально D17); `CoreServiceProvider`-биндинг
переключён `FeatureFlags→Config::featureFlagsClass()`. `config/corex.php`/`Config` — новые
`tables.feature_flags`/`feature_overrides`, `bindings.feature_flags`. `Flex\Modules\Manifest::$featureFlags`
ретрофитнут `list<mixed>`→`list<FeatureFlagDefinition>` (D20/OQ-9). Тесты: `tests/Feature/FeatureFlagsTest.php`
(7, `->group('pg')`) — DomainEvent-штамповка, FeatureFlagToggled-наследование, `define()`
идемпотентность, AC-22 workspace/user-override, FK CASCADE, CHECK-констрейнт. Прогнано:
`composer dump-autoload && composer lint:check && composer analyse` — Pint passed, PHPStan 0 ошибок;
`php vendor/bin/pest --exclude-group=pg` — 66/66 sqlite-лейн (без регресса); `PGSQL_HOST=127.0.0.1
PGSQL_PORT=5434 vendor/bin/pest --group=pg` — 31/31 (7 новых + 24 без регресса). Детали
phases/P1.md P1.9 Completion Notes/Pending Work, коммит `963cf44`.

**Remaining:** Исполнять phase-first. Порядок P1 (sequential-coupled), P1.1 ✅ / P1.12 ✅ / P1.2 ✅ /
P1.3 ✅ / P1.4 ✅ / P1.5 ✅ / P1.6 ✅ / P1.7 ✅ / P1.8 ✅ / P1.9 ✅: **P1.10** (sys_departments ltree,
следующий, переиспользует PG-лейн) → P1.11 → P1.13.
Затем P2 — **предусловие P2.1 = решение OQ-5 (php ^8.4 vs ^8.3)**; P2.1 GO → P2.2 → P2.3 → P2.4 →
P2.5 → P2.7 (users, до P2.6/P2.8) → P2.6 → P2.8 → P2.9 → re-GO checkpoint → P2.10 (DoR: ADR-017
сверен, OQ-7) → P2.11 → P2.12 → P2.13.

**Per-item routing P1** (несущие opus/high — pre-mortem; механика sonnet/medium):

| Item | Model/effort | Почему |
|:--|:--|:--|
| P1.13 | opus/high | кастомное PHPStan-правило (AST-уровень) |
| P2.1 | opus/high | PoC-гейт stancl: GO/NO-GO + F2-спайка + НФТ (Routing §3) |
| P2.13 | opus/high | PHPStan stancl-изоляция + arch-тесты (Routing §3) |
| P1.10 P1.11 | sonnet/medium | механика по готовому ТЗ (контракты/DDL/DTO) — **P1.10 следующий** |

**Sources of truth:** plans/COREX-CORE/plan.md (v0.2.0, D1–D26) · phases/P1.md · phases/P2.md ·
open-questions.md (OQ-1..9) · findings/P1-P2-rag-verification-2026-07-12.md (внешние факты,
дата-штамп) · findings/P2-premortem.md · findings/P1-P2-audit-2026-07-12.md · findings/P1-premortem.md ·
~/Vaults/Brain/plans/flex/60_contracts/DB_SCHEMA.md (DRAFT — сверяться; FROZEN = предусловие
финального закрытия migration-items) · ~/Vaults/Brain/plans/flex/30_blueprints/B-10_core-module-loader.md ·
B-11_auth-tenancy.md · ~/Vaults/Brain/plans/flex/20_research/R-11_stancl-readiness.md · R-15_cell-infra.md ·
~/Vaults/Brain/plans/flex/50_execution/00_EXECUTION_ROADMAP.md

**Open risks:** **OQ-5 php ^8.4** (stancl master vs corex ^8.3 — блокер P2.1, решение владельца);
DB_SCHEMA DRAFT (OQ-1/OQ-6 — владелец схемы; OQ-1 «финальное закрытие P1.8» из plan.md §2 снят —
DB_SCHEMA §Конфликт-2 (updated_at) разрешён владельцем 2026-07-13, см. P1.8 Completion Notes);
stancl v4 без релиза (D8/D25 supply-chain-якорь; PoC-гейт держит); churn контрактов до 1.0
(D17 — не тегать 1.0 рано); jobpipeline rc (точный пин); broadcasting PR #1448 открыт (OQ-8, вне
scope); host теперь несёт `pdo_pgsql` (P1.8 прогнан напрямую на host, без container-обходки P1.6/P1.7
— если это регрессирует на другой машине исполнителя, вернуться к `serversideup/php` контейнеру).
Полная карта — findings/P2-premortem.md.

**Workarounds/Deferred/Open questions:**
- workarounds: lifecycle-события (P1.6, `Flex\Modules\Events\{ModuleEnabled,...}`) остаются на
  interim-базе (plain classes, не `DomainEvent`) — их докблок форвард-ссылался на P1.9, но
  Files/Scope Included P1.9 их не перечисляли (другой пакет, `flex/modules`); открытый хвост для
  следующего item'а/loop-back, который трогает `flex/modules/Events` (см. Pending Work P1.9);
  `SettingChanged` retrofit на DomainEvent — сделан P1.9 (OQ-4 закрыт). `FeatureFlagToggled` —
  деливерабл-класс без продюсера (контракт `FeatureFlags` не несёт toggle-мутатора, UI фич-флагов —
  Scope Excluded P1.9); department-scope overrides (`sys_feature_overrides.scope_type='department'`)
  — CHECK готов, но `FeatureFlags::active/value` недостижим через `SettingsScope` до P1.10
  (`sys_departments`, см. Pending Work P1.9); `ArrayEntityRegistry::moduleActive` — предикат-заглушка
  `fn()=>true`, НЕ подключён к реальному `ModuleRegistry::isActive()` (P1.5 контракт готов,
  wiring — открытый хвост, см. phases/P1.md P1.5 Pending Work); `Manifest` читает поля через
  public-свойства, одноимённые с fluent-сеттерами (P1.3 — легальный PHP); `CompiledModule` (P1.5)
  не персистит полный `CompiledRegistry` в компилированный файл — только module-list (см. Pending
  Work P1.5); `DatabaseModuleLifecycle`'s `$codeVersions`/`$migrationPaths` — интерим-сеймы вместо
  installed.json/реальных CorexModule-классов (см. Pending Work P1.6); `SettingDefaultsProvider` —
  боксед-дефолт `NullSettingDefaultsProvider`, реальная реализация поверх скомпилированного
  реестра манифестов НЕ подключена в `ModulesServiceProvider` (см. Pending Work P1.7); `all()`
  Settings-репозитория мерджит только персистентные строки, без манифест-дефолтов (нет примитива
  перечисления ключей в `SettingDefaultsProvider`, см. Pending Work P1.7); `AuditEntry` не несёт
  actorType `agent`/`api_key` наполнения (только `user`/`system` через `CurrentActor`) — ждёт
  entity-scoped auth (P2, OQ-7, см. Pending Work P1.8); wiring `Manifest::$featureFlags`→
  `FeatureFlags::define()` при module-enable не подключено (мирроит открытый хвост
  `SettingDefaultsProvider`-wiring P1.7, см. Pending Work P1.9).
- deferred: corex/components (D6); §4.5 DX-генераторы + §4.6 MCP dev-сервер + AC-33/34 + AC-12 N−1
  (D26); контекстные PHPStan-правила 6/8/9 (P1.13 → Pending Work при неразрешимости); партиционирование
  sys_audit_log (§9.7); pest --parallel per-worker DB isolation (findings §6 — при переходе CI);
  Filament/Api/Routes-реализации потребителей extenders (их пакеты вне плана, P1.4 Scope Excluded);
  wiring `ArrayEntityRegistry`→`ModuleRegistry` и полная персистенция `CompiledRegistry` в файл
  (см. workarounds выше); production `loadMigrationsFrom` для `packages/modules/database/migrations`
  (см. Pending Work P1.6 — сознательно не подключён, ломало бы sqlite-суиты Crm/Tasks); реальный
  workspace-скоуп-резолв Settings (зависит от P2 workspace, см. Known Deviation P1.7); ретеншн-хук
  per-тариф sys_audit_log (биллинг вне плана, см. Pending Work P1.8); бенч sync-пути AuditLogger
  ≤0.3мс (нет pao/бенч-хука в проекте, см. Pending Work P1.8).
- open_questions: **open-questions.md OQ-1..9** — блокеры: OQ-5 (php-констрейнт, предусловие P2.1),
  OQ-7 (actor-type support + ADR-017, DoR P2.10/P2.11); OQ-1 (updated_at) снят P1.8 — DB_SCHEMA-
  владелец разрешил §Конфликт-2 2026-07-13; OQ-6 (DB_SCHEMA счёт/sys_pubsub_messages — владелец
  схемы); OQ-2 (DomainEvent↔CoreXEvent) снят P1.9 → D27; OQ-9 (Manifest::featureFlags() типизация)
  снят P1.9 (ретрофит на `list<FeatureFlagDefinition>`); OQ-8 (наблюдение PR #1448).
