---
type: workflow
tags: [corex, plan-v2, owner-gate]
created: 2026-09-12
id: D9
date: 2026-09-12
status: superseded
item: ""
items: []
supersedes: []
superseded_by: D10
---

# D9 — Owner gate: разрешённые carriers против Task 0.15.4

**Date:** 2026-09-12
**Status:** superseded
**Actor:** plan-designer/frontier/high · gpt-5.6-sol
**Evidence:** RAG:— actual installed Task 0.15.4 lint; matching cache/marketplace parsing module SHA256 2170e3ec25a4482f8727b9e9225864dca12ebe92341f87ce4505f4fcc11a2f22.
**Supersedes:** —
**Superseded By:** D10

## Решение

Нужен owner ответ: разрешить добавление ровно трёх уже зарегистрированных Layout v2 carriers — `brief/00-brief.md`, `roadmap.md` и generated `open-questions.md` — в новый successor.
Это proposal, НЕ accepted owner exception. До ответа дизайн не qualified, execution запрещено.
Alternative: owner Task предоставляет current compatible lint/canon, допускающий исходный закрытый список; инструмент этой design сессией не патчится и гейт не отключается.

## Почему

Owner brief №6 задаёт ONLY plan.md/status/decisions/phases-items/journal/bundles/handoff.
Actual lint требует:
- `_lint_plan_brief_20`: датовый plan после 2026.07.16 без brief/ ≥1 nonempty markdown → ERROR.
- `_lint_plan_roadmap_20`: fully detailed phases без roadmap.md → ERROR.
- `plan_decisions.build_views(check=True)` требует root `open-questions.md` как owned decision-open-questions projection; отсутствие → generated view stale ERROR в bundle gate.
Все три carriers действительно зарегистрированы для v2, но не в разрешённом владельцем закрытом списке. Генератор временно создал root open-questions projection; только этот новый generated файл удалён после обнаружения конфликта. Разрешённые decisions/_*.md остаются exact output того же materializer.
Их создание без clarification было бы scope expansion. Skeleton insertion, backdated ID, fake/empty carriers, skipped lint или tool edits не являются разрешённой remediation.
Все другие структурные дефекты исправлены inline; не создано нового audit/remediation item.
Стартовые P1.1/P1.2 проходят read-budget; downstream P2/P3 pending-producer, не missing-without-producer.

## Reproduction

В CoreX cwd с runtime marketplace swissknifeman/packages/task:
`python3 <Task>/scripts/plan-lint.py plans/2026.09.12-№1-COREX-SUCCESSOR`.
Observed plan-lint verdict: 2 ERROR (brief, roadmap) + 1 advisory WARN (optional index). Отдельный `plan-views.py decisions --check` выдаёт ERROR отсутствующего open-questions.md; lint останавливается на ранних структурных gates до полного generated-views gate. Всего три unresolved carrier readiness conditions, не «три ошибки» в фактическом stdout lint.
Optional index не добавляется: user carrier list не разрешает его и он не gating.
Required carriers/state/bundles исправлены по действительному closed schema, не по устаревшим snippet forms.

## Consequences

Продуктовый item не начинался и не объявлен blocked/Done.
Созданный plan сохраняется как unfinished qualified-design candidate; ACTIVE указывает на design candidate, не dispatch authorization.
Ровно один semantic Next — waiting-owner → design finish после явного разрешения трёх carriers.
No execution successor exists until current lint is green and owner separately launches it.
Current route capture succeeded: provider-thread-state, gpt-5.6-sol/high, exact current plan-design invocation.
