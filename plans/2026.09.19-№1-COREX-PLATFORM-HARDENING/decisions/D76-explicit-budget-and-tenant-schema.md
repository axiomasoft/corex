---
id: D76
date: 2026-09-20
status: accepted
item: P14
items: [P14, P14.3]
supersedes: []
superseded_by: null
---
# D76 — Budget shared, business state tenant-local

**Actor:** plan-designer/codex-gpt-6-astra/high
**Evidence:** RAG:— repository reads и predecessor contracts; внешние patterns,
где применимы, подтверждены `research/P14-design-rag.md`. Defaults — локальное решение.

## Решение

Шесть новых таблиц, три новых migration-файла P14.3 под G-DDL. Shared budget хранит opaque HMAC keys; bulk/subscriptions/deliveries/attempts в tenant DB. Existing migrations не меняются, automatic DDL отсутствует.

## Почему

`research/P14-design.md` фиксирует проверенные ограничения дерева, failure modes
и отвергнутые альтернативы. Решение сохраняет D7/D9/D14, физический tenant scope
и одну contract authority; не вводит нового consumer-specific слоя.

## Consequences

Items P14.3 реализуют точный нормативный срез и negative matrix.
Этот design record не является owner grant для G-DDL/G-SECRET или live rollout.
