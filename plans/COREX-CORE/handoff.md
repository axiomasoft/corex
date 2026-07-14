# HANDOFF — 2026-07-14 — after P1.17

**Next:** /task:plan-exec COREX-CORE P1.17

Волна ОСТАНОВЛЕНА на P1.17 (D53, RED-ревью) — item закрыт был 🟢, откачен в 🔴 Blocked. Волна НЕ
продолжается на P1.18+ до зелёного CI на P1.17.

**Рекомендуемый путь — точечный фикс (узкий диагноз, ТЗ не меняется по сути), команда выше.**
Задача фикса: `InstalledPackages::discover()` в CI/testbench-окружении не находит `corex/core` в
`vendor/composer/installed.json` через `base_path()`-резолвинг реального файла — заменить AC-2 тест
«resolves requires against a real installed.json» на контролируемую фикстуру через уже существующий
инъекционный шов `$installedJsonPath` (сигнатура `discover()` его уже принимает), сохранив реальный
дефолт-путь в прод-коде НЕТРОНУТЫМ (AC-2 не ослабляется — правило «сверка с реальным installed.json по
умолчанию» остаётся, но покрывается тестом иначе). Также исправить Completion Notes: убрать
недостоверное «гейт зелёный» (относилось к локальному прогону, не к CI) и «7 новых тестов» (фактически
5) — заменить на точные формулировки. После фикса — обязательный зелёный CI на ветке (не только
локальный прогон, D34) перед повторным закрытием item'а.

**Альтернатива — если диагноз при исполнении окажется шире одного теста (ре-дизайн ТЗ):**
```
/task:plan-design COREX-CORE P1.17
```

| Параметр | Значение |
|:--|:--|
| Model | per-item (D35); для Пути A — sonnet/medium (routing §3); для Пути B — opus/high |
| Thinking | Путь A — low/medium; Путь B — high |
| Context | NEW SESSION |
| Суть | P1.17 закрыт был 🟢 (2026-07-14), откачен в 🔴 Blocked (D53) — RED CI на ветке (4 test-лейна + coverage), Completion Notes недостоверны. Волна остановлена. |

**Разрешённые эскалации прошлых items (детали — `phases/P1.md`, блоки «✅ РАЗРЕШЕНО»), волны не касаются:**
1. **P1.14 (D47/D48):** phpstan `tests`-paths вынесены в **P1.28**; coverage `--min` ослаблен 80→55 (D47),
   возврат к 80 — time-gated **P1.27**, не блокирует закрытие фазы.
2. **P1.16 (D52):** коннекшн модуль-системы остаётся P1-формой шва (`?string $connection` +
   config-connection + ключ по `account->id`). Полный per-call ctx→connection резолвинг — предмет P2
   (D13). **Anti-weakening-gate** получил floor-исключение (`scripts/coverage-min-floor.txt`, 55).

**Остаточный пункт (Pending Work P1.14 (1), не относится к текущей остановке):** первый прогон волны
ПОСЛЕ коммита D47/D52 должен показать `coverage`: success под `--min=55` вживую в CI — если красный, это
регрессия, эскалировать.

Волна НЕ исполняется через голый `/task:plan-exec` без учёта routing: несущие items предписаны
`opus/high`, команда пинит sonnet/medium (routing-undershoot запрещён — plan-protocol §9, D35); P1.17
фикс по Пути A — механический с детерминированной Validation, поэтому sonnet/medium уместен (см.
plan.md §3 Routing строку «P1.14, P1.17, P1.20, …»).

Cold-start reads: `plans/COREX-CORE/plan.md` (§2 Rules, §3 Routing, §5 D28–D53) → этот файл →
`phases/P1.md` P1.17 (Pending Work — полный диагноз) → `findings/P1-audit-2026-07-13.md` (A7/A41/A42/A44
— исходные находки, ради которых создан item) → `findings/P1R-coverage-matrix.md`.

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
собственной эскалации).

**2026-07-14 — P1.17 реализован и закрыт 🟢** (позже откачен, см. ниже). `CompilationValidator::validateRequiredFields()`
— `title`/`tablePrefix` обязательны на компиляции (не только в сеттерах, D37); авто-вывод префикса из
composer-имени удалён из `RegistryCompiler`. Коллизия entity handle между модулями →
`CompilationException::duplicateEntityHandle` с обоими именами (было молчаливое перетирание). AC-2: новый
`Compiler\InstalledPackages::discover()` читает `vendor/composer/installed.json` по умолчанию, хардкод
`['corex/core']` убран, инъектируемый шов для тестов сохранён. A85: новая `Compiler\PipelineStages::ORDER`
— единственная константа порядка 8-extender-пайплайна, на неё ссылаются и `RegistryCompiler::compile()`,
и `CompilationValidator` (closed-set membership). `compile()` принял `?callable $onStage` — наблюдаемый
тест AC-18 сверяет порядок с hardcoded-литералом. Mutation-кейсы (`P1.17-tableprefix-skip`,
`P1.17-stage-order-swap`) закоммичены и прогнаны через `scripts/mutation-check.sh`.

**2026-07-14 — RED-ревью откатило P1.17: 🟢→🔴 Blocked (D53).** Закрытие оказалось преждевременным:
- Живой CI на ветке (не локальный прогон) красный на всех 4 test-лейнах (PHP 8.3/8.4 × Laravel 12/13) и
  на Coverage-лейне: новый AC-2 тест «resolves requires against a real installed.json» падает, потому
  что `InstalledPackages::discover()` через `base_path()` в testbench/CI не находит `corex/core` в
  `vendor/composer/installed.json`. Anti-weakening gate и PG-лейн зелёные — красит именно код/тест P1.17.
