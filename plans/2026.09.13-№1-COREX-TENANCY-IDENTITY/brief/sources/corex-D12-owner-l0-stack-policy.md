---
type: workflow
id: D12
date: 2026-09-12
status: accepted
item: P3.1
items: [P3.1]
supersedes: []
superseded_by: null
---

# D12 — Стек L0 и матрица по пакетам

**Date:** 2026-09-12
**Status:** accepted
**Actor:** Dmitry Vostrikov · repository owner; recorded by Codex (model identifier unavailable in session)
**Evidence:** RAG:— user message «утверждаю» in response to the assistant's three numbered D6 recommendations and explicit DB_SCHEMA source-owner question; D6-normative-owner-proposal.md; queue request `NORM-L0-STACK` action_version=1, action_digest=fc2b110e6c003606d59a8983fff5bf64f79ba803708b0aee46c5d74361141436.
**Supersedes:** —
**Superseded By:** —

## Owner provenance

Owner response (verbatim): «утверждаю».
Response recorded at: 2026-09-12T19:49:24Z. The preceding assistant message proposed effective80-target85, l0-php83 and minimal-forward-corrections and asked whether the owner also acts as owner of the frozen FlexCRM DB_SCHEMA. Approval applies to that three-question block only. No D7/D8 answer is inferred.

## Решение

Утвердить PHP8.3–8.5 / Laravel12–13 для core/modules/tasks; tenancy scoped PHP8.4–8.5 / Laravel12–13; auth Laravel12, без обещания Laravel13. Product target matrix отделяется от L0. Declared constraints и фактически проверенные версии/SHA/proof class показываются раздельно.

Queue decision: `NORM-L0-STACK`; selected option: `l0-php83`; outcome: `apply` for the declared future-spec-design-only scope.

## Почему

Выбран рекомендованный вариант D6; альтернатива adr84-separate-change-spec отклонена. Нормативное описание согласуется с текущими package boundaries и сохраняет core-only PHP8.3.

## Consequences

Отдельный docs-only executable spec для ADR-001 и связанных нормативных таблиц; сравнение manifest/CI/docs, local proof отдельно от runner proof. Constraints и CI jobs не менять этим решением. Rollback — docs-only revert.

P3 остаётся закрытой. Этот D# фиксирует owner branch choice и основание для отдельного detailed executable spec; текущая сессия не исполняет source amendments. D6 остаётся неизменным evidence/proposal record и не supersedes. Ответ очереди сохраняется без plan_requests.resolve(), который добавил бы in-progress событие исходному terminal P3.1. Queue schema, request/policy/action digests и owned views проверяются; terminal journal/state/status сохраняются побайтно.
