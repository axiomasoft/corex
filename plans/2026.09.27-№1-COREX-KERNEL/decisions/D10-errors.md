---
id: D10
date: 2026-09-27
status: accepted
item: P04
items: [P04, P06]
supersedes: []
superseded_by: null
---
# D10 — errors (accepted)

**Actor:** author-choice (claude-opus-5-5/high) по мандату владельца «есть идеальное решение — утверждай» (`brief/00-brief.md` B6)
**Evidence:** RAG:— `findings/00-audit-verification.md`, `artifacts/P00-design/facts/`; RAG:✅ `research/01-external-research.md`, `artifacts/research-rag/`

## Решение

Коды ошибок — стабильные строки `<scope>.<code>` (`core.*` у ядра, `<package-slug>.*` у пакетов) как wire-контракт; каждый пакет объявляет коды backed enum (`implements ErrorCode`) и регистрирует `ProblemDefinition(code, type URI, default status, title, retryable)` в реестре, компилируемом при boot (дубль/чужой префикс — ошибка boot). Исключения несут код; HTTP-ответ — RFC 9457 `application/problem+json` c extension-полем `code`. Строковые литералы кодов в коде (`new ApiError('validation_failed', …)`) запрещены гейтом.

## Почему

F00 A9.2 (60+ литералов без реестра); `pplx-08` (enum не расширяется плагинами → string wire + registry + enum для удобства, RFC 9457); chatom PLAN2.D26/P04.3 — строковый `errorCode()` + категории.

## Consequences

P04 (исключения, ErrorCode), P06 (problem+json в HTTP).
