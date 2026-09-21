---
id: D48
date: 2026-09-19
status: accepted
item: P9
items: [P9, P9.1, P9.2, P9.8]
supersedes: []
superseded_by: null
---

# D48 — Multi-account relay требует измеренного триггера

**Actor:** plan-designer/codex-gpt-5.6-sol/high

**Evidence:** `RAG:—` D27 корректно размещает outbox в tenant DB и называет
риск голодания, но не содержит наблюдаемого SLO, объёма, operator workflow
или контракта lease/progress. `RAG:—` фаза P9 объявлена как
обратимая структурная работа без DDL и смены поведения.

## Решение

1. D27.1–D27.3 о месте outbox и single-account relay не меняются.
2. P9 назначает multi-account relay owner'ом `Tenancy/Runtime`, но не строит
   scheduler, lease/progress state, DDL и concurrency policy.
3. Отдельная product-работа открывается при любом из триггеров:
   наблюдаемое голодание; нарушение delivery SLO; ручной fan-out выше
   утверждённого operator budget.
4. На старте той работы нужны: metrics baseline, fairness/SLO contract,
   owner-gate на центральный state/DDL и failure-mode design.

## Почему

Выбор round-robin, age priority, backoff и global concurrency без объёмов и SLO был бы
спекулятивным. Помещать это в фазу переносов повысило бы риск
молчаливой потери доставки и сломало contract-first границу P9.

## Consequences

Риск D27 остаётся открытым и должен быть в receipt P9 и roadmap. Это
решение уточняет адресацию D27.4, не отменяя его атомарность и
не выдавая непостроенный control plane за закрытый долг.

