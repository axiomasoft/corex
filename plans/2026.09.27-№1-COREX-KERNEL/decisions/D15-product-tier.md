---
id: D15
date: 2026-09-27
status: accepted
item: P02
items: [P02, P09]
supersedes: []
superseded_by: null
---
# D15 — product-tier (accepted)

**Actor:** author-choice (claude-opus-5-5/high) по мандату владельца «есть идеальное решение — утверждай» (`brief/00-brief.md` B6)
**Evidence:** RAG:— `findings/00-audit-verification.md`, `artifacts/P00-design/facts/`; RAG:✅ `research/01-external-research.md`, `artifacts/research-rag/`

## Solution

`flex/crm` — продукт уровня L2, не часть релизного набора ядра: перемещается из `packages/crm` в `products/flex-crm/` (не split, не публикуется этим планом, остаётся consumer-доказательством). Ядро не зависит от него (как сейчас). Выделение в собственный репозиторий — owner-gate.

## Why

Доктрина L0/L2 (`dev-doctrine.md` §1); F00 X5; монорепо ядра = только релизуемые пакеты ядра (split/lockstep D19).

## Consequences

P02 перенос; P09 split-список.
