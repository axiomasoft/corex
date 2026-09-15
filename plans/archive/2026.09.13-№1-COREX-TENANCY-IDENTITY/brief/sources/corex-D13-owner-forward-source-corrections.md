---
type: workflow
id: D13
date: 2026-09-12
status: accepted
item: P3.1
items: [P3.1]
supersedes: []
superseded_by: null
---

# D13 — Минимальные factual corrections нормативных источников

**Date:** 2026-09-12
**Status:** accepted
**Actor:** Dmitry Vostrikov · repository owner; recorded by Codex (model identifier unavailable in session)
**Evidence:** RAG:— user message «утверждаю» in response to the assistant's three numbered D6 recommendations and explicit DB_SCHEMA source-owner question; D6-normative-owner-proposal.md; queue request `NORM-FORWARD-SOURCES` action_version=1, action_digest=79ca00b2c0006d58b18dbd6fe2511ee523b5b726573454dc1733761852a9e280.
**Supersedes:** —
**Superseded By:** —

## Owner provenance

Owner response (verbatim): «утверждаю».
Response recorded at: 2026-09-12T19:49:24Z. The preceding assistant message proposed effective80-target85, l0-php83 and minimal-forward-corrections and asked whether the owner also acts as owner of the frozen FlexCRM DB_SCHEMA. Approval applies to that three-question block only. No D7/D8 answer is inferred.

## Решение

Утвердить минимальные factual forward corrections из D6: doctrine floor80 и точные ограничения historical/local CI proof; stale ULID TODO в ADR-004 заменить ссылкой на accepted UUIDv7; DB_SCHEMA catalogue20 / tenancy-created subset17 с раздельным creation/domain ownership; optional driver-local pubsub note. Ответ владельца относится ко всему предложенному блоку, включая документальное согласование замороженного DB_SCHEMA от владельца источника FlexCRM.

Queue decision: `NORM-FORWARD-SOURCES`; selected option: `minimal-forward-corrections`; outcome: `apply` for the declared future-spec-design-only scope.

## Почему

Выбран рекомендованный вариант D6; альтернатива defer-source-corrections отклонена. Исправляются зарегистрированные устаревшие факты без изменения DDL или переноса доменов в L0.

## Consequences

Отдельный docs-only executable spec с exact source paths/hashes, counts, bounded corrections и проверкой brain status/разрешением существующих docs conflicts. Изначальный user response не содержит отдельного заявления о владении DB_SCHEMA: согласование зафиксировано по контексту вопроса, который явно включал эту роль; не приписывать более широкие schema права. Schema/DDL/runtime/public Contracts не меняются. Rollback — только approved source edits и соответствующий docs sync.

P3 остаётся закрытой. Этот D# фиксирует owner branch choice и основание для отдельного detailed executable spec; текущая сессия не исполняет source amendments. D6 остаётся неизменным evidence/proposal record и не supersedes. Ответ очереди сохраняется без plan_requests.resolve(), который добавил бы in-progress событие исходному terminal P3.1. Queue schema, request/policy/action digests и owned views проверяются; terminal journal/state/status сохраняются побайтно.
