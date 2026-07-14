# HANDOFF — 2026-07-14 — after P1.22

**Next:** ЗАПУСК ВРУЧНУЮ: workflow (sonnet/medium per-item, D35)

| Параметр | Значение |
|:--|:--|
| Model | per-item (D35) — носитель модели workflow-скрипт, НЕ чат |
| Thinking | — |
| Context | ТА ЖЕ СЕССИЯ (resume, кэш прошлых items переиспользуется) |
| Суть запуска | P1.22 закрыт 🟠 Done with deviations — каскад AC-22 (department reachable, детерминированный докблок-порядок), payload-фолбэк (A87), атомарный `define()`, `SettingChanged` только-на-изменении+afterCommit, `UnsupportedScopeException` для department в Settings, констрейнт-тест через сырой insert (A57/A63), покрытие `all()`/`forget()` (A59). Гейт зелёный (100/100 sqlite, 79/79 pg), mutation-check 2 кейса. Волна продолжается **P1.23 → P1.24 → P1.25**. |

```
Workflow({scriptPath: "plans/COREX-CORE/workflows/wf-corex-core-p1r-remediation.js", resumeFromRunId: "wf_87181b80-667"})
```

**Done:** (с прошлого handoff, after P1.20/D62) **P1.22** закрыт 🟠 Done with deviations (2026-07-14) —
детали `phases/P1.md` P1.22 Completion Notes, сводка `plan.md` §5 (запись после D62). `files_outside_scope`
непустой (`Exceptions/UnsupportedScopeException.php` — предписан Implementation Rules, не в исходных Files;
`scripts/mutation-check.sh` — регистрация 2 кейсов, D45-мандат) → статус 🟠 по правилу докс-куратора
(P1.20/P1.21 прецедент), не 🟢. Коммит(ы) запушены на `p1r-remediation`.

**Разрешённые эскалации прошлых items (детали — `phases/P1.md`, блоки «✅ РАЗРЕШЕНО»):**
1. **P1.14 (D47/D48):** phpstan `tests`-paths вынесены в **P1.28**; coverage `--min` ослаблен 80→55 (D47),
   возврат к 80 — time-gated **P1.27**, не блокирует закрытие фазы.
2. **P1.16 (D52):** коннекшн модуль-системы — P1-форма шва; per-call ctx→connection — P2 (D13).
3. **P1.17 (D53/D56):** хрупкий AC-2 тест исправлен фикстурой; CI зелёный (run `29347512984`).
4. **P1.18/P1.19 (D57):** ложный DoR-гейт «OQ-11» опровергнут — исполнены под существующим ТЗ.
5. **P1.20 (D58/D62):** mutation-check-кейс зарегистрирован в харнессе — процессный блокер снят.

**Remaining:** волна 2 — **P1.23, P1.24, P1.25** (3 items по порядку плана) → **P1.26** (закрытие
фазы: слепой аудит + CHANGELOG + semver-теги + PR в `main`) → `/task:plan-close COREX-CORE P1` → P2.1.
Time-gated **P1.27** и отдельный **P1.28** — после P1.26, не блокируют закрытие фазы (D47/D48).

**Аудит фазы P1** проводится **ВСЛЕПУЮ** (без A-списка — D45/P1.26 Code Guidance) только когда все items
волны 2 (P1.14–P1.25) сданы. GREEN → **P1.26** (CHANGELOG + `Deprecated / Will break in 0.2` + semver-теги
+ PR ветки в `main`) → `/task:plan-close COREX-CORE P1` → и только потом P2.1.

**Итог борда после закрытия P1.22:** **5 🟢 · 17 🟠 · 0 🔴 · 6 ⬜**.

**Sources of truth:** `plans/COREX-CORE/plan.md` (§2 Rules, §3 Routing, §5 D28–D62+, §4 борд) ·
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
- Ядро де-факто PG16-only (OQ-12); OQ-5 (php ^8.4) — блокер P2.1; DB_SCHEMA DRAFT (OQ-6); stancl v4 без релиза.
- Полная карта того, что волна НЕ закрывает — `findings/P1R-premortem.md` (кластеры C1–C8, P1–P7, U1–U6).

**Workarounds/Deferred/Open questions:**
- workarounds: —
- deferred: Nit-класс (20 находок, D29); партиционирование `sys_audit_log` (§9.7); per-тариф retention-числа
  (P1.21 Scope Excluded); Infection/MSI; **coverage 80%** (P1.27); **phpstan `tests`-lint** (P1.28);
  **per-call ctx→connection** (P2, D13/D52); трёхуровневый Settings-каскад с авто-резолвом workspace
  (P1.7/P1.22 Scope Excluded, P2 tenancy); UI фич-флагов + `FeatureFlagToggled`-продюсер (P1.9/P1.22 Scope
  Excluded); кеш настроек `corex.settings.cache_*` (P1.22 Pending Work).
- open_questions: **OQ-1..15** (OQ-11 никогда не существовала — D57). Блокеры: OQ-5 (P2.1), OQ-15 (P1.26),
  OQ-7 (P2.10/P2.11).
