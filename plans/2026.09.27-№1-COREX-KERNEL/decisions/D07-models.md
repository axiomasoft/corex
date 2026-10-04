---
id: D07
date: 2026-09-27
status: accepted
item: P04
items: [P04]
supersedes: []
superseded_by: null
---
# D07 — models (accepted)

**Actor:** author-choice (claude-opus-5-5/high) по мандату владельца «есть идеальное решение — утверждай» (`brief/00-brief.md` B6)
**Evidence:** RAG:— `findings/00-audit-verification.md`, `artifacts/P00-design/facts/`; RAG:✅ `research/01-external-research.md`, `artifacts/research-rag/`

## Solution

Application API принимает/возвращает immutable `final readonly` DTO (`*Data`/`*View`/`*Result`, ids, value objects); Eloquent-модели — internal и не проходят через `#[Api]`-сигнатуры и события. Замена моделей хостом — только через явный `#[Spi]` (абстрактная base-модель с контрактом «только документированные колонки/связи»), где есть реальный потребитель.

## Why

Схема и поведение Eloquent не становятся SemVer-поверхностью (F00 A5.3: `CoreX\Models`, `Tasks\Models`, `Tenancy\Models`, `Flex\Crm\Models` объявлены public); чатом PLAN2.D24 — та же норма; pplx-02: каждый public-тип — многолетний контракт.

## Consequences

P04 views/DTO, перевод потребителей.
