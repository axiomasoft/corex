---
id: D31
date: 2026-09-16
status: accepted
item: P3.3
items: [P3.3, P3.4, P3.5, P3.6, P3.7]
supersedes: []
superseded_by: null
---
# D31 — Scoped owner decision: B-P3-IDENTITY read budget

**Actor:** owner / gpt-5.6-terra/high
**Evidence:** RAG:— owner message «разрешаю делать, что необходимо» in direct response to the exact B-P3-IDENTITY scoped-budget request; `artifacts/B-P3-IDENTITY/preflight-2026-09-15.log` (SHA256 `91992db47ce82781410fbfeb6f68871badfe5c43e7913fdc6aec2f07fc82f406`)

## Решение

Только для batch B-P3-IDENTITY данного плана и только для P3.3–P3.7 агрегатная
диагностика read-budget-v1 D2(a) переводится в advisory WARNING, когда native
measurement совпадает с текущими item, batch и threshold `100000`. Для первого ready
item P3.3 подтверждён current weight `583995` estimated tokens. Каждый source остаётся
обязательным: его selector, порядок, digest-qualified consumption и индивидуальные
D2(b) limits не изменяются.

## Почему

Владелец прямо принял подготовленный точный запрос после текущего native preflight.
Группа выполняется последовательно в одном root; качество и полнота intake важнее
aggregate estimate. Это продолжает подход D19/D20/D23, но не расширяет ни один из
их scopes.

## Consequences

`research/09-read-budget-policy.json` получает digest-bound B-P3-IDENTITY scope, а
`scripts/task-check.py` по-прежнему переводит только exact matching ready D2(a)
diagnostic в warning. Нет waiver для D2(b), missing/unreadable inputs, selectors,
producer readiness, Validation, Review, protected G-STORAGE/G-DEPENDENCY/G-API/
schema/private/live/release/consumer gates. Shared Task runtime, calibration и
threshold не меняются; P3 не закрывается и scope не выходит за P3.7.
