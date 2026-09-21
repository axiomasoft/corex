---
id: D55
date: 2026-09-20
status: accepted
item: P10.7
items: [P5, P10, P10.6, P10.7, P13]
supersedes: []
superseded_by: null
---

# D55 — Attribute mutation message не несёт значений

**Actor:** plan-designer/codex-gpt-5.6-sol/high

**Evidence:** `RAG:—` D25 требует transactional outbox, D26 — declared contract
registry, D28 не обещает ordering. Значения атрибутов могут быть чувствительными.

## Решение

После non-replay mutation explicit translator создаёт
`attributes.values.changed.v1` внутри той же DB transaction. Payload несёт scope,
`RecordRef`, record version, schema hash и sorted ids/keys, но не old/new values.
Search и другие consumers находятся снаружи и перечитывают актуальное разрешённое
состояние.

## Почему

Так сообщение минимизирует утечку данных и сходится при duplicate/out-of-order
delivery, не создавая attributes→search dependency.

## Consequences

Message registry расширяется additively. Write-token replay не добавляет второе
сообщение. Тесты доказывают one mutation/one outbox row и отсутствие raw values.
