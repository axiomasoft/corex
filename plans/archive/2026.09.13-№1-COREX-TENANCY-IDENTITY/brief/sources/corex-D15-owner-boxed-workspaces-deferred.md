---
type: workflow
id: D15
date: 2026-09-12
status: accepted
item: P3.2
items: [P3.2]
supersedes: []
superseded_by: null
---

# D15 — Boxed workspaces отложены до конкретного потребителя

**Date:** 2026-09-12
**Status:** accepted
**Actor:** Dmitry Vostrikov · repository owner; recorded by Codex (model identifier unavailable in session)
**Evidence:** RAG:— current owner answer; D7-architecture-owner-proposal.md; queue request `BOXED-INIT` action_version=1, action_digest=676eac98b83e44a86794ff11050edddc1bd58db611fed4e363239951935e64ce.
**Supersedes:** —
**Superseded By:** —

## Owner provenance

Owner response (verbatim): «да, если ты счтаешь это личшим решением».
Response recorded at: 2026-09-12T19:51:10Z. The preceding assistant message proposed hash-amendment-spec, unsupported-until-consumer, consumer-control-plane-owner and consumer-writer-owner and asked approval of this four-question D7 block. The owner's condition asks the assistant to confirm its recommendation; the assistant confirmed that this set is the most justified choice now, given the template integrity risk, consumer ownership and no named boxed-workspace consumer. Approval applies only to that D7 block; no D8 answer is inferred.

## Решение

Объявить boxed workspaces unsupported до конкретного потребителя. Core-only boxed constant account / no-workspace профиль остаётся доступным. При подтверждённой потребности отдельно проектировать optional generic adapter с explicit account/connection configuration и request/job lifecycle init/clear; не добавлять stancl/tenancy зависимость в core-only PHP8.3.

Queue decision: `BOXED-INIT`; selected option: `unsupported-until-consumer`; outcome: `apply` for the declared future-spec-design-only scope.

## Почему

В текущем обсуждении не назван потребитель boxed workspaces. Выбрана явно предложенная ассистентом альтернатива unsupported-until-consumer вместо optional-generic-profile-spec рекомендации D7. Отдельная инициализация, membership enforcement и очистка контекста требуют подтверждённого сценария.

## Consequences

Это принятое deferred outcome, не реализация adapter. При необходимости отдельный bounded docs-only spec явно фиксирует supported/unsupported профиль. Runtime adapter spec появляется после названного потребителя; public/no-op semantic changes требуют отдельного gate. Нет migration/runtime delta или автоматического отката данных.

P3 остаётся закрытой. Решение фиксирует owner branch choice; дальнейшая реализация оформляется отдельными detailed executable specs. D7 остаётся неизменным evidence/proposal record. Queue answer записывается без plan_requests.resolve(), который добавил бы in-progress событие terminal P3.2. Проверяются queue schema, evidence/request/policy/action digests и owned views; terminal journal/state/status сохраняются побайтно.
