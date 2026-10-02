---
id: D11
date: 2026-09-27
status: accepted
item: P06
items: [P06]
supersedes: []
superseded_by: null
---
# D11 — wire-standards (accepted)

**Actor:** author-choice (claude-opus-5-5/high) по мандату владельца «есть идеальное решение — утверждай» (`brief/00-brief.md` B6)
**Evidence:** RAG:— `findings/00-audit-verification.md`, `artifacts/P00-design/facts/`; RAG:✅ `research/01-external-research.md`, `artifacts/research-rag/`

## Solution

Вебхуки — Standard Webhooks v1 (уже реализовано, сохраняется); опубликованные сообщения outbox — CloudEvents 1.0 structured JSON envelope с extension-атрибутами `corex*`; REST — OpenAPI 3.1 contract-first, JSON Schema 2020-12; ошибки — RFC 9457. Breaking payload → новый `type`-суффикс версии + новый `dataschema`.

## Why

Интероперабельность и reference-верификаторы; единый wire экосистемы с chatom PLAN2.D26; MSG-003 corex требует конверт, но без стандарта.

## Consequences

P06; message-policy.md переписывается.
