# HANDOFF — 2026-07-15 — after P1.25

**Next:** ЗАПУСК ВРУЧНУЮ: workflow (sonnet/low per plan.md §3 Routing, `plan-exec`) — **P1.26**
(закрытие фазы: слепой аудит + CHANGELOG + semver-теги + PR в `main`)

**Таргетированный аудит D63 (4 Major-зоны волны 2, НЕ замена канонического слепого гейта) завершён
2026-07-15, все 4 находки ЗАКРЫТЫ (D64–D67).** Нашёл и владелец сразу закрыл **1 Blocker** (D64):
`AuditObserver` полагался только на `isFillable()`, credential-поля (`password`/`remember_token`) утекали
на дефолтной `User` и любой `$guarded=[]`-модели → `AuditObserver::ALWAYS_EXCLUDED` (жёсткий блок-лист,
`49a59d8`), mutation `P1.21-always-excluded-removed` (`dde0150`). Затем закрыты остальные три:
- **D65 — connection-seam (P1.16), Major, ✅ исправлено (`1ec7a5e`):** биндинг `ModuleStateReader` в
  `ModulesServiceProvider` был голым class-string → автовайрил `$connection=null` и читал `mod_modules` с
  ambient-дефолта, ИГНОРИРУЯ `config('flex-modules.connection')` (в отличие от `RecordsRegistrar`). Переведён
  на замыкание, читающее config; regression-тест резолвит контракт из контейнера без override; mutation
  `P1.16-statereader-bare-binding`. P1-scope config-read — P2 per-call ctx→connection (D52) остаётся deferred.
- **D66 — killswitch write-seam (P1.21), Minor, ✅ исправлено (`1c604f0`):** `Config::auditEnabled()`
  продублирован в `QueuedAuditLogger::record()` (defense-in-depth на enqueue-seam, не в воркере — событие,
  снятое при вкл. аудите, обязано долететь); regression-тест на прямой `AuditLogger::record()`; mutation
  `P1.21-killswitch-writeseam`.
- **D67 — Departments move() workspace-filter (P1.23), Minor, ✅ исправлено (`e941b83`):** `move()`'s UPDATE
  получил `and workspace_id = ?` (симметрично `subtree()`/`primaryOf()`); regression-тест на cross-workspace
  ltree-коллизию; mutation `P1.23-move-workspace-filter`. Низкая эксплуатируемость (глоб. уникальность
  ULID-путей), контрактная per-workspace корректность восстановлена.

Гейт зелёный локально по всем трём (105/105 sqlite, 96/96 pg — +3 новых pg-теста к 93). Все три коммита
запушены на `p1r-remediation`. **P1.26 (слепой гейт-аудит + закрытие фазы) более ничем из D63 не блокирован.**

| Параметр | Значение |
|:--|:--|
| Model | per-item (D35 для волны; P1.26 сам роутится `sonnet/low`/`plan-exec` — §3 Routing) |
| Thinking | — |
| Context | ТА ЖЕ СЕССИЯ (resume, кэш прошлых items переиспользуется) |
| Суть запуска | P1.25 закрыт 🟠 Done with deviations — 5 lifecycle-событий модуля (`ModuleEnabled/Disabled/Archived/Purged/Upgraded`) переведены на `CoreX\Events\DomainEvent` с каноническими именами B-10 §3.1 (`mod.module.*`); `CompiledSettingDefaultsProvider` (новый) читает манифест-дефолты настроек из скомпилированного реестра, ребиндится в `ModulesServiceProvider` поверх боксед-`Null`-провайдера; `FeatureFlags::define()` вызывается для `Manifest::$featureFlags` на `enable()`. По ходу вскрыт и закрыт архитектурный пробел P1.15: `CompiledRegistry` никогда не переносил `Manifest::$settings`/`$featureFlags` — добавлены 2 новых слайса (`settings`/`featureFlags`, `Compiler/CompiledSettingDefault.php` новый). Локальный гейт зелёный (105/105 sqlite, 92/92 pg), mutation-check 2 кейса (`domainevent-drop`, `null-provider-binding`) воспроизведены харнессом. Волна вплотную подошла к закрытию фазы — **P1.26** остаётся единственным блокирующим item'ом. |

```
Workflow({scriptPath: "plans/COREX-CORE/workflows/wf-corex-core-p1r-remediation.js", resumeFromRunId: "wf_87181b80-667"})
```

