# HANDOFF — 2026-07-14 — after P1.19

**Next:** ЗАПУСК ВРУЧНУЮ: per-item (D35) — носитель модели workflow-скрипт, НЕ чат

```
Workflow({scriptPath: "plans/COREX-CORE/workflows/wf-corex-core-p1r-remediation.js", resumeFromRunId: "wf_87181b80-667"})
```

| Параметр | Значение |
|:--|:--|
| Model | per-item (D35) — носитель модели workflow-скрипт, НЕ чат |
| Thinking | — (делегировано workflow) |
| Context | NEW SESSION |
| Суть | Эскалация P1.16 разобрана владельцем (D52, вариант а) — коннекшн остаётся P1-формой шва, полный ctx→connection вынесен в P2 (D13). P1.16 переведён 🟠 Done with deviations. P1.17/P1.19 (D50/D51) не имели собственной эскалации — только корректно отказались обходить чужое стоп-условие, кода не писали — разблокированы 🔴→⬜. Волна продолжается штатно с P1.17. |

**Разрешённые эскалации (детали — `phases/P1.md`, блоки «✅ РАЗРЕШЕНО»):**
1. **P1.14 (D47/D48):** phpstan `tests`-paths вынесены в **P1.28**; coverage `--min` ослаблен 80→55 (D47),
   возврат к 80 — time-gated **P1.27**, не блокирует закрытие фазы.
2. **P1.16 (D52):** коннекшн модуль-системы остаётся P1-формой шва (`?string $connection` +
   config-connection + ключ по `account->id`) — именно так и было реализовано в коммитах
   `19d5fa4`/`7d1cb10`/`6c715de`. Полный per-call ctx→connection резолвинг (`AccountConnectionResolver`) —
   предмет P2 (согласуется с D13 при проектировании P2), отдельного P1-follow-up item'а не заводится —
   граница P1/P2 уже спроектирована. **Anti-weakening-gate** получил floor-исключение
   (`scripts/coverage-min-floor.txt`, 55) — больше не флагует уже одобренное D47-понижение на каждом
   item'е волны.

**Остаточный пункт (Pending Work P1.14 (1)):** первый прогон волны ПОСЛЕ коммита D47/D52 должен
показать `coverage`: success под `--min=55` вживую в CI (не только арифметически 56.3%≥55) — если
красный, это регрессия, эскалировать.

Волна продолжается **P1.17 → P1.18 → P1.19 → …** по порядку плана (P1.17/P1.19 не начинали
имплементацию — Files/Scope Included/Validation в силе без изменений). Волна НЕ исполняется через
`/task:plan-exec`: несущие items предписаны `opus/high`, команда пинит sonnet/medium (routing-undershoot
запрещён — plan-protocol §9, D35).

После зелёной волны (порядок обязателен): аудит фазы P1 (команда plan-audit — фаза станет терминальной
только когда все 12 items волны 2 (P1.14–P1.25) сданы; P1.26/P1.27/P1.28 — после/вне блокирующего
критерия аудита).

Аудит проводится **ВСЛЕПУЮ** (без A-списка находок — иначе он превращается в сверку по чек-листу,
D45/P1.26 Code Guidance). GREEN → item **P1.26** (CHANGELOG + `Deprecated / Will break in 0.2` +
semver-теги + PR ветки в `main`) → `/task:plan-close COREX-CORE P1` → и только потом P2.1.

