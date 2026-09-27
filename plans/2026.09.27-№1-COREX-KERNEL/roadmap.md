# Roadmap — 2026.09.27-№1-COREX-KERNEL

**Обновлён:** 2026-09-27 · **Соответствует plan.md:** P00 детализирована; P01–P09 — skeleton в `backlog/` (катящийся дизайн, D21, D24)

## Жизненный цикл плана (D21, D24)

Для каждой фазы по порядку: `task:plan-design 2026.09.27-№1-COREX-KERNEL Pn` (`backlog/Pnn.md` → `phases/Pnn/`;
досье/примеры/runbook/self-check, ресерч с captures) → `task:plan-design 2026.09.27-№1-COREX-KERNEL finish` →
`task:plan-audit 2026.09.27-№1-COREX-KERNEL design` (независимая модель; только детализированные фазы) → исполнение строк
execution-sheet → закрытие фазы → дизайн следующей по фактическому коду. Дизайн фаз вперёд не делается (B5).

## Карта фаз

| Фаза | Суть | Маршрут (детали — execution-sheet) | Зависимости | Owner gate |
|:--|:--|:--|:--|:--|
| P00 | glossary, карты, архитектура, supersede PLAN2 | frontier/high | — | — |
| P01 | окружение PHP 8.4, платформа, CI supply chain, страховочная сетка | impl/high (план) | P00 | окружение PHP 8.4 (Q1) |
| P02 | vendor, состав пакетов, namespace = путь | impl/medium–high | P01 | — |
| P03 | feature modules + Internal | impl/medium–high | P02 | — |
| P04 | #[Api]/#[Spi], DTO, ошибки | impl/high, fr/high | P03 | — |
| P05 | typed config, композиция, порты | impl/high | P04 | — |
| P06 | operations/tools/webhooks, wire | impl/high, fr/high | P05 | — |
| P07 | tenancy-движок, impersonation, access, развязка | impl/high, fr/high | P05 | публикация AzGuard (Q2, только мост) |
| P08 | схема baseline v1 | impl/high–xhigh, fr/high | P07 | — |
| P09 | качество, docs, split, `1.0.0-rc.1` | impl/high | P08 | публикация (Packagist, split repos, теги) |

## Критический путь и риски

| Риск | Где | Митигация |
|:--|:--|:--|
| Нет PHP 8.4 у исполнителя (хост 8.3, Docker недоступен) | P01+ | P01.1 окружение; без PHP 8.4 — стоп/эскалация |
| Массовые переносы ломают поведение | P02, P03 | golden P01, per-package тесты, карта + инструмент, сухой прогон |
| Замена движка tenancy | P07 | frontier-решение с замером, адаптер, PG-матрица |
| Расхождение принципов с chatom | все | D22, `research/02` §12, сверка при дизайне каждой фазы |

<!-- execution-sheet/v1 -->

Эта таблица — **единственное конкретное per-admission execution-расписание**. Строки фаз P01+ добавляются при их дизайне.

| Admission | Command | Route | Review | Executor (provider/model + effort) | Independent Reviewer + effort or self-check | Auto-fix executor + effort/limit | Checkpoint | Order / short risk reason |
|:--|:--|:--|:--|:--|:--|:--|:--|:--|
| 1 · B00-A | `task:plan-run 2026.09.27-№1-COREX-KERNEL P00.1 P00.2 P00.3` | `frontier/high` | `full` | `claude-code/opus/high` | `codex/gpt-6-astra/high`, fresh | `claude-code/opus/high`, ≤2 rounds | — | P00.1 → P00.2 → P00.3; имена и карты ядра |
