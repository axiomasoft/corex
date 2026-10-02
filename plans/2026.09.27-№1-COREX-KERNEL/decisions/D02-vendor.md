---
id: D02
date: 2026-09-27
status: accepted
item: P02
items: [P02, P09]
supersedes: []
superseded_by: null
---
# D02 — vendor (accepted)

**Actor:** author-choice (claude-opus-5-5/high) по мандату владельца «есть идеальное решение — утверждай» (`brief/00-brief.md` B6)
**Evidence:** RAG:— `findings/00-audit-verification.md`, `artifacts/P00-design/facts/`; RAG:✅ `research/01-external-research.md`, `artifacts/research-rag/`

## Solution

Composer vendor — `axiomasoft`. Ядро — `axiomasoft/corex`; остальные пакеты — `axiomasoft/corex-<name>` (kebab-case, `<name>` из glossary P00). Module id = composer-имя (EXT-009 сохраняется, значения меняются). Split-репозитории — `axiomasoft/corex-*`.

## Why

Vendor `corex` на Packagist занят сторонним владельцем (17 пакетов, `corex/support`, `corex/testing`, …) — опубликовать текущие имена невозможно (`http-01`, F00 A12.6). Единый vendor экосистемы с chatom (`axiomasoft/chatom*`, chatom PLAN2.D17).

## Consequences

P02.* переименование composer-имён, module id, config/publish-идентификаторов по glossary; P09 split/release.
