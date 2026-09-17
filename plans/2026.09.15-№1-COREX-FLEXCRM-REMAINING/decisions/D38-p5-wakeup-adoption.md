---
id: D38
date: 2026-09-16
status: accepted
item: P5.2
items: [P5.2, P5.3]
supersedes: []
superseded_by: null
---

# D38 — P5 wakeup and root-hint local adoption

## Решение

Владелец разрешил выполнить P5.2 и P5.3 полностью и устранять необходимые
внутренние препятствия плана без промежуточных запросов. Это является matching
owner receipt для Q-P2-018 (G-API) и Q-P2-019 (G-SCHEMA) только для frozen
`wakeup/1` и `routing-hint/1` кандидата.

Разрешены только объявленные P5.2/P5.3 локальные изменения: новые additive
контракты и DTO `corex/wakeup`, opt-in локальная таблица wakeup requests,
opt-in root таблица wakeup hints, guarded tenancy integration, package tests,
configuration, документация и item-owned receipts. Миграции не меняют
существующие Contracts, tenant truth, membership/auth, root scheduler/fleet
policy или автоматически не включают external I/O.

## Почему

Прямое сообщение владельца в текущем root: «делай всё, что нужно по плану»;
требование относится к уже выбранным P5.2/P5.3 и отменяет повторные
процедурные остановки на plan-owned carrier repair. Candidate, scope, safety
invariants и обязательная валидация остаются прежними.

## Consequences

Это не grant для G-DEPENDENCY, G-RELEASE, G-CONSUMER, G-CLOUD, G-TIMING,
credentials, secrets, публикаций, hosted CI или production/root fleet
operations. Любая новая public-contract replacement, другой table mapping,
внешний provider или live acceptance требует отдельного scope.
