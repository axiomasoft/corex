# HANDOFF — 2026-07-14 — after P1.20

**Next:** /task:plan-design COREX-CORE P1.20

Волна ОСТАНОВЛЕНА на P1.20 (🔴 Blocked, D58) — не продолжать на P1.21+ до фикса. Стоп-условие
процессное (Major), не архитектурное: инженерная часть P1.20 сделана и независимо подтверждена ревью
GREEN, но mutation-check-кейс не зарегистрирован в каноническом харнессе (мандат D45).

**Рекомендация владельцу (не обязательно re-design — фикс инфраструктурный):** предпочтительный путь —
точечный фикс, а не полный `plan-design`-цикл: зарегистрировать кейс
`plans/COREX-CORE/mutations/P1.20-idcolumns-uuid7-ulid.patch` в `scripts/mutation-check.sh` (правка
одной строки — по образцу уже зарегистрированных кейсов P1.14/P1.16/P1.17/P1.18/P1.19), приложить оба
прогона харнесса (до регистрации — `exit=2`; после — патч красит ровно ожидаемый тест, откат чистый),
затем возобновить волну:
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
| Суть остановки | **P1.20 переведён 🔴 Blocked (был 🟢, D58).** Инженерная часть подтверждена ревью GREEN: единый дефолт `IdStrategy::Uuid7` в `Config::idStrategy()` (A22 закрыт), PG-тест типов колонок через `information_schema.columns.data_type` под 4 стратегиями/3 швами (A4 закрыт), `UlidGenerationTest` пересобирает схему ПОСЛЕ флипа конфига (A21 закрыт), стаб `packages/auth` на `IdColumns` (A23 закрыт), докблоки `IdColumns`/`HasConfiguredId` без лжи про ULID-дефолт (A74/A75 закрыты). Корневой гейт зелёный (lint/analyse/test 100, test:pg 61); тулчейн `packages/auth` прогнан отдельно и зелёный (11 тестов). Единственный дефект — процессный (Major): патч-саботаж `plans/COREX-CORE/mutations/P1.20-idcolumns-uuid7-ulid.patch` применён/откачен ВРУЧНУЮ верификатором (не через харнесс) — краснеет ровно датасет `uuid7`, откат чистый, но `scripts/mutation-check.sh` его не знает, канонический воспроизводимый прогон (мандат D45) падает `exit=2`. |

**Партиальный коммит P1.20:** `8dd4847` — «fix(core): единый дефолт ID-стратегии Uuid7 + PG-тесты типов
колонок (P1.20)», запушен на `p1r-remediation`. Код НЕ откатывается — блокируется только статус item'а
до регистрации mutation-check-кейса.

