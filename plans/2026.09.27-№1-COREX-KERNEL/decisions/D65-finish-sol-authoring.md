---
id: D65
date: 2026-09-29
status: accepted
item: P04
items: [P04]
supersedes: []
superseded_by: null
---
# D65 — Finish P00–P04: разрешение владельца на Sol/high

**Actor:** owner, current Codex session; запись designer/codex/gpt-6-sol/high.
**Evidence:** явный вызов `$ task:plan-design 2026.09.27-№1-COREX-KERNEL finish`
и ответ владельца «Разрешаю finish на Sol/high» на вопрос о расширении D62.
Provider turn_context текущей сессии `01a0ed6e-3c14-7533-9458-d468c893e910`:
`gpt-6-sol`, effort `high`, cwd `corex`. RAG:—.

## Решение

Разрешён только текущий design finish P00–P04 на codex/gpt-6-sol/high вместо
command default frontier/high. D62 остаётся разрешением на предшествующий дизайн
P04; эта запись отдельно покрывает finish. CLI `design-context --finish` вернул
`ROUTE_UNDERSHOOT`; компактный контекст получен через публичный design-only facade
`task.plan_work.design_context(..., finish=True)`. Это не execution route proof.

Finish подтверждает 13 execution-sheet admissions: P00–P03 сохраняют принятую
историю; P04.1 → P04.2 → P04.3 → P04.4 — четыре solo. Первые три требуют
frontier/high, последняя implementation/high; все reviews full. Нового объединения
не требуется: scanner, DTO/transaction replay, error boot/replay и strict rollout
имеют разные acceptance/review границы. P05–P09 остаются backlog по D24.

## Почему

Владелец явно разрешил authoring route после воспроизведения ограничения Task.
Дизайн и runtime execution имеют разные границы; provenance должен показывать
фактическую модель, без подмены маршрута будущих исполнителей или аудита.

## Consequences

Meta, Update Log и design journal actor фиксируют Sol/high. После finish необходим
независимый `task:plan-audit ... design` на frontier/xhigh, модель != Sol, свежий
контекст и GREEN для нового fingerprint. Аудит и исполнение не входят в этот вызов;
маршруты execution-sheet и прежние GREEN closure evidence не меняются.
