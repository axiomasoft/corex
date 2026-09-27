---
id: D03
date: 2026-09-27
status: accepted
item: P01
items: [P01]
supersedes: []
superseded_by: null
---
# D03 — platform (accepted)

**Actor:** author-choice (claude-opus-5-5/high) по мандату владельца «есть идеальное решение — утверждай» (`brief/00-brief.md` B6)
**Evidence:** RAG:— `findings/00-audit-verification.md`, `artifacts/P00-design/facts/`; RAG:✅ `research/01-external-research.md`, `artifacts/research-rag/`

## Решение

PHP `^8.4` во всех пакетах; Laravel `^12.0|^13.0` во всех пакетах (включая access); тест-стек и анализаторы — актуальные стабильные мажоры на дату P01 (Pest/PHPUnit/Testbench/Larastan/PHPStan/Rector/Pint — VERIFY-AT-EXEC по Packagist). CI-матрица: PHP 8.4/8.5 × Laravel 12/13; PostgreSQL — актуальные поддерживаемые мажоры (выбор P01 с ресерчем).

## Почему

Roave BC 8.22 требует PHP `~8.4|~8.5` (`pplx-09`); PHP 8.4 active support до 2026-12-31, 8.5 — до 2027-12-31; Laravel 13 вышел 2026-03-17 (`pplx-03`); tenancy уже `^8.4`; chatom PLAN2.D18 — PHP ^8.4. Нет потребителей → нет причины держать 8.3.

## Consequences

P01.1: окружение PHP 8.4 (предусловие: на хосте PHP 8.3, Docker недоступен — F00 A12.7), апгрейд манифестов, снятие L13-блокера access.
