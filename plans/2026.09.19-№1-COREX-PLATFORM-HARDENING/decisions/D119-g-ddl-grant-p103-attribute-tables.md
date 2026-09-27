---
id: D119
date: 2026-09-24
status: accepted
item: P10.3
items: [P10, P10.3]
supersedes: []
superseded_by: null
owner_gate: G-DDL
branch: main
scope: packages/attributes/database/migrations
---

# D119 — Owner-gate G-DDL на attribute catalogue tables (P10.3)

**Actor:** owner/vostrikov (human)

**Evidence:** `RAG:—` явный запуск
`task:plan-exec 2026.09.19-№1-COREX-PLATFORM-HARDENING P10.3` 2026-09-24;
D51, D102; спецификация `phases/P10/P10.3.md`.

## Решение

Владелец **выдаёт** `[OWNER-GATE:G-DDL]` на исполнение только item
`P10.3` в ветке `main` в объёме одной новой additive migration пакета
`corex/attributes`, которая создаёт:

- `sys_attribute_definitions`;
- `sys_attribute_key_reservations`;
- `sys_attribute_backfills`.

Гейт не расширяется на изменение существующей migration,
таблицы других пакетов, backfill execution P10.4 или live rollout.

## Почему

D102 разделяет append-only history, mutable current-key reservations и
resumable backfill checkpoints. P10.3 — единственный DDL-owner фазы,
а rollback проверяется только на изолированной тестовой PostgreSQL DB.
