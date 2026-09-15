---
type: workflow
id: D14
date: 2026-09-12
status: accepted
item: P3.2
items: [P3.2]
supersedes: []
superseded_by: null
---

# D14 — Целостность шаблонов через schema_hash

**Date:** 2026-09-12
**Status:** accepted
**Actor:** Dmitry Vostrikov · repository owner; recorded by Codex (model identifier unavailable in session)
**Evidence:** RAG:— current owner answer; D7-architecture-owner-proposal.md; queue request `TEMPLATE-PARITY` action_version=1, action_digest=830c4664fec6b40a3fe32d5cc4a36fdd61d0bf798904036b55d9969982b07f75.
**Supersedes:** —
**Superseded By:** —

## Owner provenance

Owner response (verbatim): «да, если ты счтаешь это личшим решением».
Response recorded at: 2026-09-12T19:51:10Z. The preceding assistant message proposed hash-amendment-spec, unsupported-until-consumer, consumer-control-plane-owner and consumer-writer-owner and asked approval of this four-question D7 block. The owner's condition asks the assistant to confirm its recommendation; the assistant confirmed that this set is the most justified choice now, given the template integrity risk, consumer ownership and no named boxed-workspace consumer. Approval applies only to that D7 block; no D8 answer is inferred.

## Решение

Утвердить направление additive schema_hash amendment для root_templates и versioned canonical build/verifier contract. Отдельный spec должен перенести проверку expected manifest / recorded hash / actual catalogue перед всеми provisioning и activation writes, включая params save и pool claim. Ready-only остаточный риск не выбран.

Queue decision: `TEMPLATE-PARITY`; selected option: `hash-amendment-spec`; outcome: `apply` for the declared future-spec-design-only scope.

## Почему

Ready status не доказывает соответствие фактической схемы. Generic integrity проверка полезна любому Laravel-потребителю; D7 определяет deterministic inputs, freshness/sealing и zero-write negative acceptance.

## Consequences

Отдельный executable spec с точными owning files/source hashes, versioned serializer, template build/provisioning/pool/wave seams, locking и D7 negatives. Frozen DB_SCHEMA owner согласование конкретного schema amendment обязательно до migration; текущее решение выбирает направление и не утверждает конкретный DDL patch. Public Contracts delta требует отдельного major/owner gate. Rollback — stop affected provisioning, retain metadata/data, explicit approved mode; без автоматического DROP.

P3 остаётся закрытой. Решение фиксирует owner branch choice; дальнейшая реализация оформляется отдельными detailed executable specs. D7 остаётся неизменным evidence/proposal record. Queue answer записывается без plan_requests.resolve(), который добавил бы in-progress событие terminal P3.2. Проверяются queue schema, evidence/request/policy/action digests и owned views; terminal journal/state/status сохраняются побайтно.
