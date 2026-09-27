---
id: D20
date: 2026-09-27
status: accepted
item: P08
items: [P08]
supersedes: []
superseded_by: null
---
# D20 — schema (accepted)

**Actor:** author-choice (claude-opus-5-5/high) по мандату владельца «есть идеальное решение — утверждай» (`brief/00-brief.md` B6)
**Evidence:** RAG:— `findings/00-audit-verification.md`, `artifacts/P00-design/facts/`; RAG:✅ `research/01-external-research.md`, `artifacts/research-rag/`

## Решение

Перед rc — сквош миграций каждого пакета в baseline `v1` (установок нет); явные имена индексов/FK/unique (≤63, hash-suffix), string вместо DB enum, bigint для счётчиков; владение таблицами по DATA-*; tenant/central scope миграций — явный тег/путь. Механика исполнения (publish vs load, журнал) — frontier-решение P08 с ресерчем и сверкой с chatom PLAN2.D25.

## Почему

D01 (нет установок); DATA-005 «миграции неизменяемы» действует с rc; единая схема-политика экосистемы.

## Consequences

P08.
