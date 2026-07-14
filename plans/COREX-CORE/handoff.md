# HANDOFF — 2026-07-14 — after P1.15

**Next:** ЗАПУСК ВРУЧНУЮ: per-item (D35) — носитель модели workflow-скрипт, НЕ чат

| Параметр | Значение |
|:--|:--|
| Model | per-item (D35) — носитель модели workflow-скрипт, НЕ чат |
| Thinking | — (делегировано workflow) |
| Context | NEW SESSION |
| Суть | P1.15 закрыт 🟢 (коммит `b53bcef`): полная персистенция `CompiledRegistry` (8 срезов) + рантайм-проекция в EntityRegistry/морф-карту + `ModuleRegistry::compiled()` + fail-loud + deploy-хук `optimizes()` + проверка возвратов записи (A10/A47). Гейт зелёный (91/91 sqlite, 40/40 pg), mutation-check материализован (3 кейса). Волна продолжается с **P1.16** (кеш-контракт без тегов + инвалидация эпохой D39 + семантика isActive). |

```
Workflow({scriptPath: "plans/COREX-CORE/workflows/wf-corex-core-p1r-remediation.js", resumeFromRunId: "wf_87181b80-667"})
```

Перед запуском проверить: рабочее дерево ветки `p1r-remediation` чистое на HEAD после коммита D47/D48
(composer.json `--min=55`, phpstan.neon без правки — уже был migrations-only; phases/P1.md/plan.md —
докс-коммит отдельно от кода item'а). `stash@{0}` (`P1R-P1.14-partial`, чужой дифф — `.claude/**`,
`docs/**`, `.gitignore`, `CLAUDE.md` и т.п.) НЕ трогать волной, не относится к items плана.

**Разрешённые эскалации P1.14 (детали — `phases/P1.md` P1.14, блок «✅ РАЗРЕШЕНО»):**
1. `phpstan.neon` `tests`-paths — вынесено в отдельный item **P1.28** (второй phpstan-прогон без
   `larastan/extension.neon`); P1.14 Scope Included официально сужен до migrations-only (D48).
2. Coverage `--min=80` — ослаблен до `--min=55` (факт 56.3% на run 29331681218, D47); возврат к 80 —
   time-gated item **P1.27** (после обкатки ядра на реальных проектах), НЕ блокирует закрытие фазы P1.26.

**Остаточный пункт (Pending Work P1.14 (1)):** run 29331681218 проверял ещё СТАРЫЙ `--min=80` (упал
56.3%<80). Первый же push волны после этой правки должен показать `coverage`: success под НОВЫМ
`--min=55` — если он красный, это регрессия, эскалировать, не игнорировать.

**После P1.15–P1.25** волна продолжается штатно: P1.15 → P1.16 → P1.18 — цепочка по данным (OQ-11
закрыт), остальные items волны — по порядку плана. **P1.27/P1.28 — вне волны 2**, не блокируют
переход к P1.26/аудиту фазы (P1.27 явно time-gated, P1.28 — механический follow-up, планируется
отдельно после волны).

Волна НЕ исполняется через `/task:plan-exec`: несущие items предписаны `opus/high`, команда пинит
sonnet/medium (routing-undershoot запрещён — plan-protocol §9, D35).

После зелёной волны (порядок обязателен): аудит фазы P1 (команда plan-audit — фаза станет терминальной
только когда все 12 items волны 2 (P1.14–P1.25) сданы; P1.26/P1.27/P1.28 — после/вне блокирующего
критерия аудита, см. выше).

Аудит проводится **ВСЛЕПУЮ** (без A-списка находок — иначе он превращается в сверку по чек-листу,
D45/P1.26 Code Guidance). GREEN → item **P1.26** (CHANGELOG + `Deprecated / Will break in 0.2` +
semver-теги + PR ветки в `main`) → `/task:plan-close COREX-CORE P1` → и только потом P2.1.

Cold-start reads: `plans/COREX-CORE/plan.md` (§2 Rules, §3 Routing, §5 D28–D48) → этот файл →
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

**2026-07-14 — P1.14 попытан, эскалировал дважды, волна ОСТАНОВЛЕНА (D46)** — ветка `p1r-remediation`
запушена (коммиты `f4f8bd6` impl, `dab0337` docs). CI: `tests-pg`/`anti-weakening-gate`/`quality`/`tests`
зелёные (run 29331681218), job `coverage` красный (56.3%<80, структурная причина — PG-only классы
покрыты только `->group('pg')`-тестами, а `test:coverage` их исключает).

**2026-07-14 — обе эскалации P1.14 разобраны владельцем (D47/D48):**
- Coverage: **ослаблен `--min` 80→55** (D47) — код ядра ещё не обкатан на реальных проектах, доводить
  до 80% сейчас нерационально (переделывать всё равно придётся); 80% зафиксирован как отложенная цель
  в time-gated **P1.27** (стартует после обкатки, не блокирует закрытие фазы).
- PHPStan `tests`-paths: **вынесены из scope P1.14** (D48) в отдельный item **P1.28** (второй
  самостоятельный phpstan-прогон без `larastan/extension.neon` — архитектурное решение, не merge в
  основной `phpstan.neon`); причина — 60 несвязанных Larastan-FP на Pest `$this`-биндинге в 8 файлах
  волны 1.
