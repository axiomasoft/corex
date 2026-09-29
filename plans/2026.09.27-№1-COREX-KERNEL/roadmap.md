# Roadmap — 2026.09.27-№1-COREX-KERNEL

**Обновлён:** 2026-09-29 · **Соответствует plan.md:** P00–P03 закрыты GREEN; P04 детализирована (D62–D64); finish P00–P04 выполнен (D65), независимый design audit нового fingerprint ожидается; P05–P09 — skeleton в `backlog/` (D21/D24)

## Жизненный цикл плана (D21, D24)

Для каждой фазы по порядку: `task:plan-design 2026.09.27-№1-COREX-KERNEL Pn` (`backlog/Pnn.md` → `phases/Pnn/`;
досье/примеры/runbook/self-check, ресерч с captures) → `task:plan-design 2026.09.27-№1-COREX-KERNEL finish` →
`task:plan-audit 2026.09.27-№1-COREX-KERNEL design` (независимая модель; только детализированные фазы) → исполнение строк
execution-sheet → закрытие фазы → дизайн следующей по фактическому коду. Дизайн фаз вперёд не делается (B5).

## Карта фаз

| Фаза | Суть | Маршрут (детали — execution-sheet) | Зависимости | Owner gate |
|:--|:--|:--|:--|:--|
| P00 | glossary, карты, архитектура, supersede PLAN2 | frontier/high | — | — |
| P01 | окружение PHP 8.4, платформа, golden, ratchet-гейты, CI supply chain | impl/high | P00 | O-1 расширения php8.4, O-2 роль PG, O-3 push + live CI |
| P02 | швы ядра/operations, механический перенос, идентификаторы, гейт полноты | impl/high, impl/xhigh (P02.3) | P01 | O-3 (hosted CI P01) до старта |
| P03 | feature modules + Internal (D57), гейт раскладки | impl/high, impl/xhigh (P03.2) | P02 | — |
| P04 | #[Api]/#[Spi], DTO, ошибки | impl/high, fr/high | P03 | — |
| P05 | typed config, композиция, порты | impl/high | P04 | — |
| P06 | operations/tools/webhooks, wire | impl/high, fr/high | P05 | — |
| P07 | tenancy-движок, impersonation, access, развязка | impl/high, fr/high | P05 | публикация AzGuard (Q2, только мост) |
| P08 | схема baseline v1 | impl/high–xhigh, fr/high | P07 | — |
| P09 | качество, docs, split, `1.0.0-rc.1` | impl/high | P08 | публикация (Packagist, split repos, теги) |

## Критический путь и риски

| Риск | Где | Митигация |
|:--|:--|:--|
| PHP 8.4 без расширений, PG без роли (хост, 2026-09-28) | P01+ | `scripts/php84 --check`, owner gates O-1/O-2 (`research/P01-design.md` §7); иначе стоп |
| Массовые переносы ломают поведение | P02, P03 | golden P01, per-package тесты, карта + инструмент, сухой прогон |
| Замена движка tenancy | P07 | frontier-решение с замером, адаптер, PG-матрица |
| Расхождение принципов с chatom | все | D22, `research/02` §12, сверка при дизайне каждой фазы |

<!-- execution-sheet/v1 -->

Эта таблица — **единственное конкретное per-admission execution-расписание**.
P00–P03 закрыты GREEN. Строки 1–9 сохраняют историю принятых запусков;
D47 откладывает push/hosted CI. P04 строки 10–13 подтверждены finish
2026-09-29 (D65): четыре последовательных solo, без изменения маршрутов/review.
Пока независимый GREEN design audit текущего finish fingerprint не записан,
строки P04 не являются execution admission. P05–P09 backlog не входят в finish (D24).
P04.1 → P04.2 → P04.3 → P04.4: first report tooling, then classified/DTO surface,
then typed registry, then fixed strict cutover. Public semantics требуют frontier/high
для первых трёх, последняя строка implementation/high. Все reviews full и другой моделью.

