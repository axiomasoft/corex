---
id: D77
date: 2026-09-20
status: accepted
item: P14
items: [P14, P14.6, P14.10]
supersedes: []
superseded_by: null
---
# D77 — Bulk status не означает rollback

**Actor:** plan-designer/codex-gpt-6-astra/high
**Evidence:** RAG:— repository reads и predecessor contracts; внешние patterns,
где применимы, подтверждены `research/P14-design-rag.md`. Defaults — локальное решение.

## Решение

Idempotent submit, durable pending polling, lease/revision fence, cancel на границе батча и сохранённые partial effects. Result24h/metadata30d. Current rights и tenant revalidate на poll/result/worker; no generic CRUD.

## Почему

`research/P14-design.md` фиксирует проверенные ограничения дерева, failure modes
и отвергнутые альтернативы. Решение сохраняет D7/D9/D14, физический tenant scope
и одну contract authority; не вводит нового consumer-specific слоя.

## Consequences

Items P14.6, P14.10 реализуют точный нормативный срез и negative matrix.
Этот design record не является owner grant для G-DDL/G-SECRET или live rollout.