**Done:** (этот handoff) **P1.25** закрыт 🟠 Done with deviations (2026-07-15) — детали `phases/P1.md`
P1.25 Completion Notes/Known Deviations, сводка `plan.md` §4 (запись после D62/P1.23). `files_outside_scope`
непустой: `packages/modules/src/Compiler/{CompiledRegistry,RegistryCompiler}.php` + новый
`Compiler/CompiledSettingDefault.php` (архитектурная необходимость — P1.15's `CompiledRegistry` никогда
не переносил `Manifest::$settings`/`$featureFlags`, без расширения Implementation Rules item'а было
невыполнимо без нарушения запрета на `ModuleDiscovery::discover()` на hot-path), `Contracts/
ModuleRegistry.php` (докблок, 8→10 слайсов), `tests/Feature/RegistryCompilerTest.php` (P1.15
round-trip-тест обновлён под новые слайсы — иначе регрессия в `composer test`, не относящаяся к P1.25),
новый тестовый фикстур `tests/Stubs/Lifecycle/StubFeatureFlagModule.php`, `scripts/mutation-check.sh`
(регистрация 2 кейсов, D45-мандат) → статус 🟠, не 🟢, по тому же правилу докс-куратора (прецедент
P1.19–P1.24). Pending Work — пусто, реализация полная. Known Deviations — 5 пунктов (см. phases/P1.md),
все — прямое следствие Scope Included/Implementation Rules, не scope creep. CI на ветке из сессии не
верифицирован (только локальный гейт D34) — проверяется на закрытии фазы P1.26.

