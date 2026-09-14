---
type: workflow
id: D16
date: 2026-09-12
status: accepted
item: P3.2
items: [P3.2]
supersedes: []
superseded_by: null
---

# D16 — DNS verification и TLS принадлежат потребителю

**Date:** 2026-09-12
**Status:** accepted
**Actor:** Dmitry Vostrikov · repository owner; recorded by Codex (model identifier unavailable in session)
**Evidence:** RAG:— current owner answer; D7-architecture-owner-proposal.md; queue request `DOMAIN-OWNER` action_version=1, action_digest=f1155a7ffb8caadcc43c82b149d1dc11c5e2a3b49337fae3c7fab357d4882594.
**Supersedes:** —
**Superseded By:** —

## Owner provenance

Owner response (verbatim): «да, если ты счтаешь это личшим решением».
Response recorded at: 2026-09-12T19:51:10Z. The preceding assistant message proposed hash-amendment-spec, unsupported-until-consumer, consumer-control-plane-owner and consumer-writer-owner and asked approval of this four-question D7 block. The owner's condition asks the assistant to confirm its recommendation; the assistant confirmed that this set is the most justified choice now, given the template integrity risk, consumer ownership and no named boxed-workspace consumer. Approval applies only to that D7 block; no D8 answer is inferred.

## Решение

Назначить CNAME+TXT ownership verification, trusted verified-state writes, ask/TLS policy и edge control-plane приложению-потребителю и его оператору. Tenancy сохраняет generic verified-state reader. Consumer отвечает за revocation/reassignment и resolver/ask cache invalidation; reusable optional verifier/public-major направление не выбрано.

Queue decision: `DOMAIN-OWNER`; selected option: `consumer-control-plane-owner`; outcome: `apply` for the declared future-spec-design-only scope.

## Почему

Reader verified_at не является DNS proof. Проверка владения и TLS операции зависят от consumer hostnames/policy/edge; их владение соответствует ответственности потребителя и агностичности L0.

## Consequences

Отдельный consumer executable spec обязан назвать concrete project/operator/entrypoints, одноразовый account+host challenge, CNAME+TXT proof, trusted writer/cache invalidation и D7 takeover/replay/revocation negatives. Schema/public Contracts/external DNS/TLS права отдельно scoped; решение их не выдаёт. Rollback — disable custom verification/issuance, только authorized state/cache edits; оператор отдельно обрабатывает edge.

P3 остаётся закрытой. Решение фиксирует owner branch choice; дальнейшая реализация оформляется отдельными detailed executable specs. D7 остаётся неизменным evidence/proposal record. Queue answer записывается без plan_requests.resolve(), который добавил бы in-progress событие terminal P3.2. Проверяются queue schema, evidence/request/policy/action digests и owned views; terminal journal/state/status сохраняются побайтно.
