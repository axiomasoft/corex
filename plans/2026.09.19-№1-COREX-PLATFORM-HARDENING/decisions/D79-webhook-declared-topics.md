---
id: D79
date: 2026-09-20
status: accepted
item: P14
items: [P14, P14.11]
supersedes: []
superseded_by: null
---
# D79 — Webhook topics — проекция объявленных messages

**Actor:** plan-designer/codex-gpt-6-astra/high
**Evidence:** RAG:— repository reads и predecessor contracts; внешние patterns,
где применимы, подтверждены `research/P14-design-rag.md`. Defaults — локальное решение.

## Решение

P5 registry authority, immutable topic profile, subscription pins major/topic version. PII allow-list, explicit sensitive ability, secret class prohibited. Endpoint changes do not redirect stored delivery.

## Почему

`research/P14-design.md` фиксирует проверенные ограничения дерева, failure modes
и отвергнутые альтернативы. Решение сохраняет D7/D9/D14, физический tenant scope
и одну contract authority; не вводит нового consumer-specific слоя.

## Consequences

Items P14.11 реализуют точный нормативный срез и negative matrix.
Этот design record не является owner grant для G-DDL/G-SECRET или live rollout.
