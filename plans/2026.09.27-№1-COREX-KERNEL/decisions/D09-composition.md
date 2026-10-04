---
id: D09
date: 2026-09-27
status: accepted
item: P05
items: [P05]
supersedes: []
superseded_by: null
---
# D09 — composition (accepted)

**Actor:** author-choice (claude-opus-5-5/high) по мандату владельца «есть идеальное решение — утверждай» (`brief/00-brief.md` B6)
**Evidence:** RAG:— `findings/00-audit-verification.md`, `artifacts/P00-design/facts/`; RAG:✅ `research/01-external-research.md`, `artifacts/research-rag/`

## Solution

Один публичный service provider на пакет (`extra.laravel.providers`) — тонкий composition root: регистрирует внутренние providers по обязанностям (bindings, console, http/routes, publishing, integrations). Ни одного анонимного класса с логикой и ни одной фабрики сложнее «new с аргументами из контейнера» в провайдерах — именованные классы (`DefaultReplayAuthorizer`, `ConfiguredCursorKeyring`, …). Дефолты SPI — `bindIf`; декорация — `extend`. Фасады Laravel запрещены вне `Laravel\`/`Internal\Laravel` моста; домен получает порты (PSR-20 clock, session/auth/event порты).

## Why

F00 A4.1, A8.1, A11.1–A11.2 (фасады в 50 файлах src); `pplx-07` (split providers по жизненному циклу, provider — не контейнер логики), `pplx-02` (Laravel — внешний адаптер).

## Consequences

P05 (providers, анонимные классы), P07 (impersonation ports), гейт фасадов.