**Рабочее дерево на момент остановки:** `git status --porcelain` — чисто по путям P1.20 (Files item'а).
Сторонний шум вне scope: `?? .mcp.json`, `?? .serena/` (артефакты MCP/serena-тулинга сессии, не относятся
к P1.20 и не трогались этим item'ом). Стеш НЕ создавался — прятать нечего, изменений по Files item'а в
рабочем дереве нет (всё, что относится к P1.20, уже в `8dd4847`).

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

**Волна ОСТАНОВЛЕНА на P1.20 (D58).** Не продолжать на P1.21+ до фикса mutation-check-регистрации и
подтверждения зелёного харнесс-прогона (см. Trigger выше). Волна НЕ исполняется через `/task:plan-exec`:
несущие items предписаны `opus/high`, команда пинит sonnet/medium (routing-undershoot запрещён —
plan-protocol §9, D35).

После зелёной волны (порядок обязателен): аудит фазы P1 (команда plan-audit — фаза станет терминальной
только когда все 12 items волны 2 (P1.14–P1.25) сданы; P1.26/P1.27/P1.28 — после/вне блокирующего
критерия аудита).

Аудит проводится **ВСЛЕПУЮ** (без A-списка находок — иначе он превращается в сверку по чек-листу,
D45/P1.26 Code Guidance). GREEN → item **P1.26** (CHANGELOG + `Deprecated / Will break in 0.2` +
semver-теги + PR ветки в `main`) → `/task:plan-close COREX-CORE P1` → и только потом P2.1.

Cold-start reads: `plans/COREX-CORE/plan.md` (§2 Rules, §3 Routing, §5 D28–D58) → этот файл →
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

**2026-07-14 — P1.19 закрыт 🟠 Done with deviations** (верификатор): прод-путь миграций sys_*/mod_*. Publish
по скоупам коннекшена (D33/D43: `corex-migrations`+`-tenant` / `flex-modules-migrations`+`-tenant`, мёртвый
`loadMigrationsFrom` на пустой каталог удалён A19/A62, README + `SCHEMA_VERSION` на провайдерах), все
PK/FK/reference через `IdColumns` (D3/A58, хардкод `uuid` убран; исключение `sys_pubsub_messages.id`
bigint), `precision: 6` на всех 22 `timestampTz` (D32/A66/A72), `sys_audit_log.seq bigserial` (D42).
Поколоночная сверка DB_SCHEMA §3.2/§3.3 совпадает; расхождения — только «код > DRAFT-каталог» (2 индекса
A91 задокументированы; seq/`mod_records`-индекс — D42/D31), правки контракта/эскалации не требуют. Гейт:
lint:check passed, analyse 0 errors, test 101/101 (sqlite), test:pg 57/57. Mutation-check: `P1.19-drop-precision`
и `P1.19-hardcode-uuid` — оба воспроизведены харнессом (exit=1 под патчем, чистый откат). Финальный HEAD
ветки `5a8f52d` (CI run `29353759009`, success), код-HEAD `3a9e555` (CI run `29353601440`, 8/8 job).
**Понижен 🟢→🟠:** `files_outside_scope` непуст — `scripts/mutation-check.sh` правился вне Files item'а
(2 записи `TEST_CMD`); прецедент установлен P1.14–P1.18, но формально scope drift. Прочие known-deviations
(не блокеры): `schema_version` реализован как `const`, не config-ключ (`config/`/`Config.php` вне Files);
Nit-мискаунт «24 vs 22» timestampTz в исходной прозе Completion Notes (покрытие полное, исправлено).

**2026-07-14 — P1.20 попытан и закрыт 🟢, откачен RED-ревью (D58): волна ОСТАНОВЛЕНА.** Инженерная часть
подтверждена GREEN (см. врезку Next выше) — единственный дефект процессный: mutation-check-кейс не
зарегистрирован в `scripts/mutation-check.sh`, канонический прогон харнесса падает `exit=2`. Код (`8dd4847`)
остаётся в дереве и запушен. Волна НЕ продолжается на P1.21+ до фикса.

**Remaining:**
1. **P1.20 (заблокирован, D58)** — фикс: регистрация mutation-check-кейса в `scripts/mutation-check.sh`
   + оба прогона харнесса. После фикса — волна продолжается **P1.21 → P1.25** по порядку плана.
2. **Волна 2 (P1.21–P1.25)** — 5 items, ждут разбора P1.20.
3. **P1.26** — CHANGELOG + теги + PR в `main`. Гейт: GREEN слепого аудита + решение OQ-15.
4. **P1.27** (time-gated, вне волны) — coverage → 80% после обкатки на реальных проектах.
5. **P1.28** (вне волны 2, после неё) — второй phpstan-прогон `flex/modules-lint` на `tests/`.
6. **Фаза P2** (0/13): предусловие P2.1 = OQ-5 (php ^8.4 vs ^8.3).

**Sources of truth:** plan.md (v0.3.0, D1–D58) · phases/P1.md (28 items) · phases/P2.md ·
open-questions.md (OQ-1..15, БЕЗ OQ-11 — см. D57) · findings/{P1-audit-2026-07-13, P1R-premortem,
P1R-coverage-matrix, P1R-rag-verification-2026-07-14, P1-P2-rag-verification-2026-07-12, P2-premortem,
P1-premortem}.md · ~/Vaults/Brain/plans/flex/30_blueprints/B-10_core-module-loader.md · B-11_auth-tenancy.md ·
~/Vaults/Brain/plans/flex/60_contracts/DB_SCHEMA.md (DRAFT) · 50_execution/00_EXECUTION_ROADMAP.md

**Open risks:**
- **🔴 P1.20 Blocked** (D58, новое) — процессный Major, не архитектурный: mutation-check-кейс
  `P1.20-idcolumns-uuid7-ulid.patch` не зарегистрирован в харнессе; фикс тривиален (см. Next).
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
- ~~**`corex/core` не поставляет ни одной применимой миграции**~~ — закрыто P1.19 (🟠 Done with
  deviations — files_outside_scope): publish по скоупам коннекшена (D33/D43) + README + `SCHEMA_VERSION`;
  мёртвый `loadMigrationsFrom` удалён. Хардкод `uuid` (A58) и `timestamp(0)` (A66/A72) тоже закрыты
  (IdColumns + `precision: 6`).
- ~~**A22/A4/A21/A23 (ID-strategy дефолт/типы/ulid-шов/auth-стаб)**~~ — инженерно закрыто P1.20
  (ревью GREEN), но item сам БЛОКИРОВАН процессно (mutation-check-регистрация, D58).
- **Тавтологичные тесты** (A4/A11/A13/A25/A32/A57/A65 — часть закрыта P1.20/P1.19): зелёная сьюта ≠
  покрытие. Правило волны — mutation-check патчами в репо, прогоняет отдельный верификатор.
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