Cold-start reads: `plans/COREX-CORE/plan.md` (§2 Rules, §3 Routing, §5 D28–D52) → этот файл →
`phases/P1.md` (ТЗ item'ов) → `findings/P1-audit-2026-07-13.md` (что чиним, A1..A93) →
`findings/P1R-premortem.md` (что волна НЕ закрывает) → `findings/P1R-coverage-matrix.md` (93 → items).

**Done:** Волна 1 (P1.1–P1.13) исполнена и закоммичена. **Аудит 2026-07-13 — 🔴 RED: 93 находки**
(3 Blocker · 17 Major · 53 Minor · 20 Nit). Гейты были зелёные — они не покрывали заявленное.

**2026-07-14 — фаза переоткрыта и ре-спроектирована** (`/task:plan-design COREX-CORE P1`):
- статусы 12 items понижены 🟢→🟠 (материальные отклонения §6), Completion Notes сохранены как история;
- **13 новых items P1.14–P1.26**; решения **D28–D45**; **OQ-10..OQ-15**;
- полный список находок восстановлен из журнала прогона в `findings/P1-audit-2026-07-13.md`
  (в фазе жили только Blocker/Major) + матрица покрытия 93/93;
- **pre-mortem волны (3 линзы)** нашёл 6 дыр в самой ремедиации — все внесены (D39–D45).

**2026-07-14 — P1.14 попытан, эскалировал дважды, волна ОСТАНОВЛЕНА (D46)**, разобрано владельцем
(D47/D48) — P1.14 **🟠 Done with deviations**, +P1.27/P1.28 (follow-ups вне волны 2).

**2026-07-14 — P1.15 закрыт 🟢** (персистенция CompiledRegistry + рантайм-проекция + deploy-хук).

**2026-07-14 — P1.16 попытан, эскалировал (D49), волна ОСТАНОВЛЕНА** — код реализован
(`ModuleRegistryCache` без `tags()`, эпоха-инвалидация D39, `isActive()`∩реестр), гейт зелёный
(95/95 sqlite, 44/44 pg), но Scope Included требовал полного ctx→connection резолвинга, которого
Files item'а не покрывали и который упирается в P2-инфру (D13). Попытки возобновить на **P1.17**
(D50) и **P1.19** (D51, ESCALATION оркестратора) корректно отклонены тем же стоп-условием —
имплементация НЕ начата, деревья по обоим item'ам чистые, коммитов нет.

**2026-07-14 — эскалация P1.16 разобрана владельцем (D52):** вариант (а) принят — коннекшн остаётся
P1-формой шва (уже так реализовано), полный ctx→connection — в P2 (D13), отдельного item'а не заводим.
P1.16 переведён **🟠 Done with deviations**, эскалация снята. P1.17/P1.19 разблокированы 🔴→⬜ (не имели
собственной эскалации). Anti-weakening-gate получил floor-исключение под D47. Волна продолжается с
P1.17 (`Workflow resumeFromRunId` — см. Launch-block выше).

**Remaining:**
1. **Волна 2 (P1.17–P1.25)** — 9 items (P1.15/P1.16 закрыты, остальные ⬜), порядок:
   P1.17 → P1.18 → P1.19 → остальные по порядку плана.
2. **P1.26** — CHANGELOG + теги + PR в `main`. Гейт: GREEN слепого аудита + решение OQ-15.
3. **P1.27** (time-gated, вне волны) — coverage → 80% после обкатки на реальных проектах.
4. **P1.28** (вне волны 2, после неё) — второй phpstan-прогон `flex/modules-lint` на `tests/`.
5. **Фаза P2** (0/13): предусловие P2.1 = OQ-5 (php ^8.4 vs ^8.3).

**Sources of truth:** plan.md (v0.3.0, D1–D52) · phases/P1.md (28 items) · phases/P2.md ·
open-questions.md (OQ-1..15) · findings/{P1-audit-2026-07-13, P1R-premortem, P1R-coverage-matrix,
P1R-rag-verification-2026-07-14, P1-P2-rag-verification-2026-07-12, P2-premortem, P1-premortem}.md ·
~/Vaults/Brain/plans/flex/30_blueprints/B-10_core-module-loader.md · B-11_auth-tenancy.md ·
~/Vaults/Brain/plans/flex/60_contracts/DB_SCHEMA.md (DRAFT) · 50_execution/00_EXECUTION_ROADMAP.md

**Open risks:**
- ~~**🔴 P1.14/P1.16 Blocked**~~ — закрыто D47/D48/D52, волна продолжается с P1.17. ~~OQ-11 (блокер DoR
  P1.18)~~ — закрыто Вариантом (A), 2026-07-14 (владелец Dmitry Vostrikov, см. `open-questions.md` OQ-11);
  P1.18 переосмыслен под это решение.
- ~~**Код никогда не пушился**~~ — закрыто P1.14: ветка `p1r-remediation` запушена. Дальше волна пушится
  по каждому item'у на ту же ветку.
- **Coverage-job следующего прогона нужно проверить фактически** — CI ещё не видел `--min=55` вживую,
  см. Pending Work P1.14 (1) / Остаточный пункт выше.
- ~~**Модуль-система нерабочая в дефолтной конфигурации**~~ (A1/A2: `tags()` на не-taggable сторе) —
  закрыто P1.16: `ModuleRegistryCache` без тегов, эпоха-инвалидация D39, гейт-зелёные.
- ~~**Компиляцию реестра не вызывает никто**~~ — закрыто P1.15: deploy-хук `optimizes('flex:modules:compile')`
  + fail-loud (`failIfMissing = environment('production')`).
- **`corex/core` не поставляет ни одной применимой миграции** (A19/A62) — до P1.19 `migrate` у потребителя
  создаёт ноль `sys_*`-таблиц. P1.19 разблокирован (D52), риск закрывается штатным прохождением волны.
- **Тавтологичные тесты** (A4/A11/A13/A25/A32/A57/A65): зелёная сьюта ≠ покрытие. Правило волны —
  mutation-check патчами в репо, прогоняет отдельный верификатор.
- **Ядро де-факто PG16-only, но нигде этого не объявляет** (OQ-12) — boxed-заказчик узнает на площадке.
- В рабочем дереве **чужой дифф** (`.gitignore`, `CLAUDE.md`, удалённый `AGENTS.md`, `.claude/**`) — не мой
  и не items волны: исполнителям запрещено его коммитить (сохранён в `stash@{0}`).
- OQ-5 (php ^8.4) — блокер P2.1; DB_SCHEMA DRAFT (OQ-6); stancl v4 без релиза (D8/D25).
- Полная карта того, что волна НЕ закрывает — `findings/P1R-premortem.md` (кластеры C1–C8, P1–P7, U1–U6).
- **Минорные ревью-находки P1.15 (2, ATTENTION):** морф-карта строится из `$registry->all()` (фильтруемого
  предикатом активности), а не из `$compiled->entities` — если `moduleActive` перестанет быть дефолтным
  `true`, выключенные модули выпадут из `Relation::morphMap`; deploy-хук `optimizes()` не покрыт тестом
  напрямую (только косвенно fail-loud). Не блокеры, но стоит учесть при P1.16/P2-работе с реестром.

**Workarounds/Deferred/Open questions:**
- workarounds: хвосты волны 1 адресованы поимённо — `findings/P1R-coverage-matrix.md` (93 кода → item).
- deferred: Nit-класс (20 находок, D29); партиционирование `sys_audit_log` (§9.7) — без него ретеншн-шов
  P1.21 в проде не исполним; fan-out db-poll (1 поллер на аккаунт — стена облачного парка); ленивая
  гидрация реестра (бюджет бута B-10 §7.1); Infection/MSI как машинный оракул тестов; расширяемость
  `Capability`/`Extend\*` для спутников (U4); референс-модуль `flex/module-example` (U5); corex/components
  (D6); DX-генераторы (D26); **coverage 80%** (P1.27, D47); **phpstan `tests`-lint** (P1.28, D48);
  **per-call ctx→connection для reader/registrar** (P2, D13/D52).
- open_questions: **OQ-1..15**. Блокеры: OQ-5 (P2.1), OQ-15 (P1.26), OQ-7 (P2.10/P2.11).
  Ждут владельца: OQ-12 (PG16 hard requirement), OQ-13 (root vs sys feature flags), OQ-14 (реестр префиксов),
  OQ-6 (DB_SCHEMA), OQ-10 (телеметрия devloop — вне плана).
