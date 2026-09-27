---
id: D12
date: 2026-09-27
status: accepted
item: P00.1
items: [P00.1, P06]
supersedes: []
superseded_by: null
---
# D12 — operations-split (accepted)

**Actor:** author-choice (claude-opus-5-5/high) по мандату владельца «есть идеальное решение — утверждай» (`brief/00-brief.md` B6)
**Evidence:** RAG:— `findings/00-audit-verification.md`, `artifacts/P00-design/facts/`; RAG:✅ `research/01-external-research.md`, `artifacts/research-rag/`

## Решение

`corex/operations` (165 файлов, namespace `CoreX\Api`) делится по швам с независимым потребителем: **operations-engine** (definition, invocation, idempotency, versioning, bulk, cost, validation, HTTP-delivery и OpenAPI-проекция как модули), **tool runtime** (GuardedToolInvoker, schema validation, MCP-проекция; публичный компонент, не `Internal`), **webhooks** (egress: подписки, доставка, SSRF-политика, Standard Webhooks). Имена пакетов/namespace — glossary P00 (namespace = имя пакета). Жёсткая строгость входа API (JSON object, запрет undeclared fields) — инвариант; расширяется только формат конверта через именованную политику, не отключение строгости.

## Почему

F00 A6.1–A7.2, A9.1–A9.3; `pplx-02`: пакет = независимая capability/шов, не слой; «инварианты не конфигурируются» (chatom PLAN2 §1.5). 4–7 пакетов аудита отклонены как избыточные.

## Consequences

P00.1 карта; P06 split.
