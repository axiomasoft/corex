---
id: D13
date: 2026-09-27
status: accepted
item: P07
items: [P07]
supersedes: []
superseded_by: null
---
# D13 — tenancy-engine (accepted)

**Actor:** author-choice (claude-opus-5-5/high) по мандату владельца «есть идеальное решение — утверждай» (`brief/00-brief.md` B6)
**Evidence:** RAG:— `findings/00-audit-verification.md`, `artifacts/P00-design/facts/`; RAG:✅ `research/01-external-research.md`, `artifacts/research-rag/`

## Решение

Ни один релизный пакет не зависит от неопубликованного upstream-снимка (`axiomasoft/stancl-*` zip из `dev-master`). Tenant-контекст и lifecycle — контракты ядра/tenancy; движок DB-per-account — за адаптером. Выбор движка (собственный vs `stancl/tenancy` 3.10.x stable за адаптером vs опубликованный v4) — frontier-item P07 с ресерчем и замером связности (сейчас 15 из 175 файлов tenancy импортируют `Stancl\*`). Default при отсутствии лучшего варианта — собственный минимальный движок (identification, bootstrap, DB lifecycle, queue context) за контрактами.

## Почему

F00 A2.5; upstream v4 не опубликован, лицензия приватного v4 не подтверждена (`http-01`, `pplx-04`, `pplx-06`) — supply-chain и лицензионный риск.

## Consequences

P07.
