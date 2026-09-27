---
id: D06
date: 2026-09-27
status: accepted
item: P04
items: [P04]
supersedes: []
superseded_by: null
---
# D06 — api-classification (accepted)

**Actor:** author-choice (claude-opus-5-5/high) по мандату владельца «есть идеальное решение — утверждай» (`brief/00-brief.md` B6)
**Evidence:** RAG:— `findings/00-audit-verification.md`, `artifacts/P00-design/facts/`; RAG:✅ `research/01-external-research.md`, `artifacts/research-rag/`

## Решение

Публичность объявляется **на символе** атрибутами `#[Api]` (Application API — вызывать) и `#[Spi(kind: Implement|Call)]` (Extension SPI — реализовать/вызывать при расширении), `since: '1.0'`; всё неклассифицированное — internal (`\Internal\` обязателен для реализации). Per-package машинный манифест `resources/contracts/php-api.json` — SemVer-снимок; гейт: неклассифицированный не-internal тип, ссылка `#[Api]`-сигнатуры на internal/Eloquent/`Collection`, межпакетный импорт неклассифицированного символа — ошибка. `extra.corex.public-api` (список namespace) удаляется. Семантика атрибутов идентична chatom PLAN2.P04; FQCN атрибутов — glossary P00 (коллизия с EAV-пакетом `CoreX\Attributes`).

## Почему

~100 межпакетных импортов вне объявленной поверхности (F00 X1); символ-уровень точнее namespace-списка; Symfony BC promise use-based (`pplx-01`), Shopware — машиночитаемые атрибуты + PHPStan (`pplx-02`); PHPStan native internal не различает пакеты (`pplx-10`).

## Consequences

P04 гейт/манифест/классификация; P01 — страховочный snapshot текущей поверхности.