**Done (ранее):** **P1.24** закрыт 🟠 Done with deviations (2026-07-15, коммиты `f6510a4`/`6111b97`
запушены на `p1r-remediation`) — детали `phases/P1.md` P1.24 Completion Notes/Known Deviations.
`files_outside_scope` непустой: `packages/core/src/Contracts/WorkerRuntime.php` (новый контракт — шов
для негативного теста guard'а), `packages/core/src/PubSub/DefaultWorkerRuntime.php` +
`packages/core/src/PubSub/SubscribeGuard.php` (продакшен-детект + общая guard-логика для 2 драйверов),
`packages/core/database/migrations/pg/2026_07_15_090000_create_sys_pubsub_cursors_table.php` (D44
обязывал таблицу, не называл файл), `packages/core/src/CoreServiceProvider.php` (биндинг +
регистрация `corex:pubsub:prune`), `tests/Feature/MigrationPublishTest.php` (7→8 sys_*-миграций —
жёсткий счётчик пришлось поднять), `scripts/mutation-check.sh` (регистрация 2 кейсов, D45-мандат) →
статус 🟠, не 🟢, по тому же правилу докс-куратора (прецедент P1.19–P1.23: необходимые, но
не перечисленные в исходном ТЗ файлы). 3 Known Deviations: (1) файлы вне списка (см. выше, все —
прямое следствие Scope Included, не scope creep); (2) `getenv('LARAVEL_OCTANE')` вместо
`env('LARAVEL_OCTANE')` из буквального текста Implementation Rules — `larastan.noEnvCallsOutsideOfConfig`
запрещает `env()` вне `config/`, тот же обход, что уже использует `CoreXTestCase`; (3) мутация
`P1.24-bare-runninginconsole-guard` реддит тест через реальную (~60с) сетевую ошибку подключения к
Redis, а не мгновенно через ассерт текста исключения — граница всё равно пинится (raннер подтвердил
exit≠0 у теста, exit=0 у самого mutation-check.sh), но кейс на порядок медленнее остальных P1.14–P1.23.

**Done (ранее):** **P1.23** закрыт 🟠 Done with deviations (2026-07-15, коммит `a97d5fb`
запушен) — `move()` lock-then-read+re-read под локом (A5), `DepartmentMoved` через afterCommit (A6),
`SoftDeletes`+cast на `Models\Department` с фильтрацией find/subtree/primaryOf (A24), `subtree()`
per-workspace, DTO `sortOrder`. `files_outside_scope=[scripts/mutation-check.sh]` + 2 Known Deviations
(afterCommit-тест наблюдает продакшн `DatabaseTransactionsManager`; A5-мутация пинится через
наблюдаемое re-read-следствие, не буквальный «read before lock») → статус 🟠, не 🟢. Детали —
`phases/P1.md` P1.23 Completion Notes/Known Deviations.

**Разрешённые эскалации прошлых items (детали — `phases/P1.md`, блоки «✅ РАЗРЕШЕНО»):**
1. **P1.14 (D47/D48):** phpstan `tests`-paths вынесены в **P1.28**; coverage `--min` ослаблен 80→55 (D47),
   возврат к 80 — time-gated **P1.27**, не блокирует закрытие фазы.
2. **P1.16 (D52):** коннекшн модуль-системы — P1-форма шва; per-call ctx→connection — P2 (D13).
3. **P1.17 (D53/D56):** хрупкий AC-2 тест исправлен фикстурой; CI зелёный (run `29347512984`).
4. **P1.18/P1.19 (D57):** ложный DoR-гейт «OQ-11» опровергнут — исполнены под существующим ТЗ.
5. **P1.20 (D58/D62):** mutation-check-кейс зарегистрирован в харнессе — процессный блокер снят.

**Remaining:** волна 2 закрыта (P1.14–P1.25 все 🟠/🟢, ни одного ⬜/🔴) → **P1.26** (закрытие
фазы: слепой аудит + CHANGELOG + semver-теги + PR в `main`) → `/task:plan-close COREX-CORE P1` → P2.1.
Time-gated **P1.27** и отдельный **P1.28** — после P1.26, не блокируют закрытие фазы (D47/D48).

**Аудит фазы P1** проводится **ВСЛЕПУЮ** (без A-списка — D45/P1.26 Code Guidance) теперь, когда все items
волны 2 (P1.14–P1.25) сданы. GREEN → **P1.26** (CHANGELOG + `Deprecated / Will break in 0.2` + semver-теги
+ PR ветки в `main`) → `/task:plan-close COREX-CORE P1` → и только потом P2.1.

**Итог борда после закрытия P1.25:** **5 🟢 · 20 🟠 · 0 🔴 · 3 ⬜** (⬜ = P1.26/P1.27/P1.28).

**Sources of truth:** `plans/COREX-CORE/plan.md` (§2 Rules, §3 Routing, §5 D28–D62+, §4 борд, §6 Update Log) ·
`plans/COREX-CORE/phases/P1.md` (ТЗ items) ·
`plans/COREX-CORE/findings/P1-audit-2026-07-13.md` (A1..A93) ·
`plans/COREX-CORE/findings/P1R-premortem.md` (кластеры C1–C8) ·
`plans/COREX-CORE/findings/P1R-coverage-matrix.md`.

Cold-start reads: `plans/COREX-CORE/plan.md` (§2 Rules, §3 Routing, §5 D28–D62+) → этот файл →
`phases/P1.md` (ТЗ item'ов) → `findings/P1-audit-2026-07-13.md` (A1..A93) → `findings/P1R-premortem.md` →
`findings/P1R-coverage-matrix.md`.

**Открытые риски:**
- **Дублирующий стеш** `stash@{0}` («P1R-P1.20-pointfix-uncommitted») — не блокер, droppable housekeeping.
- **Тавтологичные тесты** (часть закрыта волной 2): правило волны — mutation-check патчами в репо,
  прогоняет отдельный верификатор.
- **FeatureFlags-каскад (A69) остаётся частично открытым** после P1.22: `findOverride()` — single-scope
  exact-match, narrow→wide резолв внутри одного вызова не реализован (докблок фиксирует WAI); риск для
  потребителей, ожидающих настоящего каскада user→workspace→account по одному вызову `active()`.
- **Все 4 находки таргетированного аудита D63 закрыты (D64–D67, 2026-07-15)** — connection-seam (P1.16, Major),
  killswitch write-seam (P1.21, Minor), Departments move() workspace-filter (P1.23, Minor) исправлены; см. блок
  «Таргетированный аудит D63» выше и plan.md D65/D66/D67.
- Ядро де-факто PG16-only (OQ-12); OQ-5 (php ^8.4) — блокер P2.1; DB_SCHEMA DRAFT (OQ-6); stancl v4 без релиза.
- Полная карта того, что волна НЕ закрывает — `findings/P1R-premortem.md` (кластеры C1–C8, P1–P7, U1–U6).

**Workarounds/Deferred/Open questions:**
- workarounds: —
- deferred: Nit-класс (20 находок, D29); партиционирование `sys_audit_log` (§9.7); per-тариф retention-числа
  (P1.21 Scope Excluded); Infection/MSI; **coverage 80%** (P1.27); **phpstan `tests`-lint** (P1.28);
  **per-call ctx→connection** (P2, D13/D52); трёхуровневый Settings-каскад с авто-резолвом workspace
  (P1.7/P1.22 Scope Excluded, P2 tenancy); **реальный narrow→wide FeatureFlags-каскад внутри одного вызова**
  (A69 residual, P1.22 Known Deviations — findOverride() остаётся exact-match); UI фич-флагов +
  `FeatureFlagToggled`-продюсер (P1.9/P1.22 Scope Excluded); кеш настроек `corex.settings.cache_*`
  (P1.22 Pending Work); продюсер `FeatureFlagToggled` (P1.25 Scope Excluded — нет админ-API/UI).
- open_questions: **OQ-1..15** (OQ-11 никогда не существовала — D57). Блокеры: OQ-5 (P2.1), OQ-15 (P1.26),
  OQ-7 (P2.10/P2.11).
