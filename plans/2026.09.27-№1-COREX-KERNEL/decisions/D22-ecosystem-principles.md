---
id: D22
date: 2026-09-27
status: accepted
item: P00
items: [P00, P01, P02, P03, P04, P05, P06, P07, P08, P09]
supersedes: []
superseded_by: null
---
# D22 — ecosystem-principles (accepted)

**Actor:** owner 2026-09-27 (`brief/00-brief.md` REPOSITORY-OWNER-MESSAGE)
**Evidence:** RAG:— `findings/00-audit-verification.md`, `artifacts/P00-design/facts/`; RAG:✅ `research/01-external-research.md`, `artifacts/research-rag/`

## Решение

Принципы chatom PLAN2 `research/02-target-architecture.md` §1 (core не знает о пакетах-расширениях; один владелец на FQCN/таблицу/config-ключ/tag/команду/wire-type; пакет самодостаточен; публичное — только объявленное `#[Api]/#[Spi]`; инварианты не конфигурируются; compile at boot → immutable runtime; схема детерминирована; стандарты вместо изобретений; без алиасов до 1.0) — нормы PLAN3 без изменения смысла. Расхождения экосистемы фиксируются в `research/02-target-architecture.md` §12 «Сверка с chatom» и решаются новым D# в обоих планах.

## Почему

Владелец: «они оба будут из одной экосистемы, поэтому принципы должны быть общими» (B3).

## Consequences

Все фазы; кросс-проектная сверка — `research/02` §12.
