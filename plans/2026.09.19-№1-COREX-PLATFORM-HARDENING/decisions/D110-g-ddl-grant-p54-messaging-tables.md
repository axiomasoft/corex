---
id: D110
date: 2026-09-21
status: accepted
item: P5.4
items: [P5, P5.4]
supersedes: []
superseded_by: null
owner_gate: G-DDL
branch: main
scope: packages/core/database/migrations/pg
---
# D110 — Owner-gate G-DDL на DDL messaging (P5.4)

**Actor:** owner/vostrikov (human)

**Evidence:** RAG:— явный запуск `task:plan-exec 2026.09.19-№1-COREX-PLATFORM-HARDENING P5.4`
2026-09-21; D27 (tenant-schema placement); спецификация `phases/P5/P5.4.md`.

## Решение

Владелец **выдаёт** `[OWNER-GATE:G-DDL]` на исполнение **только** item `P5.4` в ветке
`main` в объёме `packages/core/database/migrations/pg/`:

- `2026_09_20_100000_create_sys_outbox_messages_table.php`;
- `2026_09_20_100001_create_sys_inbox_messages_table.php`.

Гейт **не** расширяется на PHP-код relay/outbox (P5.5+), правки существующих миграций
или таблицы `DB_SCHEMA.md` FROZEN v1.0.

## Почему

N05: durable outbox/inbox отсутствуют; без новых таблиц фаза P5 не исполнима. Размещение
в tenant-schema согласовано D27; единственный DDL-item фазы несёт гейт по D14.
