---
id: D18
date: 2026-09-15
status: accepted
item: design
items: [design]
supersedes: []
superseded_by: null
---
# D18 — Группы выполнения с сохранением контекста

## Решение

По прямому запросу владельца после whole-plan review пересмотреть grouping всех78
items, уменьшить число повторных cold starts, сохранив разумные границы, затем commit
и push всей подготовленной работы этого плана в текущую ветку. Это новое явное
разрешение на Git commit/push; прежний design-only запрет публикации product packages,
записи foreign plans и выполнения P1–P11 остаётся. Shared Task runtime не меняется.

Точные35 рекомендуемых envelopes определены в Routing root/phase и
research/08-execution-batches.md. Все78 item IDs, индивидуальные model/effort/Exec/Review,
Files/Inputs/Validation/Deliverables, R52/365parents/A01–A09 и grants сохранены.
24multi-item groups и11solo, каждый phase-local/adjacent/один Exec. Запуск использует
max model/effort/review группы; готовность каждого item подтверждается в момент входа.
Sequential producer chains запускаются с первого ready item и повторно допускают
следующий в том же root после actual closure; future bundle/receipt не фабрикуются.

Первый successor: B-P1-QUALIFY, frontier/high = gpt-5.6-sol/high, initial carrier P1.1,
explicit group scope P1.1–P1.4, далее continue-root до scope completion/context boundary.
D18 уточняет только прежнее solo launch recommendation D17 и batch rows соответствующих
phase decisions, не отменяет их архитектуру/acceptance/gates целиком; поэтому broad
supersedes D1–D17 не задаётся. Runtime attestation/continuity получают штатными инструментами
при реальном запуске, текущая запись не подменяет provider evidence.

## Почему

Существовавшие57 launch units переиспользовали context не полностью:42solo включали
intake/receipt/seal с теми же входами. По frozen known read sets D18 уменьшает cold-read
bytes с32 173 559 до20 657 887 (35,8%). Это не измерение billable tokens и не гарантия35
сессий: контекст, interleave P4/P5, calendar/authority waits могут менять число заходов.

Новые сессии не предписываются per-item commits, обычной проверкой или созданием cold
installed fixture. Phase закрывается отдельно; переход между фазами не объединяется
в batch и не означает автоматически новый root. Scope/actual continuity обязательны.
P9 manual runner/private/release, P11 foreign write и P10 monthly window сохраняют
точные boundaries. P11 packet full review отделён от light qualification; максимально
крупная группа не цель. Alternatives, каждое solo justification и ready order в guide.

## Проверка

Сохранён before snapshot; native compile/admit и negative cases проверяют реальные
границы. Ни одна phase/item не исполняется. Views/P1.1 bundle, named lint/downstream,
13existing guard tests и design-finish обновляются после финального stable delta.
Current checks — artifacts/astra-whole-plan-review/checks/batch-* и final-check-results.json.
