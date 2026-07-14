# HANDOFF — 2026-07-14 — after P1.18

**Next:** ЗАПУСК ВРУЧНУЮ: per-item (D35) — носитель модели workflow-скрипт, НЕ чат

```
Workflow({scriptPath: "plans/COREX-CORE/workflows/wf-corex-core-p1r-remediation.js", resumeFromRunId: "wf_87181b80-667"})
```

| Параметр | Значение |
|:--|:--|
| Model | per-item (D35) — носитель модели workflow-скрипт, НЕ чат |
| Thinking | — (делегировано workflow) |
| Context | NEW SESSION |
| Суть | **P1.18 закрыт 🟢** (Blocker A3 + FSM-набор: `mod_records.seq` монотонный purge-порядок D31, purge всех claim-типов D41, авто-claim обёрткой мигратора, enable() all-or-nothing D40, purged-терминал + `flex:modules:repair`, actor из CurrentActor, wiring из installed.json). Гейт зелёный (101/101 sqlite, 51/51 pg), **CI на ветке зелёный (run 29351086680, 8/8 job)**, mutation-check 2 кейса. Волна продолжается штатно с **P1.19**. |

**Важная находка (D57) — прочитать перед продолжением волны:** предыдущие handoff.md (минимум с
коммита `f404838`, ДО RED-аудита и ДО этой ремедиации) утверждали, что «OQ-11 (golden-шаблон vs
DDL-при-enable) решён владельцем Вариантом (A)» — но `git log -S "OQ-11"` не находит НИ ОДНОГО коммита,
вводящего OQ-11 в `open-questions.md` (там только OQ-1..OQ-9), и фраза «golden-шаблон» в истории репо
встречается исключительно в контексте P2-провижининга (D24), никогда рядом с P1.18. Альтернативного ТЗ
P1.18 «purge = очистка данных, не DDL» не существовало НИ В ОДНОМ коммите. Это была недостоверная
(галлюцинированная) запись, много раз скопированная между handoff'ами без сверки с SSOT — тот же класс
дефекта, за который фаза P1 получила исходный RED-вердикт. **Урок процессу:** будущие docs-curator/
handoff-агенты обязаны проверять OQ-ссылки против `open-questions.md` ПЕРЕД тем, как копировать их в
новый handoff — не доверять прозе предыдущего файла.

**Разрешённые эскалации прошлых items (детали — `phases/P1.md`, блоки «✅ РАЗРЕШЕНО»):**
1. **P1.14 (D47/D48):** phpstan `tests`-paths вынесены в **P1.28**; coverage `--min` ослаблен 80→55 (D47),
   возврат к 80 — time-gated **P1.27**, не блокирует закрытие фазы.
2. **P1.16 (D52):** коннекшн модуль-системы остаётся P1-формой шва. Полный per-call ctx→connection
   резолвинг — предмет P2 (D13). **Anti-weakening-gate** получил floor-исключение
   (`scripts/coverage-min-floor.txt`, 55).
3. **P1.17 (D53/D56):** RED CI (AC-2 тест ложно краснел на `base_path()`-резолвинге реального
   `installed.json` в testbench) — исправлен фикстурой через существующий инъекционный шов, AC-2 не
   ослаблен. CI на ветке зелёный (run `29347512984`) — это же подтверждает Остаточный пункт P1.14 (1):
   `coverage` живьём зелёный под `--min=55`.
4. **P1.18/P1.19 (D57):** ложный DoR-гейт «OQ-11» опровергнут — item'ы разблокированы под существующим
   ТЗ, без re-design.

Волна продолжается **P1.18 → P1.19 → …** по порядку плана. Волна НЕ исполняется через `/task:plan-exec`:
несущие items предписаны `opus/high`, команда пинит sonnet/medium (routing-undershoot запрещён —
plan-protocol §9, D35).

После зелёной волны (порядок обязателен): аудит фазы P1 (команда plan-audit — фаза станет терминальной
только когда все 12 items волны 2 (P1.14–P1.25) сданы; P1.26/P1.27/P1.28 — после/вне блокирующего
критерия аудита).

Аудит проводится **ВСЛЕПУЮ** (без A-списка находок — иначе он превращается в сверку по чек-листу,
D45/P1.26 Code Guidance). GREEN → item **P1.26** (CHANGELOG + `Deprecated / Will break in 0.2` +
semver-теги + PR ветки в `main`) → `/task:plan-close COREX-CORE P1` → и только потом P2.1.

