---
type: owner-decision
id: D5
date: 2026-09-13
status: accepted
item: P3.2
items: [P3.2]
supersedes: []
superseded_by: null
---
# D5 — P3.2 mechanical signup claim admission

**Date:** 2026-09-13
**Status:** accepted
**Actor:** CoreX owner; recorded by Codex `gpt-5.6-terra/high`
**Evidence:** Explicit owner instruction in this thread; D2; P3.1 exact DDL SHA-256 `bf58222560bd96f0de1625f7956b9eb481f103ff5c1301b83a044a0973511e24`; P3.1 replay contract SHA-256 `4c900d4c662f2b70cf18ab72caa410c32f6bf558d07eebd32f48e9d6bfa59375`.
**Supersedes:** —
**Superseded By:** —

## Решение

Accept the P3.1 proposal for the bounded P3.2 mechanical adapter only. The accepted public API is `CoreX\Tenancy\Contracts\ProvisioningClaim::claim(ClaimRequest): ClaimResult`, with final readonly `Provisioning\ClaimRequest(string $requestKey, string $clusterId, string $templateId, string $requestDigest)` and `Provisioning\ClaimResult(string $accountId, bool $replayed)`. `ClaimConflict` rejects incompatible, malformed, partial, and tombstoned bindings; `ClaimUnavailable` rejects missing or unqualified template proof and unavailable pending-pool capacity. These additions belong to the next CoreX 0.2.0 release and do not change an existing interface or signature.

## Почему

The owner explicitly authorized completing P3.2 in this session. The accepted narrow seam preserves P3.1's immutable-key and fail-closed properties while keeping the P6.25 runtime saga outside this item.

Accept the exact `root_accounts_signup_key_uq` SQL and four immutable JSON members from P3.1 §2–3, including `ascii-token-128-v1` and `retain-deny-v1`. The migration is restricted to the disposable PostgreSQL 16 fixture and future management-root deployment; it must not be run against a tenant, development, or production connection in this item. Its rollback is operationally disabling the entrypoint while retaining the data and index; no automatic `DROP INDEX`, key clearing, or compensation delete is allowed.

The Action is a mechanical, root-connection-only reservation seam. It requires a caller-supplied qualified template proof and never invokes `PendingPool::pullPending()`, creates a database, queues a clone, activates an account, writes a role, accepts a membership, or compensates. In this repository there is deliberately no runtime caller binding: the future named P6.25 central provisioner remains the only permitted caller and must provide its durable retry/retention/compensation binding before an application exposes the Action. The tests may supply a local immutable proof fixture solely to prove fail-closed behavior; that fixture is not an APPROVED-SPECS producer result and does not authorize runtime claim traffic.

The tests must prove independent PG16 connections for the same key, replay, incompatible input, separate keys, tombstone/empty-key behavior, and zero mutation without valid proof. No dependency, endpoint, command, event listener, live migration, provider operation, or external call is authorized.

## Consequences

This resolves the P3.2-local `PUBLIC-API`, `SIGNUP-DDL`, `CLAIM-OWNER`, and `TEMPLATE-READY` admission boundary only to implement an unbound, proof-gated library seam and isolated tests. It grants no P6.25 saga, production traffic, template-producer certification, external operation, or release authority. Before a future runtime caller is wired, it must separately provide the actual APPROVED-SPECS integrity receipt, named caller, and durable recovery policy.
