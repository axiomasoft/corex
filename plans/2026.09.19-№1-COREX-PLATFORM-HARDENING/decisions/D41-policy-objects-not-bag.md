---
id: D41
date: 2026-09-19
status: accepted
item: P7.2
items: [P7, P7.2, P7.3]
supersedes: []
superseded_by: null
---
# D41 — Продуктовые правила — policy-объекты, не поля generic-Options

**Actor:** plan-designer/cursor-grok-4.6/high
**Evidence:** RAG:✅ P7-Q4 Fowler encapsulation, Microsoft Options vs
policy, Laravel middleware constructor injection. A75: impersonation
читается несколькими middleware по ключам. RAG:— `DenyImpersonatedWrites`,
`AccountStatusGate`, `DatabaseImpersonationService`.

## Решение

1. В `corex/tenancy` кроме `TenancyOptions` вводятся три policy:
   `ImpersonationPolicy` (`enabled`, `requireApprovedBy`),
   `AccountLifecyclePolicy` (`trialDays`, `graceDays`, `platformDomain`),
   `StatusGatePolicy` (`paymentRoute`, `exportRoute`, `readOnlyMethods`).
2. Middleware и lifecycle получают policy (или `TenancyOptions`, из
   которого policy достаётся), а не четыре вызова `config()`.
3. `AuthOptions.panel` остаётся скаляром: одно значение, один смысл,
   нет cross-field инварианта — отдельный `PanelPolicy` не вводится.

## Почему

Generic-bag отвечает «какие значения»; policy отвечает «какое правило».
Четыре ключа impersonation имеют смысл только вместе и читаются в
нескольких местах — это A75. Один `panel` в auth не тянет класс.
Альтернатива «все ключи tenancy в одном плоском DTO» отклонена: она
оставляет правило в middleware.

## Consequences

P7.2 создаёт три класса рядом с `TenancyOptions`. P7.3 переводит
HTTP/impersonation-потребителей на policy. Провайдеры в P8 дробятся уже
имея, что регистрировать.
