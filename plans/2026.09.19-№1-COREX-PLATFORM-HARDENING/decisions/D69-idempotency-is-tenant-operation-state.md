---
id: D69
date: 2026-09-20
status: superseded
item: P13
items: [P13, P13.5, P13.6, P13.12]
supersedes: []
superseded_by: D104
---
# D69 — Idempotency is tenant-local semantic operation state

**Actor:** plan-designer/codex-gpt-5.6-sol/high
**Evidence:** RAG:✅ Stripe documents replay of the first result, parameter mismatch
conflict, no record before execution and finite retention. RAG:— CoreX physical
tenancy and mutation live in the tenant DB; current API has no durable store.

## Решение

- `sys_api_idempotency` lives in the tenant database and stores HMAC key digest,
  scoped request fingerprint, lease and canonical semantic outcome.
- Begin happens after authentication/authorization/validation and before handler.
  Same key/fingerprint replays without another effect; mismatch conflicts.
- Every terminal outcome after begin is retained, including internal failure.
- Replay requires the same operation contract hash. HTTP headers/bytes are projected,
  not stored as authority.
- Retention and stale-lease takeover are finite. Prune removes only expired terminal
  rows. P13.5 alone carries owner-gate G-DDL.

## Почему

Central state cannot be atomic or naturally isolated with tenant-local effects.
Storing HTTP bytes would make transport the operation authority and prevent parity
with exposed tools.

## Consequences

P13.5 owns DDL/store and table ownership; P13.6 owns the invoker decorator and replay
matrix. The gate remains expected, not implicitly granted by design.
