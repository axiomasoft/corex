---
id: D81
date: 2026-09-20
status: accepted
item: P14
items: [P14, P14.13, P14.19]
supersedes: []
superseded_by: null
---
# D81 — Standard signatures; secrets принадлежат host

**Actor:** plan-designer/codex-gpt-6-astra/high
**Evidence:** RAG:— repository reads и predecessor contracts; внешние patterns,
где применимы, подтверждены `research/P14-design-rag.md`. Defaults — локальное решение.

## Решение

HMAC exact id.timestamp.raw_body, strict whsec_32bytes, two active signatures at most. Rotation readiness explicit; synthetic security tests разрешены, activation policy G-SECRET P14.19, live secret/sends требуют конкретной authority.

## Почему

`research/P14-design.md` фиксирует проверенные ограничения дерева, failure modes
и отвергнутые альтернативы. Решение сохраняет D7/D9/D14, физический tenant scope
и одну contract authority; не вводит нового consumer-specific слоя.

## Consequences

Items P14.13, P14.19 реализуют точный нормативный срез и negative matrix.
Этот design record не является owner grant для G-DDL/G-SECRET или live rollout.
