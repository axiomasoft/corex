# HANDOFF — 2026-07-15 — after P1.24

**Next:** ЗАПУСК ВРУЧНУЮ: workflow (sonnet/medium per-item, D35) — **P1.25**

| Параметр | Значение |
|:--|:--|
| Model | per-item (D35) — носитель модели workflow-скрипт, НЕ чат |
| Thinking | — |
| Context | ТА ЖЕ СЕССИЯ (resume, кэш прошлых items переиспользуется) |
| Суть запуска | P1.24 закрыт 🟠 Done with deviations — `SubscribeGuard`+`WorkerRuntime`-шов (Octane-детект + явный opt-in, не голый `runningInConsole()`, A29); DURABLE-курсор подписчика `sys_pubsub_cursors` (D44, персистентная позиция channel+subscriber, бутстрап на `max(id)`, без реплея бэклога по умолчанию); `corex:pubsub:prune` ретеншн-команда; рефлексия и grep-тест в `PubSubTest.php` удалены, заменены поведенческими (спай-драйвер через контейнер). Локальный гейт зелёный (104/104 sqlite, 88/88 pg), mutation-check 2 кейса (`bare-runninginconsole-guard`, `cursor-start-zero`) воспроизведены харнессом. Коммиты `f6510a4`/`6111b97` запушены. Волна продолжается **P1.25**. |

```
Workflow({scriptPath: "plans/COREX-CORE/workflows/wf-corex-core-p1r-remediation.js", resumeFromRunId: "wf_87181b80-667"})
```

**Done:** (этот handoff) **P1.24** закрыт 🟠 Done with deviations (2026-07-15, коммиты `f6510a4`/`6111b97`
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
CI на ветке из сессии не верифицирован (только локальный гейт D34) — проверяется на закрытии фазы P1.26.

**Done (ранее):** **P1.23** закрыт 🟠 Done with deviations (2026-07-15, коммит `a97d5fb`
запушен) — `move()` lock-then-read+re-read под локом (A5), `DepartmentMoved` через afterCommit (A6),
`SoftDeletes`+cast на `Models\Department` с фильтрацией find/subtree/primaryOf (A24), `subtree()`
per-workspace, DTO `sortOrder`. `files_outside_scope=[scripts/mutation-check.sh]` + 2 Known Deviations
(afterCommit-тест наблюдает продакшн `DatabaseTransactionsManager`; A5-мутация пинится через
наблюдаемое re-read-следствие, не буквальный «read before lock») → статус 🟠, не 🟢. Детали —
`phases/P1.md` P1.23 Completion Notes/Known Deviations.

**Done (ранее):** (after P1.20/D62) **P1.22** закрыт 🟠 Done with deviations (2026-07-14,
docs-curator верификация, sha `d96085d`) — детали `phases/P1.md` P1.22 Completion Notes/Known Deviations,
сводка `plan.md` §6 Update Log (запись после D62). `files_outside_scope` непустой
(`packages/core/src/Exceptions/UnsupportedScopeException.php` — предписан Implementation Rules, не в
исходных Files; `scripts/mutation-check.sh` — регистрация 2 кейсов, D45-мандат) → статус 🟠 по правилу
докс-куратора (P1.19/P1.21 прецедент), не 🟢. Вердикт независимого ревью — **ATTENTION** (не GREEN):
deliverables 8/9 подтверждены кодом/тестами; спорный — «каскад override'ов AC-22»: `findOverride()`
остаётся single-scope exact-match, реального narrow→wide каскада в FeatureFlags нет (головной дефект A69
переоформлен в WAI докблоком вместо устранения), отклонение вынужденное (VO несёт один уровень, hop
user→workspace требует P2-tenancy), паритетно уже принятому Known Deviation P1.7. Также переоценённый как
«гонка двух коннекшенов» тест `define()` — на деле последовательный вызов на одном соединении `pgsql`
(false claim в Completion Notes, код при этом корректен). Оба зафиксированы в Known Deviations P1.22.
Коммит `d96085d` запушен на `p1r-remediation`.

**Разрешённые эскалации прошлых items (детали — `phases/P1.md`, блоки «✅ РАЗРЕШЕНО»):**
1. **P1.14 (D47/D48):** phpstan `tests`-paths вынесены в **P1.28**; coverage `--min` ослаблен 80→55 (D47),
   возврат к 80 — time-gated **P1.27**, не блокирует закрытие фазы.
2. **P1.16 (D52):** коннекшн модуль-системы — P1-форма шва; per-call ctx→connection — P2 (D13).
3. **P1.17 (D53/D56):** хрупкий AC-2 тест исправлен фикстурой; CI зелёный (run `29347512984`).
4. **P1.18/P1.19 (D57):** ложный DoR-гейт «OQ-11» опровергнут — исполнены под существующим ТЗ.
5. **P1.20 (D58/D62):** mutation-check-кейс зарегистрирован в харнессе — процессный блокер снят.

**Remaining:** волна 2 — **P1.24, P1.25** (2 items по порядку плана) → **P1.26** (закрытие
фазы: слепой аудит + CHANGELOG + semver-теги + PR в `main`) → `/task:plan-close COREX-CORE P1` → P2.1.
Time-gated **P1.27** и отдельный **P1.28** — после P1.26, не блокируют закрытие фазы (D47/D48).

**Аудит фазы P1** проводится **ВСЛЕПУЮ** (без A-списка — D45/P1.26 Code Guidance) только когда все items
волны 2 (P1.14–P1.25) сданы. GREEN → **P1.26** (CHANGELOG + `Deprecated / Will break in 0.2` + semver-теги
+ PR ветки в `main`) → `/task:plan-close COREX-CORE P1` → и только потом P2.1.

**Итог борда после закрытия P1.24:** **5 🟢 · 19 🟠 · 0 🔴 · 4 ⬜**.

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
  (P1.22 Pending Work).
- open_questions: **OQ-1..15** (OQ-11 никогда не существовала — D57). Блокеры: OQ-5 (P2.1), OQ-15 (P1.26),
  OQ-7 (P2.10/P2.11).
