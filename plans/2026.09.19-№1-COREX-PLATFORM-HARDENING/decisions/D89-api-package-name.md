---
id: D89
date: 2026-09-20
status: proposed
item: P15.11
items: [P15, P15.11]
supersedes: []
superseded_by: null
---
# D89 — Имя пакета API

**Actor:** plan-designer/codex-gpt-5.6-sol/high
**Evidence:** RAG:— Q1, current Composer/split identity; RAG:✅ `research/P15-design-rag.md`.
**Owner gate:** `G-PKG-API`

## Context

Q1 предлагает переименовать Composer-пакет и каталог `corex/api` / `packages/api` в
`corex/operations` / `packages/operations`. Идентичность пакета и PHP-язык операций — разные
поверхности совместимости.

## Решение

- **Accept:** переименовать Composer package id, каталог и split-mirror в
  `corex/operations` / `packages/operations`.
- Сохранить `CoreX\Api\*`, config keys, route prefixes и operation identifiers.
- **Reject:** оставить `corex/api` и `packages/api`; P15.11 закрыть как `skipped` с owner evidence.

## Почему

Package identity влияет на dependency и publication surface, тогда как сохранение
`CoreX\Api` избегает несвязанного второго breaking change.

## Consequences

Требуется явный ответ владельца: proposed status не является grant. Composer `replace` сам по
себе не переносит PHP FQCN или persisted values и не используется как автоматическая миграция.
