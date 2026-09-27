---
id: D23
date: 2026-09-27
status: accepted
item: P00
items: [P00]
supersedes: []
superseded_by: null
---
# D23 — supersede-plan2 (accepted)

**Actor:** author-choice (claude-opus-5-5/high) по мандату владельца «есть идеальное решение — утверждай» (`brief/00-brief.md` B6)
**Evidence:** RAG:— `findings/00-audit-verification.md`, `artifacts/P00-design/facts/`; RAG:✅ `research/01-external-research.md`, `artifacts/research-rag/`

## Решение

PLAN2 (`2026.09.19-№1-COREX-PLATFORM-HARDENING`) приостанавливается на P17.11 (ожидание владельца) и закрывается как superseded PLAN3: его релизный пакет (кандидат, pinned digests, split, stancl-staging) строится на именах/составе, которые PLAN3 меняет. Остаточные DoD-гейты PLAN2 не переносятся механически: полезные гейты переопределяются фазами PLAN3 по новой архитектуре.

## Почему

D01/D02 делают кандидат PLAN2 недействительным (vendor, имена, состав); исполнять P17.11/P17.12 — работа впустую.

## Consequences

`plans/ACTIVE.md`; handoff PLAN2 — ссылка на PLAN3.
