---
id: D62
date: 2026-09-29
status: accepted
item: P04
items: [P04]
supersedes: []
superseded_by: null
---
# D62 — P04: разрешение владельца на Sol при дизайне

**Actor:** owner, current Codex session; реализация записи designer/codex/gpt-6-sol/high.
**Evidence:** owner message «разрешаю исполнение моделью sol» после явного
`$ & task:plan-design 2026.09.27-№1-COREX-KERNEL P04`; текущий provider turn_context:
`gpt-6-sol`, effort `high`, cwd `corex`, rollout
`rollout-2026-09-29T16-21-47-01a0ed54-1b4b-7821-9974-44912db4e27d.jsonl`.
Host rollout — provenance, не переносимый bundle input. RAG:—.

## Solution

Разрешён дизайн P04 в текущем root на codex/gpt-6-sol/high, вместо планового
claude-opus-5-5/high и command default frontier/high. Это scoped authoring override,
не изменение будущих item routes, независимости design audit или scope.
Дизайн завершается на артефактах P04; finish и исполнение не запускаются автоматически.

CLI `design-context` отклонил нестандартную строку `$ & task:…` и последующее steering
сообщение (`ROUTE_LAUNCH_MISMATCH`). Для design-only использован тот же публичный
`task.plan_work.design_context(..., finish=True)` без execution route proof: компактная
схема и существующий контекст; source plan/backlog прочитаны отдельно. Actual model взят
из provider turn_context, а не выдан за успешно attested execution admission.
Дефект parser не меняет продуктовые контракты; Task package не правится.

## Why

Владелец явно разрешил Sol после выбора команды; provenance текущего дизайна должен соответствовать фактическому author, сохраняя scope и independent audit boundary.

## Consequences

Meta, Update Log и design journal actor фиксируют Sol/high. D56 default author остаётся
для других фаз; текущая рекомендация после P04 — finish. Установленная команда требует
отдельный GREEN design audit до execution (D24); прежний fingerprint P03 не применим.
