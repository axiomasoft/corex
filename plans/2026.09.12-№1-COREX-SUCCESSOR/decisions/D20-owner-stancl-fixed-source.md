---
type: workflow
id: D20
date: 2026-09-12
status: accepted
item: P3.3
items: [P3.3]
supersedes: []
superseded_by: null
---

# D20 — Воспроизводимый stancl source с фиксированными metadata

**Date:** 2026-09-12
**Status:** accepted
**Actor:** Dmitry Vostrikov · repository owner; recorded by Codex (model identifier unavailable in session)
**Evidence:** RAG:— current owner answer; D8-distribution-owner-proposal.md; queue request `STANCL-SOURCE` action_version=1, action_digest=bc575435c50c64afba6f3730ee1e5739adb82342ca88cb55588e8b93877b0fc2.
**Supersedes:** —
**Superseded By:** —

## Owner provenance

Owner response (verbatim): «да».
Response recorded at: 2026-09-12T19:54:45Z. The preceding assistant message proposed public-candidate-split-spec, private-immutable-source-spec, owned-fixed-metadata-source-spec and minimum-factual-proof-spec and asked approval of these four directions for separate executable specs. It explicitly retained separate exact-result external publication/resource authority and unknown AzGuard immutable ref. Approval applies to this D8 block only and does not fabricate missing inputs or authorize external writes.

## Решение

Выбрать owner-maintained fixed-metadata mirror/tag для исходного stancl commit 76e5f9655924271c6c9da54e2d4ccb82c6f71ea0 и jobpipeline v2.0.0-rc7 ref 07dc54a49cf9e2df1238ee4a347af333260519a6. Temporary consumer-root pin/lock с branch-metadata risk не выбран. Exact mirror org/repo/name/version и maintainer определяются отдельным spec; refs здесь выбраны из D8 evidence, не переопределены.

Queue decision: `STANCL-SOURCE`; selected option: `owned-fixed-metadata-source-spec`; outcome: `apply` for the declared future-spec-design-only scope.

## Почему

Consumer-root #ref pin не фиксирует branch metadata; dependency #ref игнорируется при consumer resolution. Owned immutable artifact/metadata источник позволяет доказать одинаковый resolved graph. Root lock discrepancy не исправляется одним topology choice.

## Consequences

Отдельный executable spec для licence/ownership verification, fixed metadata/artifact mapping и manifest/lock/installed-ref reconciliation в root и pristine package matrix. Не делать dependency edits до scoped executable approval; внешние fork/mirror/tag/push операции отдельно authorized. До применения reverify выбранные upstream refs и metadata, без обещания GA. Rollback — previously proven immutable artifact/consumer lock, no branch fallback/live DB delta.

P3 остаётся закрытой. Решение фиксирует owner branch choice; дальнейшая реализация оформляется отдельными detailed executable specs. D8 остаётся неизменным evidence/proposal record. Queue answer записывается без plan_requests.resolve(), который добавил бы in-progress событие terminal P3.3. Проверяются queue schema, evidence/request/policy/action digests и owned views; terminal journal/state/status сохраняются побайтно.
