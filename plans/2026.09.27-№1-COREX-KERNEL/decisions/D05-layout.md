---
id: D05
date: 2026-09-27
status: superseded
item: P03
items: [P03]
supersedes: []
superseded_by: D57
---
# D05 — layout (accepted)

**Actor:** author-choice (claude-opus-5-5/high) по мандату владельца «есть идеальное решение — утверждай» (`brief/00-brief.md` B6)
**Evidence:** RAG:— `findings/00-audit-verification.md`, `artifacts/P00-design/facts/`; RAG:✅ `research/01-external-research.md`, `artifacts/research-rag/`

## Solution

Feature modules во всех пакетах: `CoreX\<Pkg>\<Module>\{Contracts,Data,Enums,Events,Exceptions}` — публичные категории на верхнем уровне модуля; реализация — `CoreX\<Pkg>\<Module>\Internal\…`; Laravel-мост пакета (providers, commands, middleware, Eloquent-модели) — `CoreX\<Pkg>\Laravel\…` либо `Internal`. Корень `src/` пакета: provider + модули. Слоевые «корзины» уровня пакета (`CoreX\Api\Contracts\` на весь пакет) запрещены.

## Why

Локальность изменений и явная граница public/internal по пути (гейт, Roave-baseline `\Internal\`); совпадает с chatom PLAN2.D21 и нынешними фича-каталогами на диске (folding показывает, что модули уже выделены физически).

## Consequences

P03 перенос по move-map инструментом; STR-* статьи constitution переписываются.
