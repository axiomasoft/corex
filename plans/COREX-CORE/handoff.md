# HANDOFF — 2026-07-13 — after P1.5

**Next:**

| Параметр | Значение |
|:--|:--|
| Model | Opus |
| Thinking | high — Lifecycle-FSM data-loss-риск на purge + вводит PG16-инфраструктуру (Routing §3, pre-mortem B/C) |
| Context | continue (/clear) — ручной item |
| Суть | Исполнить P1.6 (ModuleLifecycle-FSM: enable/disable/uninstall/upgrade/purge, RecordsRegistrar, 3 миграции mod_* PG-first, advisory-xact-lock, DatabaseModuleStateReader, **вводит PG тест-лейн** — docker PG16 + pgsql-коннекшен + Pest `->group('pg')`) — следующий item фазы после P1.5. |

````
/task:plan-exec COREX-CORE P1.6
````

Cold-start reads: `plans/COREX-CORE/plan.md` → `plans/COREX-CORE/handoff.md` (этот файл) →
`plans/COREX-CORE/phases/P1.md` (Phase Context + item P1.6) → Required Reads P1.6 (B-10 §3.3
433–505, §5.1 872–887, §5.3 908–934, §5.4 936–940, §7.4 1043–1054, §2 DDL mod_* 49–108,
DB_SCHEMA.md §3.3 465–508, plan.md §5 D9/D12, `packages/modules/src/Contracts/ModuleStateReader.php`
из P1.5, `packages/core/database/migrations/2026_06_14_000000_create_corex_settings_table.php`
стиль миграции). Skills: `devops:docker-postgres`, `devops:db-test-preflight`.

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

**Remaining:** Исполнять phase-first, гейт «Запускай P6» снят владельцем 2026-07-13. Порядок P1
(sequential-coupled), P1.1 ✅ / P1.12 ✅ / P1.2 ✅ / P1.3 ✅ / P1.4 ✅ / P1.5 ✅: **P1.6**
(ModuleLifecycle-FSM, вводит PG-лейн, следующий) → P1.7 → P1.8 → P1.9 → P1.10 → P1.11 → P1.13.
Затем P2 — **предусловие P2.1 = решение OQ-5 (php ^8.4 vs ^8.3)**; P2.1 GO → P2.2 → P2.3 → P2.4 →
P2.5 → P2.7 (users, до P2.6/P2.8) → P2.6 → P2.8 → P2.9 → re-GO checkpoint → P2.10 (DoR: ADR-017
сверен, OQ-7) → P2.11 → P2.12 → P2.13.

**Per-item routing P1** (несущие opus/high — pre-mortem; механика sonnet/medium):

| Item | Model/effort | Почему |
|:--|:--|:--|
| P1.6 | opus/high | Lifecycle-FSM + purge (data-loss) + вводит PG16-лейн (pre-mortem B/C) — **следующий** |
| P1.13 | opus/high | кастомное PHPStan-правило (AST-уровень) |
| P2.1 | opus/high | PoC-гейт stancl: GO/NO-GO + F2-спайка + НФТ (Routing §3) |
| P2.13 | opus/high | PHPStan stancl-изоляция + arch-тесты (Routing §3) |
| P1.7 P1.8 P1.9 P1.10 P1.11 | sonnet/medium | механика по готовому ТЗ (DDL/контракты/DTO) |

**Sources of truth:** plans/COREX-CORE/plan.md (v0.2.0, D1–D26) · phases/P1.md · phases/P2.md ·
open-questions.md (OQ-1..9) · findings/P1-P2-rag-verification-2026-07-12.md (внешние факты,
дата-штамп) · findings/P2-premortem.md · findings/P1-P2-audit-2026-07-12.md · findings/P1-premortem.md ·
~/Vaults/Brain/plans/flex/60_contracts/DB_SCHEMA.md (DRAFT — сверяться; FROZEN = предусловие
финального закрытия migration-items) · ~/Vaults/Brain/plans/flex/30_blueprints/B-10_core-module-loader.md ·
B-11_auth-tenancy.md · ~/Vaults/Brain/plans/flex/20_research/R-11_stancl-readiness.md · R-15_cell-infra.md ·
~/Vaults/Brain/plans/flex/50_execution/00_EXECUTION_ROADMAP.md

**Open risks:** **OQ-5 php ^8.4** (stancl master vs corex ^8.3 — блокер P2.1, решение владельца);
DB_SCHEMA DRAFT (OQ-1/OQ-6 — владелец схемы); stancl v4 без релиза (D8/D25 supply-chain-якорь;
PoC-гейт держит); churn контрактов до 1.0 (D17 — не тегать 1.0 рано); jobpipeline rc (точный пин);
broadcasting PR #1448 открыт (OQ-8, вне scope). Полная карта — findings/P2-premortem.md.

**Workarounds/Deferred/Open questions:**
- workarounds: SettingChanged (P1.7) и lifecycle-события (P1.6) до DomainEvent — interim-база,
  ретрофит P1.9 (OQ-4); `Manifest::featureFlags()` — interim `list<mixed>` до P1.9 (OQ-9/D20);
  `ArrayEntityRegistry::moduleActive` — предикат-заглушка `fn()=>true`, НЕ подключён к реальному
  `ModuleRegistry::isActive()` (P1.5 контракт готов, wiring — открытый хвост, см. phases/P1.md
  P1.5 Pending Work); `Manifest` читает поля через public-свойства, одноимённые с fluent-сеттерами
  (P1.3 — легальный PHP); `CompiledModule` (P1.5) не персистит полный `CompiledRegistry`
  (entities/permissions/menu/api/filament/routes/workflow из P1.4) в компилированный файл —
  только module-list для `all()/get()` (см. Pending Work P1.5).
- deferred: corex/components (D6); §4.5 DX-генераторы + §4.6 MCP dev-сервер + AC-33/34 + AC-12 N−1
  (D26); контекстные PHPStan-правила 6/8/9 (P1.13 → Pending Work при неразрешимости); партиционирование
  sys_audit_log (§9.7); pest --parallel per-worker DB isolation (findings §6 — при переходе CI);
  Filament/Api/Routes-реализации потребителей extenders (их пакеты вне плана, P1.4 Scope Excluded);
  wiring `ArrayEntityRegistry`→`ModuleRegistry` и полная персистенция `CompiledRegistry` в файл
  (см. workarounds выше).
- open_questions: **open-questions.md OQ-1..9** — блокеры: OQ-5 (php-констрейнт, предусловие P2.1),
  OQ-7 (actor-type support + ADR-017, DoR P2.10/P2.11), OQ-1 (updated_at, финальное закрытие P1.8);
  OQ-6 (DB_SCHEMA счёт/sys_pubsub_messages — владелец схемы); OQ-2 (DomainEvent↔CoreXEvent, решается
  на P1.9); OQ-8 (наблюдение PR #1448).
