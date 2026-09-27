---
id: D17
date: 2026-09-27
status: accepted
item: P01
items: [P01, P09]
supersedes: []
superseded_by: null
---
# D17 — quality-bar (accepted)

**Actor:** author-choice (claude-opus-5-5/high) по мандату владельца «есть идеальное решение — утверждай» (`brief/00-brief.md` B6)
**Evidence:** RAG:— `findings/00-audit-verification.md`, `artifacts/P00-design/facts/`; RAG:✅ `research/01-external-research.md`, `artifacts/research-rag/`

## Решение

PHPStan level max + `phpstan-strict-rules` + bleedingEdge на всех пакетах, пустой baseline к rc; Rector (PHP 8.4 + Laravel sets) чистый; Pint; все классы `final`, кроме `#[Spi]`-баз; `readonly` DTO; именованные аргументы; комментарии только «почему»; mutation MSI: security/authz/tenancy ≥95%, operations/idempotency/messaging ≥90%, прочее ≥75%; Roave BC per package — блокирующий с `1.0.0-rc.1`; API-манифест-гейт; `unused-public`/dependency-analyser.

## Почему

F00 X6 (level 6), A13.1; chatom PLAN2 §11 — та же планка экосистемы; `pplx-09`, `pplx-10`.

## Consequences

P01 baseline-guard (только сужается), P09 финальная планка.
