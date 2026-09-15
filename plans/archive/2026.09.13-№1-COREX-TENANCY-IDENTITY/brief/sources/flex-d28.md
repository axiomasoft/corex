---
id: D28
date: 2026-07-17
status: accepted
item: ''
items: []
supersedes: []
superseded_by: null
---
## Решение

**P6.26 репойнтнут на план FLEXID** (репойнт из оркестра `2026.07.17-AXIOMA-ORCHESTRA` P2.2, наследует оркестр D3). Центральный IdP `id.flex.ru`/flexl.ru выделен в отдельный продукт **Flex** со своим мастер-планом `05-Projects/01-Products/Flex/plans/2026.07.17-FLEXID`. P6.26 конвертируется из inline-реализации OIDC AS/membership/switcher в **делегат-гейт** «→ GREEN плана FLEXID» (Intent/Scope/Validation/Required Reads переписаны на зеркалирование). Контракт B-34 §3.3–§3.5/§4.1–§4.3/Н1–Н4 НЕ меняется — переехал дом РЕАЛИЗАЦИИ, не решения. Альтернатива (оставить inline в FLEX-2 P6.26) отклонена: двойная бухгалтерия против отдельного плана FLEXID. RAG:— (repo-grounded: оркестр plan.md §5 D3 + phases/P2.md P2.2 Scope; COREX-CORE D7 — IdP вне corex)

## Почему

Единый аккаунт — точка входа экосистемы, продукт ШИРЕ IdP (оркестр D3); держать реализацию IdP в двух планах (FLEX-2 P6.26 + FLEXID) = дубль бухгалтерии и дрейф контракта
