---
id: D23
date: 2026-09-15
status: accepted
item: design
items: [design]
supersedes: []
superseded_by: null
---
# D23 — Scoped owner decision: B-P3-LOCAL budget и execution

## Решение и provenance

Прямой запрос владельца в текущем root от 2026-09-15: «Выполни всю группу B-P3-LOCAL (P3.1–P3.2) по plans/2026.09.15-№1-COREX-FLEXCRM-REMAINING/handoff.md»; «Разрешаю считать суммарный estimated-token budget рекомендацией для B-P3-LOCAL. Качество важнее экономии токенов»; «Перед исполнением зафиксируй это scoped owner decision и обнови plan-owned policy, сохранив все обязательные источники, проверки и ограничения отдельных чтений. Общий runtime не меняй».

Разрешены последовательные P3.1 → P3.2 в одном authoritative root без дочерних агентов. После каждого GREEN закрыть item, обновить bundle/admission следующего готового пункта и продолжать без промежуточного подтверждения. Существующие рабочие изменения сохранять; при context pressure записывать checkpoint. После P3.2 подготовить handoff B-P3-IDENTITY/P3.3–P3.7; фазу P3 не закрывать, за P3.2 не выходить.

## Scoped policy

Только B-P3-LOCAL/P3.1–P3.2 данного плана: native суммарный estimated-token threshold100000 — advisory WARNING с фактическим измерением. research/09-read-budget-policy.json version1.2.0 добавляет отдельную digest-bound scope запись D23; D19/B-P1 и D20/B-P2 сохраняются. B-P3-IDENTITY/P3.3–P3.7 и P4–P11 остаются вне advisory. Shared Task runtime, threshold/calibration и алгоритмы admission не меняются.

scripts/task-check.py использует native lint/measurement APIs и меняет severity только точной D2(a) aggregate diagnostic для ready пункта утверждённой группы с совпавшими path/item/weight/threshold и accepted digest-bound decision. Missing/unreadable inputs, selectors/digests/producer readiness, структура/route/design/Validation/semantic checks и все D2(b) per-read limits остаются обязательными errors. Источники читаются полностью в объявленном порядке bounded chunks с проверкой полноты transport; reuse только действительно прочитанных неизменённых bytes. Hashing/summary не заменяет чтение. Native diagnostics/measurements и negative policy checks сохраняются.

## Authority и ограничения

Владелец отдельно потребовал: «Перед изменениями, требующими G-STORAGE/G-DEPENDENCY, проверь matching owner receipts. Предложения из P2 разрешениями не считать. Если receipts отсутствуют, подготовь конкретные запросы и запроси только недостающее решение владельца».

D23 не является G-STORAGE/G-DEPENDENCY receipt и не принимает protected adoption. Все G gates, source/library/provider qualification, API/major/schema/private/live/release/consumer authority и 52R/365parents/682operative+6delimiters/9foundations/34DI/13AP сохраняются. Недостающий receipt останавливает только затронутые writes; local/fake/native/installed/released/hosted/deployed evidence различаются. GREEN packet или policy не заменяет conformance.

## Проверка и последствия

Перед первым product START: binding/negative policy checks, affected integrated delta/finish reseal, design/lint и свежие bundle/routing/contract checks. До protected writes — принятие exact concrete request matching owner receipt. Не менять исторические результаты D19/D20 или journal prefix. Прямой запрет дочерних агентов сохраняется; никакого нового audit consent этим решением не выдано.
