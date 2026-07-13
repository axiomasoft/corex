# HANDOFF — 2026-07-13 — after P1.13

**Next:**

| Параметр | Значение |
|:--|:--|
| Model | Opus |
| Thinking | xhigh — adversarial-аудит закрытой фазы: сверка 13 🟢/🟠 с кодом и git, no-op, scope drift |
| Context | NEW SESSION (/clear) — аудит фазы читает весь код фазы, свежий контекст обязателен |
| Суть | Фаза P1 терминальна (12 🟢 + 1 🟠) — прогнать аудит фазы, затем закрыть её (Phase Handoff, semver-теги `corex/core v0.1.0` / `flex/modules v0.1.0` + CHANGELOG per D17) и только после этого стартовать P2.1. |

````
/task:plan-audit COREX-CORE P1
````

После GREEN-аудита:

````
/task:plan-close COREX-CORE P1
````

Затем первый item фазы P2 (**предусловие: OQ-5 — решение владельца по php ^8.4 vs ^8.3**):

````
/task:plan-exec COREX-CORE P2.1
````

Cold-start reads: `plans/COREX-CORE/plan.md` → `plans/COREX-CORE/handoff.md` (этот файл) →
`plans/COREX-CORE/phases/P1.md` (для аудита) / `phases/P2.md` (для P2.1) → Required Reads item'а.

**Done:** План создан 2026-07-06; обе фазы детализированы до 16-польных ТЗ 2026-07-08 (26/26);
2026-07-12 двойной design-пасс (DoR-финализация + max-design, детали в Update Log plan.md).
**Фаза P1 исполнена целиком (13/13 items терминальны, 12 🟢 + 1 🟠).** Порядок закрытия и коммиты:
P1.1 `c6b4ce0` (UUIDv7 — дефолт ID-strategy) · P1.12 `1890e56` (контракты TenantContext/AccountRef/
WorkspaceRef + TenantContextResolver + SingleAccountResolver) · P1.2 `c5fd939` (EntityRegistry v2 +
новый пакет `flex/modules`) · P1.3 `dabf1ab` (CorexModule/Manifest/Dep/SettingDefault + ModuleDiscovery
+ `flex:modules:compile`) · P1.4 `e8111bf` (RegistryCompiler + 8 extenders + CompilationValidator,
«спящий» cross-module extender) · P1.5 `5865599` (CompiledModuleRegistry hot-path + ModuleActivationGate)
· P1.6 `20853eb` (ModuleLifecycle-FSM + RecordsRegistrar + `mod_*` миграции, **вводит PG16 тест-лейн**)
· P1.7 `fd5eb21` (Settings v2, `sys_settings`) · P1.8 `18ddf73` (Audit v2 queued, `sys_audit_log` BRIN)
· P1.9 `963cf44` (DomainEvent-база — D27 — + FeatureFlags v2) · P1.10 `32acd10` (sys_departments ltree
+ DepartmentRepository) · P1.11 `b05459c` (PubSub + 3 драйвера) · P1.13 `4571e23` (PHPStan-расширение
`flex/modules-lint`, 9 правил).
Детали каждого item'а — `phases/P1.md` (Completion Notes / Pending Work / Known Deviations);
пересказ здесь сознательно опущен.
**2026-07-13 — P1.13 закрыт (🟠 Done with deviations):** `Flex\Modules\PHPStan\Rules\*` — 6 простых
правил на SQL-строках-константах DB/Schema-вызовов (SET без LOCAL · session `pg_advisory_lock` ·
LISTEN/NOTIFY · CREATE TEMP · SQL PREPARE · eval) + 3 контекстных, владение в которых задаётся картой
в `packages/modules/extension.neon` (чужой tablePrefix · fs вне директории модуля · прямая запись в
`sys_*`/`mod_*` мимо контрактов). `composer analyse` с включённым расширением — 0 ошибок на легальном
коде ядра. Тесты — `RuleTestCase`, 9 фикстур позитив/негатив. Отклонения: исполнено sonnet/medium
вместо `opus/high` (Routing §3; риск закрыт верификацией API по коду установленного phpstan 2.2.2);
Required Read #6 (context7) не выполнен — квота исчерпана, замещён чтением phar'а той же версии;
тесты сгруппированы в 2 класса вместо `*RuleTest.php` на правило.

**Remaining:**
1. **Аудит + закрытие фазы P1** (Phase Handoff в `phases/P1.md`, semver-теги `corex/core v0.1.0` /
   `flex/modules v0.1.0` + CHANGELOG per D17) — предусловие старта P2 по Execution Rules.
2. **Фаза P2** (0/13, phase-first): предусловие P2.1 = решение **OQ-5** (php ^8.4 vs ^8.3);
   P2.1 (PoC-гейт stancl, блокирующий; GO/NO-GO) → P2.2 → P2.3 → P2.4 → P2.5 → P2.7 (users, до
   P2.6/P2.8) → P2.6 → P2.8 → P2.9 → re-GO checkpoint → P2.10 (DoR: ADR-017 сверен, OQ-7) → P2.11 →
   P2.12 → P2.13.

