---
id: D88
date: 2026-09-20
status: accepted
item: P15.10
items: [P15, P15.10]
supersedes: []
superseded_by: null
---
# D88 — Core zone FQCN принимают semantic names

**Actor:** plan-designer/codex-gpt-5.6-sol/high
**Evidence:** RAG:— D32, `research/P6-design.md` §4, текущие
PubSub/Audit/Views/Data surfaces и потребители.

## Решение

P15.10 переносит `CoreX\PubSub\*`→`CoreX\Messaging\*`, audit contracts
в `CoreX\Audit\Contracts\*`, `AuditLog`→`CoreX\Audit\Internal\AuditLog`,
`CoreX\Views\*`→`CoreX\SavedViews\*` и `DataScopeAccessDecision`→
`CoreX\Support\Data\DataScopeAccessDecision`. Namespace-coherent `git mv` здесь
является часть rename, а не rollout structure profiles P16.

## Почему

`Views` фактически владеет saved-view contract, а общий `Messaging`
совпадает с зоной P5/P6. Точечные PSR-4 карты оставили бы
неймспейс и файловую семантику расходящимися.

## Consequences

Item требует `[OWNER-GATE:G-API-P15]`, full review, обновление
`extra.corex.public-api` и всех in-repo consumers; deprecated aliases нет.
