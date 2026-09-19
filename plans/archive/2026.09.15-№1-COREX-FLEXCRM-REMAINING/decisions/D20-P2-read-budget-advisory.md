---
id: D20
date: 2026-09-15
status: accepted
item: design
items: [design]
supersedes: []
superseded_by: null
---
# D20 — Scoped owner decision: B-P2-PROPOSALS budget и execution

## Решение и provenance

Прямое поручение владельца в текущем root (2026-09-15): «Выполни всю группу B-P2-PROPOSALS (P2.1–P2.4) по plans/2026.09.15-№1-COREX-FLEXCRM-REMAINING/handoff.md»; «Разрешаю считать суммарный estimated-token budget рекомендацией для B-P2-PROPOSALS. Качество важнее экономии токенов»; «Перед исполнением зафиксируй это scoped owner decision и обнови plan-owned policy, сохранив все обязательные источники, проверки и ограничения отдельных чтений. Общий runtime не меняй».

Разрешены последовательные P2.1 → P2.2 → P2.3 → P2.4 в одном authoritative root без дочерних агентов, per-item closure/bundle/admission и eligible inline P2 close/следующий handoff. Промежуточные подтверждения не нужны. За P2.4 scope не выходит. Существующие рабочие изменения сохраняются; при context pressure durable checkpoint.

## Scoped policy

Только B-P2-PROPOSALS/P2.1–P2.4 данного плана: native суммарный estimated-token threshold100000 становится advisory WARNING с фактическим измерением. Plan-owned research/09-read-budget-policy.json version1.1.0 добавляет отдельную digest-bound scope запись D20; исходная D19/B-P1 policy сохраняется. P3–P11 не получают advisory. Shared Task runtime, calibration/threshold и runtime admission не меняются.

Plan-owned scripts/task-check.py через публичные native lint/measurement APIs меняет severity только точной D2(a) aggregate diagnostic для ready пункта утверждённой группы с совпавшими path/item/weight/threshold и bound accepted decision. Все прочие ошибки и D2(b) per-read limits остаются errors: missing/нечитаемые inputs, selector/digest/producer/readiness/структура/route/design/Validation/semantic checks обязательны. Все источники читаются в объявленном порядке bounded chunks с transport полнотой; reuse допускается только для реально прочитанных неизменённых bytes, не hashing/summary вместо чтения. Измерения/исходные native diagnostics и negative policy checks сохраняются.

D20 не принимает решения восьми source owners, не выдаёт G-API/G-MAJOR/G-SCHEMA/G-DEPENDENCY/G-PROFILE/G-SOURCES и release/private/hosted/live/consumer authority. Exact proposals и pending resume сохраняются; local catalogue GREEN не deployed acceptance. A01–A09/52R/365parent criteria/все clauses/thresholds/G/DI/AP не сокращаются.

## Проверка и последствия

До первого P2 START: owner policy binding и negative severity checks; affected design delta/finish reseal; design/lint и P2.1 native routing/readiness admission. Перед каждым следующим item свежие required inputs/design/bundle/route/contract checks. После GREEN eligible phase close и post-close successor recommendation, без P3 execution. D19 не superseded и её historical evidence не переписываются.