**Per-item routing P2** (несущие opus/high — Routing §3):

| Item | Model/effort | Почему |
|:--|:--|:--|
| P2.1 | opus/high | PoC-гейт stancl: GO/NO-GO + F2-спайка + НФТ — **следующий exec-item после закрытия P1** |
| P2.13 | opus/high | PHPStan stancl-изоляция + arch-тесты |
| остальные P2 | sonnet/medium | механика (DDL-миграции, контракты, провижининг) |

**Sources of truth:** plans/COREX-CORE/plan.md (v0.2.0, D1–D27) · phases/P1.md · phases/P2.md ·
open-questions.md (OQ-1..9) · findings/P1-P2-rag-verification-2026-07-12.md (внешние факты,
дата-штамп) · findings/P2-premortem.md · findings/P1-P2-audit-2026-07-12.md · findings/P1-premortem.md ·
~/Vaults/Brain/plans/flex/60_contracts/DB_SCHEMA.md (DRAFT — сверяться; FROZEN = предусловие
финального закрытия migration-items) · ~/Vaults/Brain/plans/flex/30_blueprints/B-10_core-module-loader.md ·
B-11_auth-tenancy.md · ~/Vaults/Brain/plans/flex/20_research/R-11_stancl-readiness.md · R-15_cell-infra.md ·
~/Vaults/Brain/plans/flex/50_execution/00_EXECUTION_ROADMAP.md

**Open risks:** **OQ-5 php ^8.4** (stancl master vs corex ^8.3 — блокер P2.1, решение владельца);
DB_SCHEMA DRAFT (OQ-6 — владелец схемы); stancl v4 без релиза (D8/D25 supply-chain-якорь; PoC-гейт
держит); churn контрактов до 1.0 (D17 — не тегать 1.0, пока EntityRegistry/TenantContext не обкатаны
потребителями); jobpipeline rc (точный пин `2.0.0-rc7`); broadcasting PR #1448 открыт (OQ-8, вне scope);
PG-лейн прогоняется на host (`pdo_pgsql` есть; на другой машине — вернуться к контейнеру
`serversideup/php`, см. Known Deviation P1.6); правила `flex/modules-lint` 6/8 инертны, пока в
экосистеме нет реальных module-пакетов (карта владения в neon пуста — P1.13 Pending Work).
Полная карта — findings/P2-premortem.md.

**Workarounds/Deferred/Open questions:**
- workarounds: полный список открытых хвостов P1 (lifecycle-события на interim-базе вместо
  `DomainEvent`; `ArrayEntityRegistry::moduleActive` — заглушка `fn()=>true`, не подключена к
  `ModuleRegistry::isActive()`; `SettingDefaultsProvider` — боксед-`Null…`, реальная реализация поверх
  реестра манифестов не подключена; wiring `Manifest::$featureFlags`→`FeatureFlags::define()` отсутствует;
  `FeatureFlagToggled` без продюсера; `SettingsScope` не несёт варианта `department`; `CompiledModule`
  не персистит полный `CompiledRegistry`; `$codeVersions`/`$migrationPaths` `DatabaseModuleLifecycle` —
  интерим-сеймы; `AuditEntry` без actorType `agent`/`api_key`; Redis-драйвер PubSub без smoke-теста;
  request-cycle guard `subscribe()` без негативного теста) — живёт в **Pending Work соответствующих
  items `phases/P1.md`**, здесь не дублируется; аудит фазы P1 обязан их пересобрать.
- deferred: corex/components (D6); §4.5 DX-генераторы + §4.6 MCP dev-сервер + AC-33/34 + AC-12 N−1
  (D26); автовывод карты владения `flex/modules-lint` из манифеста (P1.13 Pending Work, v1.1);
  правила на `PDO::ATTR_PERSISTENT` (§7.3 п.6) и курсоры `WITH HOLD` (§7.3 п.7) — v1.1;
  партиционирование sys_audit_log (§9.7); pest --parallel per-worker DB isolation; production
  `loadMigrationsFrom` для `packages/modules/database/migrations`; cross-package FK
  (`sys_departments.workspace_id`/`head_user_id`, `sys_department_user.user_id`) — интеграционная
  миграция P2.6 (D14); `sys_pubsub_messages` вне DB_SCHEMA/golden-шаблона (OQ-6).
- open_questions: **open-questions.md OQ-1..9** — блокеры: OQ-5 (php-констрейнт, предусловие P2.1),
  OQ-7 (actor-type support + ADR-017, DoR P2.10/P2.11); OQ-6 (DB_SCHEMA счёт/`sys_pubsub_messages` —
  владелец схемы); OQ-8 (наблюдение PR #1448). Сняты: OQ-1 (P1.8), OQ-2 → D27 (P1.9), OQ-9 (P1.9).
