---
type: workflow
id: D17
date: 2026-09-12
status: accepted
item: P3.2
items: [P3.2]
supersedes: []
superseded_by: null
---

# D17 — Privileged grants принадлежат consumer writer

**Date:** 2026-09-12
**Status:** accepted
**Actor:** Dmitry Vostrikov · repository owner; recorded by Codex (model identifier unavailable in session)
**Evidence:** RAG:— current owner answer; D7-architecture-owner-proposal.md; queue request `ROLE-WRITE` action_version=1, action_digest=f0d0d58d721dbfeb38f0f49ff844e5bb303b6a4251d9d77739a463100ee3ff14.
**Supersedes:** —
**Superseded By:** —

## Owner provenance

Owner response (verbatim): «да, если ты счтаешь это личшим решением».
Response recorded at: 2026-09-12T19:51:10Z. The preceding assistant message proposed hash-amendment-spec, unsupported-until-consumer, consumer-control-plane-owner and consumer-writer-owner and asked approval of this four-question D7 block. The owner's condition asks the assistant to confirm its recommendation; the assistant confirmed that this set is the most justified choice now, given the template integrity risk, consumer ownership and no named boxed-workspace consumer. Approval applies only to that D7 block; no D8 answer is inferred.

## Решение

Назначить privileged role assignment writes приложению-потребителю. Consumer writer использует consumer-owned privilege policy и trusted actor/account/workspace; ImpersonationGuard.assertAllowed(GrantPrivilegedRole) обязателен перед любым write/event. Consumer запрещает bypass через direct/raw/bulk paths и охватывает HTTP/jobs/CLI; generic L0 writer/public-major направление не выбрано.

Queue decision: `ROLE-WRITE`; selected option: `consumer-writer-owner`; outcome: `apply` for the declared future-spec-design-only scope.

## Почему

Политика привилегированных ролей принадлежит приложению. Наличие read predicates и guard assertion не доказывает production write enforcement; D7 задаёт конкретные entrypoint/bypass и zero-write negatives.

## Consequences

Отдельный consumer executable spec с named app/writer/entrypoint inventory, caller grant authority/scope, initialized impersonation context, atomic assignment/audit и D7 negative acceptance. No universal roles or new public API. Rollback — disable grant entrypoints, revert approved writer, preserve assignments/audit; без автоматического destructive cleanup.

P3 остаётся закрытой. Решение фиксирует owner branch choice; дальнейшая реализация оформляется отдельными detailed executable specs. D7 остаётся неизменным evidence/proposal record. Queue answer записывается без plan_requests.resolve(), который добавил бы in-progress событие terminal P3.2. Проверяются queue schema, evidence/request/policy/action digests и owned views; terminal journal/state/status сохраняются побайтно.
