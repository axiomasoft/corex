---
id: D19
date: 2026-09-27
status: accepted
item: P09
items: [P09]
supersedes: []
superseded_by: null
---
# D19 — release (accepted)

**Actor:** author-choice (claude-opus-5-5/high) по мандату владельца «есть идеальное решение — утверждай» (`brief/00-brief.md` B6)
**Evidence:** RAG:— `findings/00-audit-verification.md`, `artifacts/P00-design/facts/`; RAG:✅ `research/01-external-research.md`, `artifacts/research-rag/`

## Solution

Монорепо — источник истины; read-only split-репозитории `axiomasoft/corex-*` через `danharrin/monorepo-split-github-action`; lockstep-версии всех пакетов; опциональный metapackage `axiomasoft/corex-full`; первый релиз `1.0.0-rc.1`. Публикация (Packagist, split repos, теги, push) — owner-gate P09.

## Why

`pplx-11` (lockstep для одного продукта, split read-only, metapackage); chatom PLAN2.D28 — единая модель экосистемы.

## Consequences

P09.
