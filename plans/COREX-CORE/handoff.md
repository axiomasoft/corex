# HANDOFF — 2026-07-14 — after P1.21

**Next:** /task:plan-design COREX-CORE P1.20

Волна ОСТАНОВЛЕНА на P1.20 (🔴 Blocked, D58) — P1.21 исполнен вопреки стоп-условию по прямому
распоряжению оркестратора (см. врезку ниже); P1.22+ не продолжать до разбора порядка волны + фикса
P1.20. Стоп-условие процессное (Major), не архитектурное: инженерная часть P1.20 сделана и независимо
подтверждена ревью GREEN, но mutation-check-кейс не зарегистрирован в каноническом харнессе (мандат D45).

**Рекомендация владельцу (не обязательно re-design — фикс инфраструктурный):** предпочтительный путь —
точечный фикс, а не полный `plan-design`-цикл: зарегистрировать кейс
`plans/COREX-CORE/mutations/P1.20-idcolumns-uuid7-ulid.patch` в `scripts/mutation-check.sh` (правка
одной строки — по образцу уже зарегистрированных кейсов P1.14/P1.16/P1.17/P1.18/P1.19/**P1.21**),
приложить оба прогона харнесса (до регистрации — `exit=2`; после — патч красит ровно ожидаемый тест,
откат чистый), затем возобновить волну на P1.22:
```
Workflow({scriptPath: "plans/COREX-CORE/workflows/wf-corex-core-p1r-remediation.js", resumeFromRunId: "<последний run P1.20>"})
```
`/task:plan-design COREX-CORE P1.20` вызывается, только если владелец при разборе решит, что фикс
требует пересмотра Validation/Code Guidance item'а — план-протокол требует launch-block именно на
`plan-design` для разбора остановки Blocked-item'а; команда выше — путь, ожидаемый после разбора.

| Параметр | Значение |
|:--|:--|
| Model | per-item (D35) — носитель модели workflow-скрипт, НЕ чат; фикс mutation-check-регистрации — sonnet/low (тривиальная правка) |
| Thinking | — |
| Context | NEW SESSION |
| Суть остановки | **P1.21 закрыт 🟠 Done with deviations (не 🟢, как заявлял исполнитель) — при P1.20 всё ещё 🔴 Blocked.** Вердикт ревью ATTENTION (не GREEN), `files_outside_scope=[scripts/mutation-check.sh]` непуст — тот же критерий, что развёл P1.19/P1.20 в 🟠/🔴 (докс-верификация). Deliverables подтверждены: A17 fillable-diff, A60 killswitch в рантайм-точке, event-time снимок occurredAt/workspaceId, AuditRetention+corex:audit:prune, seq-курсор AC-23; `false_claims=[]`. Гейт локально зелёный (lint/analyse/test 100, test:pg 68), CI на ветке success, mutation-check 2 кейса краснеют под саботажем. Порядок волны нарушен процессно (P1.20 остаётся 🔴) — независимость работ подтверждена, разбор за оркестратором. |

**⚠ Расхождение порядка волны (surface, plan-protocol §10).** Предыдущий handoff (after P1.20) явно
фиксировал: «Волна ОСТАНОВЛЕНА на P1.20 — НЕ продолжать на P1.21+ до фикса». Тем не менее P1.21 исполнен
по прямому распоряжению оркестратора при всё ещё 🔴 Blocked P1.20. Работа P1.21 (Audit) кодово
независима от P1.20 (ID-колонки) — до разбора борд несёт 🔴 (P1.20) рядом с 🟠 (P1.21).

---

**Done:** (с прошлого handoff, after P1.20)
- **P1.21** закрыт **🟠 Done with deviations** — детали см. `phases/P1.md` P1.21 Completion Notes.
  Коммиты на `p1r-remediation`: `ce4ac3a` (код+тесты), `22aa055` (mutation-патчи + регистрация в
  `scripts/mutation-check.sh`), `769f75f` (докс P1.21 — статус в нём ошибочно выставлен 🟢, исправлен
  этим handoff'ом/docs-verification-проходом без изменения кода).

**Разрешённые эскалации прошлых items (детали — `phases/P1.md`, блоки «✅ РАЗРЕШЕНО»):**
1. **P1.14 (D47/D48):** phpstan `tests`-paths вынесены в **P1.28**; coverage `--min` ослаблен 80→55 (D47),
   возврат к 80 — time-gated **P1.27**, не блокирует закрытие фазы.
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

**Итог борда после P1.21:** **4 🟢 · 16 🟠 · 1 🔴 (P1.20) · 7 ⬜**.

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
- workarounds: —
- deferred: Nit-класс (20 находок, D29); партиционирование `sys_audit_log` (§9.7); per-тариф retention-числа
  (P1.21 Scope Excluded); Infection/MSI; **coverage 80%** (P1.27); **phpstan `tests`-lint** (P1.28);
  **per-call ctx→connection** (P2, D13/D52).
- open_questions: **OQ-1..15** (OQ-11 никогда не существовала — D57). Блокеры: OQ-5 (P2.1), OQ-15 (P1.26),
  OQ-7 (P2.10/P2.11).