| Admission | Command | Route | Review | Executor (provider/model + effort) | Independent Reviewer + effort or self-check | Auto-fix executor + effort/limit | Checkpoint | Order / short risk reason |
|:--|:--|:--|:--|:--|:--|:--|:--|:--|
| 1 · B00-A | `task:plan-run 2026.09.27-№1-COREX-KERNEL P00.1 P00.2 P00.3` | `frontier/high` | `full` | `codex/gpt-6-astra/high` | `cursor/grok-4.7/high`, fresh | `codex/gpt-6-astra/high`, ≤2 rounds | — | P00.1 → P00.2 → P00.3; имена и карты ядра |
| 2 · solo P01.1 | `task:plan-run 2026.09.27-№1-COREX-KERNEL P01.1` | `implementation/high` | `full` | `codex/gpt-6-sol/high` | `cursor/grok-4.7/high`, fresh | `codex/gpt-6-sol/high`, ≤2 rounds | O-1/O-2 preflight | После P00; окружение, манифесты, совместимость тестового стека |
| 3 · B01-B | `task:plan-run 2026.09.27-№1-COREX-KERNEL P01.2 P01.3` | `implementation/high` | `full` | `codex/gpt-6-sol/high` | `cursor/grok-4.7/high`, fresh | `codex/gpt-6-sol/high`, ≤2 rounds | P01.1 GREEN | P01.2 → P01.3; golden до изменения analysis/anti-weakening |
| 4 · solo P01.4 | `task:plan-run 2026.09.27-№1-COREX-KERNEL P01.4` | `implementation/high` | `full` | `codex/gpt-6-sol/high` | `cursor/grok-4.7/high`, fresh | `codex/gpt-6-sol/high`, ≤2 rounds | P01.3 GREEN; O-3 после item | CI потребляет гейты P01.1–P01.3; live CI до P02 |
| 5 · B02-A | `task:plan-run 2026.09.27-№1-COREX-KERNEL P02.1 P02.2` | `implementation/high` | `full` | `codex/gpt-6-sol/high` | `cursor/grok-4.7/high`, fresh | `codex/gpt-6-sol/high`, ≤2 rounds | design audit GREEN; O-3 GREEN | P02.1 → P02.2; SPI ядра/engine без изменения вывода, seams 136 → 18 |
| 6 · solo P02.3 | `task:plan-run 2026.09.27-№1-COREX-KERNEL P02.3` | `implementation/xhigh` | `full` | `codex/gpt-6-sol/xhigh` | `cursor/grok-4.7/high`, fresh | `codex/gpt-6-sol/xhigh`, ≤2 rounds | B02-A GREEN; `seams … 0 blocking` | Механический перенос по картам; сухой прогон; golden без rebaseline |
| 7 · solo P02.4 | `task:plan-run 2026.09.27-№1-COREX-KERNEL P02.4` | `implementation/high` | `full` | `codex/gpt-6-sol/high` | `cursor/grok-4.6/high`, fresh | `codex/gpt-6-sol/high`, ≤2 rounds | P02.3 GREEN; O-3 для P02 после item | Идентификаторы, гейт полноты, ownership strict; push + hosted CI — владелец |
| 8 · solo P03.1 | `task:plan-run 2026.09.27-№1-COREX-KERNEL P03.1` | `implementation/high` | `full` | `codex/gpt-6-sol/high` | `cursor/grok-4.7/high`, fresh | `codex/gpt-6-sol/high`, ≤2 rounds | design audit GREEN; P02 closed | Гейт раскладки R0–R5 с baseline 436, ретайр tenancy-structure, STR-доктрина |
| 9 · solo P03.2 | `task:plan-run 2026.09.27-№1-COREX-KERNEL P03.2` | `implementation/xhigh` | `full` | `codex/gpt-6-sol/xhigh` | `cursor/grok-4.7/high`, fresh | `codex/gpt-6-sol/xhigh`, ≤2 rounds | P03.1 GREEN; `p03-transfer.py plan … 0 problems` | Перенос 278 классов по карте; manifest/lock sync (D58); сухой прогон; golden без rebaseline; baseline = прогноз 159 |
| 10 · solo P04.1 | `task:plan-run 2026.09.27-№1-COREX-KERNEL P04.1` | `frontier/high` | `full` | `codex/gpt-6-astra/high` | `cursor/grok-4.7/high`, fresh | `codex/gpt-6-astra/high`, ≤2 rounds | finish + GREEN current design audit; P03 closed | Api/Spi/source scanner, report + negatives before mass classification |
| 11 · solo P04.2 | `task:plan-run 2026.09.27-№1-COREX-KERNEL P04.2` | `frontier/high` | `full` | `codex/gpt-6-astra/high` | `cursor/grok-4.7/high`, fresh | `codex/gpt-6-astra/high`, ≤2 rounds | P04.1 GREEN | Type verdicts/DTO/transaction capture/R5=0, reviewed Golden delta and mechanical replay |
| 12 · solo P04.3 | `task:plan-run 2026.09.27-№1-COREX-KERNEL P04.3` | `frontier/high` | `full` | `codex/gpt-6-astra/high` | `cursor/grok-4.7/high`, fresh | `codex/gpt-6-astra/high`, ≤2 rounds | P04.2 GREEN | Error ownership/boot compilation and safe persisted replay; 85 constructor sites |
| 13 · solo P04.4 | `task:plan-run 2026.09.27-№1-COREX-KERNEL P04.4` | `implementation/high` | `full` | `codex/gpt-6-sol/high` | `cursor/grok-4.7/high`, fresh | `codex/gpt-6-sol/high`, ≤2 rounds | P04.2 + P04.3 artifacts GREEN | Strict graph/manifests/Roave/semver/docs, no new product design |

D58: remediation и повторный finish выполнены codex/gpt-6-astra/high по прямому мандату владельца; исходный аудит ATTENTION сохранён. Следующий design audit читает delta A1–A5 в новом независимом контексте.

