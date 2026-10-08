---
id: D37
date: 2026-09-16
status: accepted
item: design
items: [design]
supersedes: []
superseded_by: null
---
# D37 — отключить token admission плана

## Решение

По прямому указанию владельца token/byte budget и read-admission больше не ограничивают
исполнение ни одного item этого плана. Обязательные источники остаются частью контракта,
но их размер, оценка токенов, количество и read-budget diagnostics не блокируют и не
предупреждают START, lint или continuation.

## Почему

Владелец выбрал непрерывное выполнение плана без token-flow ограничений и без отдельного
design pass. Product scope, authority-гейты, dependency DAG, изоляция тестов и declared
Validation этим решением не отменяются.

## Consequences

D19, D20, D23 и D31 остаются неизменёнными историческими evidence carriers. Активный
policy carrier D37 заменяет их token gate для исполнения этого плана.
