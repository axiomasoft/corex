# Roadmap — 2026.09.27-№1-COREX-KERNEL

**Обновлён:** 2026-09-29 · **Соответствует plan.md:** P00–P02 закрыты; P03 детализирована (D57, items P03.1–P03.2), ждёт finish + design audit; P04–P09 — skeleton в `backlog/` (катящийся дизайн, D21, D24)

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

Эта таблица — **единственное конкретное per-admission execution-расписание**. Finish 2026-09-28 (повторный, после
дизайна P02) согласовал P00–P02; P00 и P01 закрыты. P02 допускается только после GREEN design audit текущего fingerprint
**и** O-3 (GREEN hosted CI на коммите P01 `d2e84a1`; сейчас не стартует из-за billing GitHub Actions). B02-A объединяет
P02.1 → P02.2: общий инструментарий швов (`check-maps.py`, `p02-transfer.py seams`, `golden-delta.py`), порядок
последовательный. P02.3 — отдельный запуск implementation/xhigh: перенос ≥500 файлов с обязательным сухим прогоном;
стартует только при `seams … 0 blocking`. P02.4 — отдельный запуск на перенесённом дереве. Каждый запуск P02 — full.
Фазы P04–P09 в backlog не входят в finish (D24). Finish 2026-09-29 (после дизайна P03) добавил строки 8–9: P03.1 — гейт раскладки, ретайр гейта tenancy и STR-доктрина; P03.2 — перенос 278 классов implementation/xhigh с сухим прогоном, стартует только после GREEN P03.1. Оба запуска — full. P03 допускается после GREEN design audit текущего fingerprint; hosted CI отложен (D47) и не блокирует.
После ремонта A1–A4 (D46) группы и семантические маршруты сохраняются; предыдущий ATTENTION fingerprint
не открывает исполнение. Нужен GREEN независимого design audit после повторного finish.

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
| 9 · solo P03.2 | `task:plan-run 2026.09.27-№1-COREX-KERNEL P03.2` | `implementation/xhigh` | `full` | `codex/gpt-6-sol/xhigh` | `cursor/grok-4.7/high`, fresh | `codex/gpt-6-sol/xhigh`, ≤2 rounds | P03.1 GREEN; `p03-transfer.py plan … 0 problems` | Перенос 278 классов по карте; сухой прогон; golden без rebaseline; baseline = прогноз 159 |

## P03 — последовательность команд

Все команды фазы по порядку; строки 3–4 — те же запуски, что строки 8–9 execution-sheet (группы и маршруты задаёт только
sheet). Носители плана пишет один автор за раз (D56): после каждого шага исполнителя автор плана сводит результат.

| # | Шаг | Команда | Кто (provider/model + effort) | Native launch | Условие старта |
|:--|:--|:--|:--|:--|:--|
| 0 | Дизайн фазы (выполнен) | `task:plan-design 2026.09.27-№1-COREX-KERNEL P03` | `claude-code/claude-opus-5-5/high` (автор плана) | `/task:plan-design 2026.09.27-№1-COREX-KERNEL P03` | P02 закрыта |
| 1 | Finish дизайна (выполнен) | `task:plan-design 2026.09.27-№1-COREX-KERNEL finish` | `claude-code/claude-opus-5-5/high` (автор плана, D56) | `/task:plan-design 2026.09.27-№1-COREX-KERNEL finish` | шаг 0; `plan-lint` OK |
| 2 | Независимый аудит дизайна | `task:plan-audit 2026.09.27-№1-COREX-KERNEL design` | `codex/gpt-6-sol/high` (не автор дизайна) | `codex -C "$(git rev-parse --show-toplevel)" -m gpt-6-sol -c 'model_reasoning_effort="high"' -a never -s danger-full-access '$ task:plan-audit 2026.09.27-№1-COREX-KERNEL design'` | finish записан; `design-gate` ждёт аудит |
| 3 | Исполнение P03.1 (sheet 8) | `task:plan-run 2026.09.27-№1-COREX-KERNEL P03.1` | `codex/gpt-6-sol/high`; review `cursor/grok-4.7/high` | `codex -C "$(git rev-parse --show-toplevel)" -m gpt-6-sol -c 'model_reasoning_effort="high"' -a never -s danger-full-access '$ task:plan-run 2026.09.27-№1-COREX-KERNEL P03.1'` | design audit GREEN |
| 4 | Исполнение P03.2 (sheet 9) | `task:plan-run 2026.09.27-№1-COREX-KERNEL P03.2` | `codex/gpt-6-sol/xhigh`; review `cursor/grok-4.7/high` | `codex -C "$(git rev-parse --show-toplevel)" -m gpt-6-sol -c 'model_reasoning_effort="xhigh"' -a never -s danger-full-access '$ task:plan-run 2026.09.27-№1-COREX-KERNEL P03.2'` | P03.1 GREEN |
| 5 | Фазовый аудит | `task:plan-audit 2026.09.27-№1-COREX-KERNEL P03` | `codex/gpt-6-sol/high` (не исполнитель P03.2 по сессии) | `codex -C "$(git rev-parse --show-toplevel)" -m gpt-6-sol -c 'model_reasoning_effort="high"' -a never -s danger-full-access '$ task:plan-audit 2026.09.27-№1-COREX-KERNEL P03'` | P03.1, P03.2 GREEN (Phase Assurance: public-contract) |
| 6 | Закрытие фазы | `task:plan-close 2026.09.27-№1-COREX-KERNEL P03` | `claude-code/claude-opus-5-5/high` (автор плана, D56 п.5) | `/task:plan-close 2026.09.27-№1-COREX-KERNEL P03` | фазовый аудит GREEN |
| 7 | Дизайн следующей фазы | `task:plan-design 2026.09.27-№1-COREX-KERNEL P04` | `claude-code/claude-opus-5-5/high` | `/task:plan-design 2026.09.27-№1-COREX-KERNEL P04` | P03 закрыта |