## P03 — последовательность команд

Все команды фазы по порядку; строки 3–4 — те же запуски, что строки 8–9 execution-sheet (группы и маршруты задаёт только
sheet). Носители плана пишет один автор за раз (D56): после каждого шага исполнителя автор плана сводит результат.

| # | Шаг | Команда | Кто (provider/model + effort) | Native launch | Условие старта |
|:--|:--|:--|:--|:--|:--|
| 0 | Дизайн фазы (выполнен) | `task:plan-design 2026.09.27-№1-COREX-KERNEL P03` | `claude-code/claude-opus-5-5/high` (автор плана) | `/task:plan-design 2026.09.27-№1-COREX-KERNEL P03` | P02 закрыта |
| 1 | Finish дизайна (выполнен) | `task:plan-design 2026.09.27-№1-COREX-KERNEL finish` | `claude-code/claude-opus-5-5/high` (автор плана, D56) | `/task:plan-design 2026.09.27-№1-COREX-KERNEL finish` | шаг 0; `plan-lint` OK |
| 2 | Независимый аудит нового fingerprint | `task:plan-audit 2026.09.27-№1-COREX-KERNEL design` | `codex/gpt-6-astra/xhigh` (отдельный reviewer, свежий контекст; маршрут Task audit) | `codex -C "$(git rev-parse --show-toplevel)" -m gpt-6-astra -c 'model_reasoning_effort="xhigh"' -a never -s danger-full-access '$ task:plan-audit 2026.09.27-№1-COREX-KERNEL design'` | finish записан; `design-gate` ждёт аудит |
| 3 | Исполнение P03.1 (sheet 8) | `task:plan-run 2026.09.27-№1-COREX-KERNEL P03.1` | `codex/gpt-6-sol/high`; review `cursor/grok-4.7/high` | `codex -C "$(git rev-parse --show-toplevel)" -m gpt-6-sol -c 'model_reasoning_effort="high"' -a never -s danger-full-access '$ task:plan-run 2026.09.27-№1-COREX-KERNEL P03.1'` | design audit GREEN |
| 4 | Исполнение P03.2 (sheet 9) | `task:plan-run 2026.09.27-№1-COREX-KERNEL P03.2` | `codex/gpt-6-sol/xhigh`; review `cursor/grok-4.7/high` | `codex -C "$(git rev-parse --show-toplevel)" -m gpt-6-sol -c 'model_reasoning_effort="xhigh"' -a never -s danger-full-access '$ task:plan-run 2026.09.27-№1-COREX-KERNEL P03.2'` | P03.1 GREEN |
| 5 | Закрытие P03 в owning continuation | последний GREEN item P03.2 закрывает фазу по D56.5 | root исполнения, по sheet | текущая сессия | items и independent review GREEN; phase close gate allow |
| 6 | Дополнительный adversarial audit (не обязателен) | `task:plan-audit 2026.09.27-№1-COREX-KERNEL P03` | `codex/gpt-6-astra/xhigh`, свежий reviewer | provider-native launch по Task | только принятое candidate/run-bound `audit-admission/v2`; не автоматически из Phase Assurance |
| 7 | Дизайн следующей фазы | `task:plan-design 2026.09.27-№1-COREX-KERNEL P04` | `claude-code/claude-opus-5-5/high` | `/task:plan-design 2026.09.27-№1-COREX-KERNEL P04` | P03 закрыта |

D59: P03 full replay исправляется текущим owner-authorized root; после GREEN design audit следующий шаг — P03.1 (sheet 8), без ещё одного круга дизайна. Полный audit вместо owning close не назначается автоматически.


## P04 — последовательность команд

| Шаг | Команда | Условие |
|:--|:--|:--|
| Дизайн P04 (выполнен) | `task:plan-design 2026.09.27-№1-COREX-KERNEL P04` | P03 GREEN; current author Sol/high по D62 |
| Finish (выполнен, D65) | `task:plan-design 2026.09.27-№1-COREX-KERNEL finish` | phase finalize + plan-lint GREEN; confirm rows 10–13 |
| Независимый design audit | `task:plan-audit 2026.09.27-№1-COREX-KERNEL design` | новый finish fingerprint; модель != Sol author, fresh context |
| Исполнение | exact commands rows 10–13 | GREEN audit; each predecessor terminal GREEN |
| Закрытие | final GREEN P04.4 owning continuation | item Validation/full reviews + phase close gate GREEN |
| Дизайн P05 | `task:plan-design 2026.09.27-№1-COREX-KERNEL P05` | P04 closed; actual code input |

D62 authoring override applies P04 design; D65 отдельно разрешает текущий finish на Sol/high. Future executor routes stay pinned to
semantic class/effort in Routing; permission to design with Sol is not silently extended
to independent reviews or future frontier execution. Phase audit is never automatic.
