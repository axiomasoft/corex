---
type: workflow
id: D18
date: 2026-09-12
status: accepted
item: P3.3
items: [P3.3]
supersedes: []
superseded_by: null
---

# D18 — Публичная split-топология CoreX

**Date:** 2026-09-12
**Status:** accepted
**Actor:** Dmitry Vostrikov · repository owner; recorded by Codex (model identifier unavailable in session)
**Evidence:** RAG:— current owner answer; D8-distribution-owner-proposal.md; queue request `RELEASE-COREX` action_version=1, action_digest=9fd2fcdafe188d1e9f41583dcc28979e194648196bbd275847557747bd75990c.
**Supersedes:** —
**Superseded By:** —

## Owner provenance

Owner response (verbatim): «да».
Response recorded at: 2026-09-12T19:54:45Z. The preceding assistant message proposed public-candidate-split-spec, private-immutable-source-spec, owned-fixed-metadata-source-spec and minimum-factual-proof-spec and asked approval of these four directions for separate executable specs. It explicitly retained separate exact-result external publication/resource authority and unknown AzGuard immutable ref. Approval applies to this D8 block only and does not fabricate missing inputs or authorize external writes.

## Решение

Утвердить target org axiomasoft и proposed public mirrors corex-core, corex-modules, corex-tasks, corex-tenancy; initial allowlist ровно core/modules/tasks/tenancy. Auth не входит в default public graph; crm исключён как consumer layer. Порядок: deterministic local dry-run export → pristine consumer install/tests → отдельно authorized publication exact refs.

Queue decision: `RELEASE-COREX`; selected option: `public-candidate-split-spec`; outcome: `apply` for the declared future-spec-design-only scope.

## Почему

Public per-package mirrors соответствуют модели распространения агностичного MIT CoreX. Private-registry-only альтернатива не выбрана; split topology choice не доказывает publication readiness.

## Consequences

Отдельный executable spec для bounded local export/staged artifacts/pristine fixtures с source SHA→artifact/tree/ref mapping, tool digest, exclusions/licensing, no path/symlink/private credential leakage, cold update/install, package tests и D8 negatives. External create/push/tag/release/Packagist/secrets только после конкретного подготовленного результата и отдельной scoped authority. Rollback — discard staging/disable job; published correction новой approved release, без force-push/delete tags.

P3 остаётся закрытой. Решение фиксирует owner branch choice; дальнейшая реализация оформляется отдельными detailed executable specs. D8 остаётся неизменным evidence/proposal record. Queue answer записывается без plan_requests.resolve(), который добавил бы in-progress событие terminal P3.3. Проверяются queue schema, evidence/request/policy/action digests и owned views; terminal journal/state/status сохраняются побайтно.
