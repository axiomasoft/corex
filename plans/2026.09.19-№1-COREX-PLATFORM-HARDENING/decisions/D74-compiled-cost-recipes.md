---
id: D74
date: 2026-09-20
status: accepted
item: P14
items: [P14, P14.1, P14.2]
supersedes: []
superseded_by: null
---
# D74 — Стоимость объявляется в module recipe

**Actor:** plan-designer/codex-gpt-6-astra/high
**Evidence:** RAG:— repository reads и predecessor contracts; внешние patterns,
где применимы, подтверждены `research/P14-design-rag.md`. Defaults — локальное решение.

## Решение

Cost fluent metadata в существующем Api extender; pure estimate по validated counts. PHP attribute как второй источник отвергнут. Legacy hashes совместимы; нет DB-запроса ради оценки.

## Почему

`research/P14-design.md` фиксирует проверенные ограничения дерева, failure modes
и отвергнутые альтернативы. Решение сохраняет D7/D9/D14, физический tenant scope
и одну contract authority; не вводит нового consumer-specific слоя.

## Consequences

Items P14.1, P14.2 реализуют точный нормативный срез и negative matrix.
Этот design record не является owner grant для G-DDL/G-SECRET или live rollout.