- P1.14 переведён **🟠 Done with deviations**, эскалация снята, волна продолжается с P1.15
  (`Workflow resumeFromRunId` — см. Launch-block выше).

**Remaining:**
1. **Волна 2 (P1.16–P1.25)** — 10 items (P1.15 закрыт 🟢), порядок: P1.16 → P1.18 — цепочка по данным,
   остальные по порядку плана.
2. **P1.26** — CHANGELOG + теги + PR в `main`. Гейт: GREEN слепого аудита + решение OQ-15.
3. **P1.27** (time-gated, вне волны) — coverage → 80% после обкатки на реальных проектах.
4. **P1.28** (вне волны 2, после неё) — второй phpstan-прогон `flex/modules-lint` на `tests/`.
5. **Фаза P2** (0/13): предусловие P2.1 = OQ-5 (php ^8.4 vs ^8.3).

**Sources of truth:** plan.md (v0.3.0, D1–D48) · phases/P1.md (28 items) · phases/P2.md ·
open-questions.md (OQ-1..15) · findings/{P1-audit-2026-07-13, P1R-premortem, P1R-coverage-matrix,
P1R-rag-verification-2026-07-14, P1-P2-rag-verification-2026-07-12, P2-premortem, P1-premortem}.md ·
~/Vaults/Brain/plans/flex/30_blueprints/B-10_core-module-loader.md · B-11_auth-tenancy.md ·
~/Vaults/Brain/plans/flex/60_contracts/DB_SCHEMA.md (DRAFT) · 50_execution/00_EXECUTION_ROADMAP.md

**Open risks:**
- ~~**🔴 P1.14 Blocked**~~ — закрыто D47/D48, волна продолжается с P1.15. ~~OQ-11 (блокер DoR P1.18)~~ —
  закрыто Вариантом (A), 2026-07-14 (владелец Dmitry Vostrikov, см. `open-questions.md` OQ-11); P1.18
  переосмыслен под это решение.
- ~~**Код никогда не пушился**~~ — закрыто P1.14: ветка `p1r-remediation` запушена, run 29331681218 —
  `tests-pg`/`quality`/`tests`/`anti-weakening-gate` зелёные. Дальше волна пушится по каждому item'у
  на ту же ветку.
- **Coverage-job следующего прогона нужно проверить фактически** — CI ещё не видел `--min=55` вживую
  (только арифметически 56.3%≥55), см. Pending Work P1.14 (1).
- **Модуль-система нерабочая в дефолтной конфигурации** (A1: `tags()` на не-taggable сторе) — до P1.16.
- ~~**Компиляцию реестра не вызывает никто**~~ — закрыто P1.15: deploy-хук `optimizes('flex:modules:compile')`
  (компиляция на `php artisan optimize`) + fail-loud (`failIfMissing = environment('production')`: отсутствие
  реестра в проде = `RuntimeException` с инструкцией, не тихий пустой список).
- **`corex/core` не поставляет ни одной применимой миграции** (A19/A62) — до P1.19 `migrate` у потребителя
  создаёт ноль `sys_*`-таблиц.
- **Тавтологичные тесты** (A4/A11/A13/A25/A32/A57/A65): зелёная сьюта ≠ покрытие. Правило волны —
  mutation-check патчами в репо, прогоняет отдельный верификатор.
- **Ядро де-факто PG16-only, но нигде этого не объявляет** (OQ-12) — boxed-заказчик узнает на площадке.
- В рабочем дереве **чужой дифф** (`.gitignore`, `CLAUDE.md`, удалённый `AGENTS.md`, `.claude/**`) — не мой
  и не items волны: исполнителям запрещено его коммитить (сохранён в `stash@{0}`).
- OQ-5 (php ^8.4) — блокер P2.1; DB_SCHEMA DRAFT (OQ-6); stancl v4 без релиза (D8/D25).
- Полная карта того, что волна НЕ закрывает — `findings/P1R-premortem.md` (кластеры C1–C8, P1–P7, U1–U6).

**Workarounds/Deferred/Open questions:**
- workarounds: хвосты волны 1 адресованы поимённо — `findings/P1R-coverage-matrix.md` (93 кода → item).
- deferred: Nit-класс (20 находок, D29); партиционирование `sys_audit_log` (§9.7) — без него ретеншн-шов
  P1.21 в проде не исполним; fan-out db-poll (1 поллер на аккаунт — стена облачного парка); ленивая
  гидрация реестра (бюджет бута B-10 §7.1); Infection/MSI как машинный оракул тестов; расширяемость
  `Capability`/`Extend\*` для спутников (U4); референс-модуль `flex/module-example` (U5); corex/components
  (D6); DX-генераторы (D26); **coverage 80%** (P1.27, D47); **phpstan `tests`-lint** (P1.28, D48).
- open_questions: **OQ-1..15**. Блокеры: OQ-5 (P2.1), OQ-15 (P1.26), OQ-7 (P2.10/P2.11).
  Ждут владельца: OQ-12 (PG16 hard requirement), OQ-13 (root vs sys feature flags), OQ-14 (реестр префиксов),
  OQ-6 (DB_SCHEMA), OQ-10 (телеметрия devloop — вне плана).
</content>
