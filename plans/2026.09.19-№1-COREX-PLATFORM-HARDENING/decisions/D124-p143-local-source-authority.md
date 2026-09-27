---
id: D124
date: 2026-09-25
status: superseded
item: P14.3
items: [P14.3]
supersedes: []
superseded_by: D125
---

# D124 — P14.3 uses a local-source G-DDL boundary

**Actor:** owner / human, with plan-maintenance / codex-gpt-6/high.

**Evidence:** `RAG:—` P14.3 changes only checkout migration source, provider
registration, documentation and tests against disposable SQLite/PostgreSQL
databases. The exact owner `task:plan-run` invocation reached
`AUTHORITY_PROVIDER_UNAVAILABLE` before durable start because this Codex provider
has no external verifier or nonce store for the default `provider-proof` boundary.

## Решение

P14.3 keeps `[OWNER-GATE:G-DDL]`, `branch=main` and
`scope=packages/api/database/migrations/`. Its Authority additionally declares
`boundary=owner-session`, `effect=local-source` and `environment=corex_test`.
The item remains limited to its declared Files and isolated test databases. This
amendment does not authorize production migration, rollout, external sends,
secret rotation, deployment or release.

## Почему

The local boundary matches the actual reversible checkout and isolated-test
effects while retaining the exact session-bound owner grant and finite migration
scope.
