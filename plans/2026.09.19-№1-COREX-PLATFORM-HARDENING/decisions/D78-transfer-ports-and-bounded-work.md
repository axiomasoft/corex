---
id: D78
date: 2026-09-20
status: accepted
item: P14
items: [P14, P14.7, P14.8, P14.9]
supersedes: []
superseded_by: null
---
# D78 — Transfer сохраняет двигатель и получает public export port

**Actor:** plan-designer/codex-gpt-6-astra/high
**Evidence:** RAG:— repository reads и predecessor contracts; внешние patterns,
где применимы, подтверждены `research/P14-design-rag.md`. Defaults — локальное решение.

## Решение

Импорт resume по checkpoint, export unsealed restart новым manifest. CancellationSignal без смены TransferRunner signature, runtime/memory guard и source bounds. API uses public ports + host-provided idempotent factory; не импортирует чужой Internal.

## Почему

`research/P14-design.md` фиксирует проверенные ограничения дерева, failure modes
и отвергнутые альтернативы. Решение сохраняет D7/D9/D14, физический tenant scope
и одну contract authority; не вводит нового consumer-specific слоя.

## Consequences

Items P14.7, P14.8, P14.9 реализуют точный нормативный срез и negative matrix.
Этот design record не является owner grant для G-DDL/G-SECRET или live rollout.
