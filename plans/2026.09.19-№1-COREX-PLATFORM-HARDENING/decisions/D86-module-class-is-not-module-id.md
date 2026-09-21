---
id: D86
date: 2026-09-20
status: accepted
item: P15.1
items: [P15, P15.1]
supersedes: []
superseded_by: null
---
# D86 — Module class не является module id

**Actor:** plan-designer/codex-gpt-5.6-sol/high
**Evidence:** RAG:— `ModuleDiscovery`, девять `extra.corex.module`,
`mod_modules.name`, `mod_records.module_name`, `mod_lifecycle_log.module_name`.

## Решение

`CorexModule`→`ModuleDefinition` меняет FQCN класса и все
`extra.corex.module`, но не composer module id. `mod_*` хранит composer-имя;
DDL/backfill не требуется и запрещён.

## Почему

Предположение скелета о миграции `mod_*` не подтвердилось:
проверенные таблицы не имеют class/FQCN колонки.

## Consequences

P15.1 атомарно обновляет base, discovery, наследников, Composer metadata
и fixtures; проверяет неизменность девяти module ids и нулевой DDL diff.
