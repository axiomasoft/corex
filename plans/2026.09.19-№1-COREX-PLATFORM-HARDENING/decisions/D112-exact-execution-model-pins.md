---
id: D112
date: 2026-09-22
status: accepted
item: P5
items: [P5, P5.6, P5.13, P5.14, P5.15, P5.16, P5.17, P5.18, P6, P7, P8, P9, P10, P11, P12, P13, P14, P15, P16, P17]
supersedes: []
superseded_by: null
---
# D112 — Exact execution model pins

**Actor:** owner (Dmitry Vostrikov), 2026-09-22; recorded by plan-design.
**Evidence:** owner request that future executors must not have to choose who or which model;
D111; `findings/execution-audit-P1-P5-2026-09-22.md`.

## Решение

The native model and effort for every remaining execution scope are fixed in `plan.md` §3.3.
Executors do not choose or downgrade them:

- Composer 2.5 is limited to P5.13 and P5.17;
- Grok 4.7 owns P5.14–P5.15, P6–P8 implementation and P15–P16 implementation;
- GPT-5.6 Sol owns P5.6/P5.16/P5.18, all P9–P14, phase-closing qualification items,
  and all P17;
- every named phase-closing qualification runs in a fresh session that did not implement the
  material preceding product items.

The exact native selectors are `composer-2.5`, `cursor-grok-4.7-high`, and `gpt-5.6-sol`.
If a selector is unavailable, execution fails before mutation and returns to the owner; it is not
silently replaced. Existing owner gates remain mandatory.

## Почему

D111 established risk classes but left parts of P15–P16 optional and the old D37 class-to-model
table still implied Composer for all implementation items. Exact pins remove provider discretion,
keep Composer on the two bounded repairs where it is appropriate, and reserve independent Sol
sessions for joined acceptance.

## Consequences

- Routing class/effort remains the lifecycle carrier; §3.3 is the authoritative native-model pin.
- A different model requires a new owner decision, not executor judgment.
- Model unavailability is a waiting result; it does not authorize fallback or a lower effort.