- Completion Notes утверждали «гейт зелёный» — относится только к ЛОКАЛЬНОМУ прогону исполнителя, не к
  авторитетному CI (D34 требует именно CI); «7 новых тестов» — фактически 5.
- Код/коммиты (`4c3428f`/`bcbfa76`/`1844f90`) НЕ откатываются, остаются в дереве и запушены — Deliverables
  по сути реализованы (tablePrefix обязателен, коллизия handle, AC-18 наблюдаемый тест, оба
  mutation-check красят границу), проблема узкая (один тест/фикстура) и в некорректных Completion Notes.
- `git status --porcelain` на момент остановки: только `.mcp.json`/`.serena/` (служебные, вне item'а,
  stash не создавался — нечего прятать по существу работы P1.17).

**Remaining:**
1. **Разбор остановки P1.17** (см. Next выше) — точечный фикс или ре-дизайн, затем зелёный CI.
2. **Волна 2 (P1.18–P1.25)** — не стартует до п.1; порядок после разбора: P1.18 → P1.19 → остальные.
3. **P1.26** — CHANGELOG + теги + PR в `main`. Гейт: GREEN слепого аудита + решение OQ-15.
4. **P1.27** (time-gated, вне волны) — coverage → 80% после обкатки на реальных проектах.
5. **P1.28** (вне волны 2, после неё) — второй phpstan-прогон `flex/modules-lint` на `tests/`.
6. **Фаза P2** (0/13): предусловие P2.1 = OQ-5 (php ^8.4 vs ^8.3).

**Sources of truth:** plan.md (v0.3.0, D1–D53) · phases/P1.md (28 items) · phases/P2.md ·
open-questions.md (OQ-1..15) · findings/{P1-audit-2026-07-13, P1R-premortem, P1R-coverage-matrix,
P1R-rag-verification-2026-07-14, P1-P2-rag-verification-2026-07-12, P2-premortem, P1-premortem}.md ·
~/Vaults/Brain/plans/flex/30_blueprints/B-10_core-module-loader.md · B-11_auth-tenancy.md ·
~/Vaults/Brain/plans/flex/60_contracts/DB_SCHEMA.md (DRAFT) · 50_execution/00_EXECUTION_ROADMAP.md

**Open risks:**
- **🔴 P1.17 Blocked (D53), АКТИВНО** — RED CI на ветке (4 test-лейна + coverage), недостоверные
  Completion Notes. Волна не продолжается до разбора, см. Next.
- ~~**🔴 P1.14/P1.16 Blocked**~~ — закрыто D47/D48/D52. ~~OQ-11 (блокер DoR P1.18)~~ — закрыто
  Вариантом (A), 2026-07-14 (владелец Dmitry Vostrikov, см. `open-questions.md` OQ-11); P1.18
  переосмыслен под это решение — само P1.18 не тронуто текущей остановкой.
- ~~**Код никогда не пушился**~~ — закрыто P1.14: ветка `p1r-remediation` запушена. Дальше волна пушится
  по каждому item'у на ту же ветку.
- **Coverage-job следующего прогона нужно проверить фактически** — CI ещё не видел `--min=55` вживую,
  см. Pending Work P1.14 (1) / Остаточный пункт выше.
- ~~**Модуль-система нерабочая в дефолтной конфигурации**~~ (A1/A2: `tags()` на не-taggable сторе) —
  закрыто P1.16: `ModuleRegistryCache` без тегов, эпоха-инвалидация D39, гейт-зелёные.
- ~~**Компиляцию реестра не вызывает никто**~~ — закрыто P1.15: deploy-хук `optimizes('flex:modules:compile')`
  + fail-loud (`failIfMissing = environment('production')`).
- **Гейт компиляции пропускал ровно то, ради чего создан** (A7/A41/A42/A44) — код P1.17 деливераблы по
  сути реализует (tablePrefix/title обязательны, коллизия handle, AC-18), но AC-2-тест красит CI —
  риск НЕ закрыт до зелёного CI, см. Next.
- **`corex/core` не поставляет ни одной применимой миграции** (A19/A62) — до P1.19 `migrate` у потребителя
  создаёт ноль `sys_*`-таблиц. P1.19 ждёт разбора P1.17 (порядок волны), риск открыт.
- **Тавтологичные тесты** (A4/A11/A13/A25/A32/A57/A65): зелёная сьюта ≠ покрытие. Правило волны —
  mutation-check патчами в репо, прогоняет отдельный верификатор.
- **Ядро де-факто PG16-only, но нигде этого не объявляет** (OQ-12) — boxed-заказчик узнает на площадке.
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
  **per-call ctx→connection для reader/registrar** (P2, D13/D52); **constraint-версии в `Dep` и
  топосортировка/циклы зависимостей** — P1.17 Scope Excluded, кандидат следующей волны/потребителя.
- open_questions: **OQ-1..15**. Блокеры: OQ-5 (P2.1), OQ-15 (P1.26), OQ-7 (P2.10/P2.11).
  Ждут владельца: OQ-12 (PG16 hard requirement), OQ-13 (root vs sys feature flags), OQ-14 (реестр префиксов),
  OQ-6 (DB_SCHEMA), OQ-10 (телеметрия devloop — вне плана).
