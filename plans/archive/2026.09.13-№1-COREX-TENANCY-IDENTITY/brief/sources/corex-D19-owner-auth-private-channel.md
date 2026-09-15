---
type: workflow
id: D19
date: 2026-09-12
status: accepted
item: P3.3
items: [P3.3]
supersedes: []
superseded_by: null
---

# D19 — Auth остаётся optional/private через immutable AzGuard source

**Date:** 2026-09-12
**Status:** accepted
**Actor:** Dmitry Vostrikov · repository owner; recorded by Codex (model identifier unavailable in session)
**Evidence:** RAG:— current owner answer; D8-distribution-owner-proposal.md; queue request `AUTH-PRIVATE-CHANNEL` action_version=1, action_digest=40a576ce509a2a89fac94c38b74c2f9c7641d76ba449c93376cff8bc2477785c.
**Supersedes:** —
**Superseded By:** —

## Owner provenance

Owner response (verbatim): «да».
Response recorded at: 2026-09-12T19:54:45Z. The preceding assistant message proposed public-candidate-split-spec, private-immutable-source-spec, owned-fixed-metadata-source-spec and minimum-factual-proof-spec and asked approval of these four directions for separate executable specs. It explicitly retained separate exact-result external publication/resource authority and unknown AzGuard immutable ref. Approval applies to this D8 block only and does not fabricate missing inputs or authorize external writes.

## Решение

Выбрать canonical private AzGuard VCS/registry channel, owner-provided immutable compatible0.2 ref и selected-repository read credentials; auth остаётся opt-in/private. Candidate VCS из D8: https://github.com/axiomasoft/azguard-private.git, не считать confirmed canonical address без source-owner подтверждения. Private contents не экспортируются в public artifacts; auth exclusion-until-release направление не выбрано.

Queue decision: `AUTH-PRIVATE-CHANNEL`; selected option: `private-immutable-source-spec`; outcome: `apply` for the declared future-spec-design-only scope.

## Почему

Явный consumer-root private source и immutable ref устраняют зависимость от sibling development paths и отделяют private auth proof от public CoreX graph. Local auth vendor не является isolated install proof.

## Consequences

Отдельный executable spec фиксирует source-owner confirmed channel, immutable ref/metadata digest и read credential scope; сейчас ref неизвестен, private install spec non-runnable до этого input. Owner token provisioning/injection и private authenticated install отдельно scoped, значения не записывать. Acceptance — exact-ref cold update/install auth-as-root и auth-as-dependency, absent/denied source/credential negatives; dev overrides вне released artifact. Rollback — opt out auth, preserve existing private channel.

P3 остаётся закрытой. Решение фиксирует owner branch choice; дальнейшая реализация оформляется отдельными detailed executable specs. D8 остаётся неизменным evidence/proposal record. Queue answer записывается без plan_requests.resolve(), который добавил бы in-progress событие terminal P3.3. Проверяются queue schema, evidence/request/policy/action digests и owned views; terminal journal/state/status сохраняются побайтно.
