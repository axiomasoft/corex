---
type: workflow
id: D11
date: 2026-09-12
status: accepted
item: P3.1
items: [P3.1]
supersedes: []
superseded_by: null
---

# D11 — Обязательный coverage минимум 80%, цель 85%

**Date:** 2026-09-12
**Status:** accepted
**Actor:** Dmitry Vostrikov · repository owner; recorded by Codex (model identifier unavailable in session)
**Evidence:** RAG:— user message «утверждаю» in response to the assistant's three numbered D6 recommendations and explicit DB_SCHEMA source-owner question; D6-normative-owner-proposal.md; queue request `NORM-COVERAGE` action_version=1, action_digest=0d8a1c8630b947304957e818ef2dd61d332c79ab404f4132a10be2df3ce9b22d.
**Supersedes:** —
**Superseded By:** —

## Owner provenance

Owner response (verbatim): «утверждаю».
Response recorded at: 2026-09-12T19:49:24Z. The preceding assistant message proposed effective80-target85, l0-php83 and minimal-forward-corrections and asked whether the owner also acts as owner of the frozen FlexCRM DB_SCHEMA. Approval applies to that three-question block only. No D7/D8 answer is inferred.

## Решение

Утвердить effective enforced floor 80% и отдельный policy target 85%. Governance должен явно обосновать approved exception/baseline 80%; Architecture, Process и doctrine используют одинаковые определения metric/floor/target. Исполнимые coverage floor и команды сохраняют 80%.

Queue decision: `NORM-COVERAGE`; selected option: `effective80-target85`; outcome: `apply` for the declared future-spec-design-only scope.

## Почему

Выбран рекомендованный вариант D6; альтернатива policy-target80 отклонена. Сохраняются достижимый обязательный минимум и отдельная цель развития.

## Consequences

Отдельный docs-only executable spec: точные исходные файлы/хэши, минимальный diff, единые определения и проверка нулевой executable delta. Поднятие floor до85 требует отдельного measurement и решения. Rollback — только утверждённый docs patch; floor80 сохраняется.

P3 остаётся закрытой. Этот D# фиксирует owner branch choice и основание для отдельного detailed executable spec; текущая сессия не исполняет source amendments. D6 остаётся неизменным evidence/proposal record и не supersedes. Ответ очереди сохраняется без plan_requests.resolve(), который добавил бы in-progress событие исходному terminal P3.1. Queue schema, request/policy/action digests и owned views проверяются; terminal journal/state/status сохраняются побайтно.
