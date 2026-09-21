---
id: D75
date: 2026-09-20
status: accepted
item: P14
items: [P14, P14.4, P14.5]
supersedes: []
superseded_by: null
---
# D75 — Четыре бюджета резервируются атомарно; Q4 закрыт

**Actor:** plan-designer/codex-gpt-6-astra/high
**Evidence:** RAG:— repository reads и predecessor contracts; внешние patterns,
где применимы, подтверждены `research/P14-design-rag.md`. Defaults — локальное решение.

## Решение

Global enforce, account/client/actor observe по умолчанию. Единая shared SQL authority, одна transaction; terminal CAS refund, unknown expiry без refund. Bulk оплачивает admission и каждый batch, а не удерживает full export estimate.

## Почему

`research/P14-design.md` фиксирует проверенные ограничения дерева, failure modes
и отвергнутые альтернативы. Решение сохраняет D7/D9/D14, физический tenant scope
и одну contract authority; не вводит нового consumer-specific слоя.

## Consequences

Items P14.4, P14.5 реализуют точный нормативный срез и negative matrix.
Этот design record не является owner grant для G-DDL/G-SECRET или live rollout.
