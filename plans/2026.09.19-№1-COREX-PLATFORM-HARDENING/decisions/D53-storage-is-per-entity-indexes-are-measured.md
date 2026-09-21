---
id: D53
date: 2026-09-20
status: accepted
item: P10.5
items: [P10, P10.3, P10.4, P10.5, P10.8]
supersedes: []
superseded_by: null
---

# D53 — Storage выбирается по entity, индекс — по измеренному query shape

**Actor:** plan-designer/codex-gpt-5.6-sol/high

**Evidence:** `RAG:✅` PostgreSQL документирует разные свойства GIN, expression,
partial и B-tree indexes; только B-tree может дать sorted output.

## Решение

Existing `AttributeStore` делегирует через resolver по entity; per-field engine
запрещён. Index policy: `none` по умолчанию, measured `expression_btree`, либо
`promoted`. Promoted column принадлежит subject package; attributes сохраняет
canonical value и не делает foreign DDL.

## Почему

Перемешивание engines внутри record ломает атомарность. Индекс без query/data evidence
увеличивает write cost, а широкая promotion нарушает ownership.

## Consequences

Expression index активируется только после cast validation и EXPLAIN receipt; имя
зависит от id+revision. Existing `storage_driver` остаётся default, per-entity map
additive.
