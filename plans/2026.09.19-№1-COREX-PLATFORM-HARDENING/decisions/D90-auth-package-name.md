---
id: D90
date: 2026-09-20
status: superseded
item: P15.12
items: [P15, P15.12]
supersedes: []
superseded_by: D135
---
# D90 — Имя пакета Auth

**Actor:** plan-designer/codex-gpt-5.6-sol/high
**Evidence:** RAG:— Q2, current Composer/split identity; RAG:✅ `research/P15-design-rag.md`.
**Owner gate:** `G-PKG-AUTH`

## Context

Q2 предлагает переименовать Composer-пакет и каталог `corex/auth` / `packages/auth` в
`corex/access` / `packages/access`. Название пакета и предметный PHP namespace — разные поверхности.

## Решение

- **Accept:** переименовать Composer package id, каталог и split-mirror в
  `corex/access` / `packages/access`.
- Сохранить `CoreX\Auth\*`, config keys и database prefixes.
- **Reject:** оставить `corex/auth` и `packages/auth`; P15.12 закрыть как `skipped` с owner evidence.

## Почему

Package identity может описывать access capability, а устойчивый `CoreX\Auth` остаётся
точным предметным namespace; эти решения нельзя связывать неявно.

## Consequences

Требуется явный ответ владельца: proposed status не является grant. Совместимость Composer
задаётся принятой release policy, а не предполагается из `replace`.
