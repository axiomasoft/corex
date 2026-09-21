---
id: D82
date: 2026-09-20
status: accepted
item: P14
items: [P14, P14.14]
supersedes: []
superseded_by: null
---
# D82 — Webhook egress pins проверенный адрес до send

**Actor:** plan-designer/codex-gpt-6-astra/high
**Evidence:** RAG:— repository reads и predecessor contracts; внешние patterns,
где применимы, подтверждены `research/P14-design-rag.md`. Defaults — локальное решение.

## Решение

HTTPS443, no redirects/proxy/userinfo/query; validate all A/AAAA and deny private/reserved including mapped IPv6, then pin curl address with TLS host verification. Optional ext-curl absence fails closed. Local-only test transport не входит production bindings.

## Почему

`research/P14-design.md` фиксирует проверенные ограничения дерева, failure modes
и отвергнутые альтернативы. Решение сохраняет D7/D9/D14, физический tenant scope
и одну contract authority; не вводит нового consumer-specific слоя.

## Consequences

Items P14.14 реализуют точный нормативный срез и negative matrix.
Этот design record не является owner grant для G-DDL/G-SECRET или live rollout.
