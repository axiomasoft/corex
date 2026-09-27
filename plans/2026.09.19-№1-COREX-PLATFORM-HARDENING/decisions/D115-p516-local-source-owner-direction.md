---
id: D115
date: 2026-09-22
status: accepted
item: P5.16
items: [P5.16]
supersedes: []
superseded_by: null
---

# D115 — P5.16 executes as bounded local source remediation

## Решение

Владелец явно поручил завершить P5.16 в текущей Codex-сессии без нового
provider launch. Для этого item разрешены только:

- правка двух ещё не опубликованных migration source files, уже объявленных P5.16;
- связанные test, CHANGELOG и plan receipt из той же item-спеки;
- прогон миграций только на изолированной PostgreSQL базе `corex_test`.

Применение DDL к non-test/production базе, deploy, release, secrets и fleet-действия
не разрешены и не входят в P5.16.

## Почему

Current Task authority path требует provider-owned verifier/nonce-store, которого
Codex adapter не имеет, и поэтому не может материализовать объявленный
`G-DDL-P5R`. Владелец отдельно зафиксировал этот Task runtime gap в Swiss Knife Man
roadmap. Это решение не называет локальный transcript `security-boundary`; оно
сужает текущее исполнение до обратимой source/test-работы.

