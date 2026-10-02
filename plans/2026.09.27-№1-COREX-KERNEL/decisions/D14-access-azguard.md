---
id: D14
date: 2026-09-27
status: accepted
item: P07
items: [P07]
supersedes: []
superseded_by: null
---
# D14 — access-azguard (accepted)

**Actor:** author-choice (claude-opus-5-5/high) по мандату владельца «есть идеальное решение — утверждай» (`brief/00-brief.md` B6)
**Evidence:** RAG:— `findings/00-audit-verification.md`, `artifacts/P00-design/facts/`; RAG:✅ `research/01-external-research.md`, `artifacts/research-rag/`

## Solution

`corex-access` владеет контрактами авторизации платформы (tenant-boundary, entity-context, scope) без зависимости от AzGuard; интеграция с AzGuard — отдельный integration-пакет-мост, требующий опубликованный стабильный релиз AzGuard. Namespace `CoreX\Auth` → по glossary (`CoreX\Access`). Никаких VCS/`@dev` зависимостей в релизных пакетах.

## Why

F00 A2.4, A14.1; доктрина: «AzGuard независимо владеет своей ролевой системой … access может документировать необязательный адаптер» (`docs/dev-doctrine.md` §1).

## Consequences

P07; owner-gate публикации AzGuard (вне репо) — только для моста.