Cold-start reads: `plans/COREX-CORE/plan.md` (§2 Rules, §3 Routing, §5 D28–D57) → этот файл →
`phases/P1.md` (ТЗ item'ов) → `findings/P1-audit-2026-07-13.md` (что чиним, A1..A93) →
`findings/P1R-premortem.md` (что волна НЕ закрывает) → `findings/P1R-coverage-matrix.md` (93 → items).

**Done:** Волна 1 (P1.1–P1.13) исполнена и закоммичена. **Аудит 2026-07-13 — 🔴 RED: 93 находки**
(3 Blocker · 17 Major · 53 Minor · 20 Nit). Гейты были зелёные — они не покрывали заявленное.

**2026-07-14 — фаза переоткрыта и ре-спроектирована** (`/task:plan-design COREX-CORE P1`): статусы 12
items понижены 🟢→🟠 (материальные отклонения §6); **13 новых items P1.14–P1.26**; решения **D28–D45**;
полный список находок восстановлен в `findings/P1-audit-2026-07-13.md`; pre-mortem волны нашёл 6 дыр
в самой ремедиации (D39–D45).

**2026-07-14 — P1.14 попытан, эскалировал дважды, волна ОСТАНОВЛЕНА (D46)**, разобрано владельцем
(D47/D48) — P1.14 **🟠 Done with deviations**, +P1.27/P1.28 (follow-ups вне волны 2).

**2026-07-14 — P1.15 закрыт 🟢** (персистенция CompiledRegistry + рантайм-проекция + deploy-хук).

**2026-07-14 — P1.16 попытан, эскалировал (D49), разобран владельцем (D52):** коннекшн остаётся
P1-формой шва, полный ctx→connection — в P2 (D13). P1.16 **🟠 Done with deviations**.

**2026-07-14 — P1.17 закрыт 🟢, откачен RED-ревью (D53), исправлен (D56):** хрупкий AC-2 тест на
реальном `installed.json` (окружение-зависимость `base_path()` в CI/testbench) заменён контролируемой
фикстурой через существующий шов `InstalledPackages::discover($installedJsonPath)`. CI на ветке зелёный
(run `29347512984`, все job'ы success). P1.17 **🟢 Done**.

**2026-07-14 — P1.18 стоп-условие «OQ-11» расследовано и опровергнуто (D57)** — см. врезку выше.
P1.18/P1.19 разблокированы под существующим ТЗ, эскалация оркестратора (D51/D55, попытки исполнить
P1.19 вне очереди) закрыта тем же путём — очередь снята штатно.

**2026-07-14 — P1.18 закрыт 🟢** (wf-p1r/opus): монотонный `seq`-purge (D31) + purge всех claim-типов
(D41) + авто-claim обёрткой мигратора + enable() all-or-nothing (D40) + FSM-гейты (purged терминален,
`flex:modules:repair` как легальный выход) + actor из `CurrentActor` + wiring codeVersions/migrationPaths
из installed.json. Коммиты `71ba24d` (код+тесты) / `0082a50` (mutation-патчи), CI run `29351086680`
зелёный (8/8). Две known-deviations (не блокеры, см. phases/P1.md P1.18): CHECK actor'а
`mod_lifecycle_log` не приведён к `sys_audit_log` (schema-owner, вне Files, P2); `purged` сделан
терминальным вопреки диаграмме B-10 §5.1 (осознанно, A11 + `flex:modules:repair`-escape).

**Remaining:**
1. **Волна 2 (P1.19–P1.25)** — 7 items, порядок: P1.19 → остальные по порядку плана.
2. **P1.26** — CHANGELOG + теги + PR в `main`. Гейт: GREEN слепого аудита + решение OQ-15.
3. **P1.27** (time-gated, вне волны) — coverage → 80% после обкатки на реальных проектах.
4. **P1.28** (вне волны 2, после неё) — второй phpstan-прогон `flex/modules-lint` на `tests/`.
5. **Фаза P2** (0/13): предусловие P2.1 = OQ-5 (php ^8.4 vs ^8.3).

**Sources of truth:** plan.md (v0.3.0, D1–D57) · phases/P1.md (28 items) · phases/P2.md ·
open-questions.md (OQ-1..15, БЕЗ OQ-11 — см. D57) · findings/{P1-audit-2026-07-13, P1R-premortem,
P1R-coverage-matrix, P1R-rag-verification-2026-07-14, P1-P2-rag-verification-2026-07-12, P2-premortem,
P1-premortem}.md · ~/Vaults/Brain/plans/flex/30_blueprints/B-10_core-module-loader.md · B-11_auth-tenancy.md ·
~/Vaults/Brain/plans/flex/60_contracts/DB_SCHEMA.md (DRAFT) · 50_execution/00_EXECUTION_ROADMAP.md

**Open risks:**
- ~~**🔴 P1.14/P1.16/P1.17 Blocked**~~ — закрыто D47/D48/D52/D56. ~~«OQ-11» (блокер DoR P1.18)~~ —
  опровергнуто D57 (недостоверная запись, не реальное решение владельца) — P1.18 исполняется под
  существующим ТЗ без re-design.
- ~~**Код никогда не пушился**~~ — закрыто P1.14: ветка `p1r-remediation` запушена. Дальше волна пушится
  по каждому item'у на ту же ветку.
- ~~**Coverage-job следующего прогона нужно проверить фактически**~~ — закрыто: CI run `29347512984`
  подтвердил `coverage`: success под `--min=55` вживую (Pending Work P1.14 (1) закрыт).
- ~~**Модуль-система нерабочая в дефолтной конфигурации**~~ (A1/A2) — закрыто P1.16.
- ~~**Компиляцию реестра не вызывает никто**~~ — закрыто P1.15.
- ~~**Гейт компиляции пропускал ровно то, ради чего создан**~~ (A7/A41/A42/A44) — закрыто P1.17.
- **`corex/core` не поставляет ни одной применимой миграции** (A19/A62) — до P1.19 `migrate` у потребителя
  создаёт ноль `sys_*`-таблиц. P1.19 разблокирован (D57), риск закрывается штатным прохождением волны.
- **Тавтологичные тесты** (A4/A11/A13/A25/A32/A57/A65): зелёная сьюта ≠ покрытие. Правило волны —
  mutation-check патчами в репо, прогоняет отдельный верификатор.
- **Ядро де-факто PG16-only, но нигде этого не объявляет** (OQ-12) — boxed-заказчик узнает на площадке.
- OQ-5 (php ^8.4) — блокер P2.1; DB_SCHEMA DRAFT (OQ-6); stancl v4 без релиза (D8/D25).
- Полная карта того, что волна НЕ закрывает — `findings/P1R-premortem.md` (кластеры C1–C8, P1–P7, U1–U6).
- **Минорные ревью-находки P1.15 (2, ATTENTION):** морф-карта строится из `$registry->all()` (фильтруемого
  предикатом активности), а не из `$compiled->entities` — если `moduleActive` перестанет быть дефолтным
  `true`, выключенные модули выпадут из `Relation::morphMap`; deploy-хук `optimizes()` не покрыт тестом
  напрямую (только косвенно fail-loud). Не блокеры, учесть при P2-работе с реестром.

**Workarounds/Deferred/Open questions:**
- workarounds: хвосты волны 1 адресованы поимённо — `findings/P1R-coverage-matrix.md` (93 кода → item).
- deferred: Nit-класс (20 находок, D29); партиционирование `sys_audit_log` (§9.7); fan-out db-poll;
  ленивая гидрация реестра (B-10 §7.1); Infection/MSI; расширяемость `Capability`/`Extend\*` (U4);
  референс-модуль `flex/module-example` (U5); corex/components (D6); DX-генераторы (D26);
  **coverage 80%** (P1.27, D47); **phpstan `tests`-lint** (P1.28, D48); **per-call ctx→connection**
  (P2, D13/D52); **constraint-версии в `Dep` и топосортировка** (P1.17 Scope Excluded, следующая волна).
- open_questions: **OQ-1..15** (OQ-11 никогда не существовала — D57). Блокеры: OQ-5 (P2.1), OQ-15 (P1.26),
  OQ-7 (P2.10/P2.11). Ждут владельца: OQ-12 (PG16 hard requirement), OQ-13 (root vs sys feature flags),
  OQ-14 (реестр префиксов), OQ-6 (DB_SCHEMA), OQ-10 (телеметрия devloop — вне плана).
