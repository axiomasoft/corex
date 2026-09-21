---
id: D104
date: 2026-09-20
status: accepted
item: P13.5
items: [P13, P13.1, P13.5, P13.6, P13.12, P14.5]
supersedes: [D69]
superseded_by: null
---
# D104 — Idempotent mutation effect and terminal outcome commit atomically

**Actor:** plan-designer/codex-gpt-5.6-sol/high
**Evidence:** `RAG:—` DA02 identifies the hard-crash window after effect commit and
before terminal record; lease fencing alone cannot undo a duplicated effect.

## Решение

The D69 tenant-local scope, HMAC key, semantic outcome, contract hash and finite
retention remain. Additionally, idempotent mutation descriptors declare
`tenant_transactional`: the store locks and fences the attempt, invokes the handler on
that same tenant connection, records the terminal outcome, and commits effect+outcome in
one transaction. Takeover blocks on that row lock, then replays a committed terminal or
claims a rolled-back attempt. Direct external/other-connection effects are ineligible;
they must persist a same-transaction outbox intent or provide an explicit reconciliation
contract. Ambiguous commit returns `reconciliation_required`, never blind retry.

## Почему

Only a shared atomic boundary closes kill-after-effect-before-terminal. Expired time
alone is not evidence that the previous effect did not commit.

## Consequences

P13.1 validates effect mode; P13.5 exposes the transactional store primitive; P13.6
removes the out-of-transaction `finally` terminal write and proves kill-point behavior
on PostgreSQL. No gate is granted by this decision.
