# HANDOFF — 2026-07-14 — after P1.21

**Next:** разбор порядка волны (P1.20 vs P1.21) + фикс регистрации mutation-check P1.20

**⚠ Расхождение порядка волны (surface, plan-protocol §10).** Предыдущий handoff (after P1.20) явно
фиксировал: «Волна ОСТАНОВЛЕНА на P1.20 (🔴 Blocked, D58) — НЕ продолжать на P1.21+ до фикса». Тем не менее
**P1.21 исполнен по прямому распоряжению оркестратора** при всё ещё **🔴 Blocked P1.20**. Работа P1.21
(Audit) кодово независима от P1.20 (ID-колонки), гейт локально зелёный, mutation-check пинится — поэтому
P1.21 закрыт 🟢 по фактам. НО стоп-условие волны формально нарушено. **Оркестратору/владельцу:** либо (а)
закрыть P1.20 тривиальным фиксом (регистрация его mutation-кейса — см. ниже) и признать P1.21-ahead
де-факто состоявшимся, либо (б) явно подтвердить, что параллельное/внеочередное исполнение независимых
items волны разрешено. До разбора борд несёт 🔴 (P1.20) рядом с 🟢 (P1.21).

**Фикс P1.20 (по-прежнему нужен, тривиальный):** зарегистрировать
`plans/COREX-CORE/mutations/P1.20-idcolumns-uuid7-ulid.patch` в `scripts/mutation-check.sh` (одна строка
`TEST_CMD`, по образцу уже зарегистрированных P1.14/P1.16/P1.17/P1.18/P1.19/**P1.21**), приложить оба
прогона харнесса (до — `exit=2`; после — патч красит `uuid7`-датасет, откат чистый).

| Параметр | Значение |
|:--|:--|
| Model | per-item (D35) — носитель модели workflow-скрипт; разбор порядка/фикс регистрации — sonnet/low |
| Thinking | — |
| Context | NEW SESSION |

---

## P1.21 — закрыт 🟢 (2026-07-14)

**Suть:** аудит — sensitive-фильтр, killswitch, event-time снимок, retention, seq-курсор.

- **A17 diff по mass-assignable:** `AuditObserver::record()` фильтрует ОБА пути (create/`getAttributes()`,
  update/`getChanges()`) по `$model->isFillable()` — guarded-поля (`password`/`remember_token`/`id` при
  непустом `$fillable`) в `changes` НЕ попадают вообще (исключаются, не маскируются — B-10 §7.4 п.8);
  fillable-но-sensitive маскируются `••••••` в `old` И `new`. `restored`/`forceDeleted` добавлены (в
  observer + `HasAuditLog` через `registerModelEvent` — no-op на не-SoftDeletes); лишний `deleted` от
  `forceDelete()` свёрнут через `isForceDeleting()`.
- **A60 killswitch:** `Config::auditEnabled()` перенесён из boot-time `HasAuditLog::boot` в рантайм-точку
  `AuditObserver::record()` — теперь гасит и capability-путь (`::observe`), и трейт.
- **Event-time снимок:** `AuditEntry` расширен `occurredAt`/`workspaceId` + `withSnapshot()`; заполняются в
  `QueuedAuditLogger::record()` (синхронный путь); `RecordAuditEntry::handle()` больше НЕ резолвит tenant.
  Boxed/CLI → `workspaceId=null` (If-then honest-null).
- **A64 retention:** `Contracts/AuditRetention` + `Audit/NullAuditRetention` (biнд `corex.bindings.audit_retention`)
  + `Audit/PruneAuditLog` (`corex:audit:prune {--database=}`) — единственный легальный DELETE.
- **A65 курсор:** AC-23 переписан на `sys_audit_log.seq` (D42); тест наблюдаем (entity_id не отсортирован).

**Коммиты (на `p1r-remediation`):** `ce4ac3a` (код+тесты) · `22aa055` (mutation-патчи + регистрация в
`scripts/mutation-check.sh`). **Гейт локально:** lint:check pass · analyse 0 · test 100/100 (sqlite) ·
test:pg 68/68. **Mutation-check:** `P1.21-getattributes-create`, `P1.21-killswitch` — оба краснеют под
саботажем (exit=1), откат чистый. **CI на ветке — ЖДЁТ прогона (D45): «локально зелено» не финально.**

**Known deviations (детали — phases/P1.md P1.21):** (1) `restore()` пишет доп. `updated`-запись (пустой
diff) рядом с `restored` — не подавляется (вне scope); (2) `scripts/mutation-check.sh` правился вне Files
(регистрация 2 кейсов — прецедент P1.17–P1.19); (3) `workspaceId=null` на boxed/CLI.

---

**Разрешённые эскалации прошлых items (детали — `phases/P1.md`, блоки «✅ РАЗРЕШЕНО»):**
1. **P1.14 (D47/D48):** phpstan `tests`-paths → **P1.28**; coverage `--min` 80→55 (D47), возврат — **P1.27**.
2. **P1.16 (D52):** коннекшн модуль-системы — P1-форма шва; per-call ctx→connection — P2 (D13).
3. **P1.17 (D53/D56):** хрупкий AC-2 тест исправлен фикстурой; CI зелёный (run `29347512984`).
4. **P1.18/P1.19 (D57):** ложный DoR-гейт «OQ-11» опровергнут — исполнены под существующим ТЗ.

**Аудит фазы P1** проводится **ВСЛЕПУЮ** (без A-списка — D45/P1.26 Code Guidance) только когда все items
волны 2 (P1.14–P1.25) сданы. GREEN → **P1.26** (CHANGELOG + `Deprecated / Will break in 0.2` + semver-теги
+ PR ветки в `main`) → `/task:plan-close COREX-CORE P1` → и только потом P2.1.

Cold-start reads: `plans/COREX-CORE/plan.md` (§2 Rules, §3 Routing, §5 D28–D58) → этот файл →
`phases/P1.md` (ТЗ item'ов) → `findings/P1-audit-2026-07-13.md` (A1..A93) → `findings/P1R-premortem.md` →
`findings/P1R-coverage-matrix.md`.

**Remaining:**
1. **Порядок волны P1.20↔P1.21** — разбор (см. врезку выше) + фикс регистрации mutation-check P1.20.
2. **Волна 2 (P1.22–P1.25)** — 4 items по порядку плана.
3. **P1.26** — CHANGELOG + теги + PR в `main`. Гейт: GREEN слепого аудита + решение OQ-15.
4. **P1.27** (time-gated, вне волны) — coverage → 80% после обкатки.
5. **P1.28** (вне волны 2) — второй phpstan-прогон `flex/modules-lint` на `tests/`.
6. **Фаза P2** (0/13): предусловие P2.1 = OQ-5 (php ^8.4 vs ^8.3).

**Итог борда после P1.21:** **5 🟢 · 15 🟠 · 1 🔴 (P1.20) · 7 ⬜**.

**Sources of truth:** plan.md (v0.3.0, D1–D58) · phases/P1.md (28 items) · phases/P2.md ·
open-questions.md (OQ-1..15, БЕЗ OQ-11 — D57) · findings/{P1-audit-2026-07-13, P1R-premortem,
P1R-coverage-matrix, P1R-rag-verification-2026-07-14, P1-P2-rag-verification-2026-07-12, P2-premortem,
P1-premortem}.md · ~/Vaults/Brain/plans/flex/30_blueprints/B-10_core-module-loader.md · B-11_auth-tenancy.md ·
~/Vaults/Brain/plans/flex/60_contracts/DB_SCHEMA.md (DRAFT) · 50_execution/00_EXECUTION_ROADMAP.md

**Open risks:**
- **⚠ Порядок волны** — P1.21 исполнен при 🔴 P1.20; требует разбора оркестратора (см. врезку).
- **🔴 P1.20 Blocked** (D58) — процессный Major: mutation-check-кейс не зарегистрирован; фикс тривиален.
- **Тавтологичные тесты** (A4/A11/A13/A25/A32/A57/A65 — часть закрыта): правило волны — mutation-check
  патчами в репо, прогоняет отдельный верификатор.
- **Ядро де-факто PG16-only** (OQ-12); OQ-5 (php ^8.4) — блокер P2.1; DB_SCHEMA DRAFT (OQ-6); stancl v4 без релиза.
- Полная карта того, что волна НЕ закрывает — `findings/P1R-premortem.md` (кластеры C1–C8, P1–P7, U1–U6).

**Workarounds/Deferred/Open questions:**
- deferred: Nit-класс (20 находок, D29); партиционирование `sys_audit_log` (§9.7); per-тариф retention-числа
  (P1.21 Scope Excluded); Infection/MSI; **coverage 80%** (P1.27); **phpstan `tests`-lint** (P1.28);
  **per-call ctx→connection** (P2, D13/D52).
- open_questions: **OQ-1..15** (OQ-11 никогда не существовала — D57). Блокеры: OQ-5 (P2.1), OQ-15 (P1.26),
  OQ-7 (P2.10/P2.11).
