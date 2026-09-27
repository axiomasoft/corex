---
id: D122
date: 2026-09-25
status: accepted
item: P13.12
items: [P13.12]
supersedes: []
superseded_by: null
---

# D122 — P13.12 uses a local-source G-API boundary

**Actor:** owner / human, with plan-maintenance / codex-gpt-6-sol/xhigh.

**Evidence:** `RAG:—` P13.12 is a checkout-only contract freeze. The exact
`task:plan-exec` invocation reached `AUTHORITY_BOUNDARY` before its durable
start. The Codex provider has no verifier or nonce store for the default
`provider-proof` boundary (`AUTHORITY_PROVIDER_UNAVAILABLE`). The owner chose
the recommended local gate in this session and then directed the executor to
do the necessary work with quality, reliability and flexibility.

## Решение

P13.12 keeps `[OWNER-GATE:G-API]`, `branch=main` and
`scope=operation-contract/1`. Its Authority additionally declares
`boundary=owner-session`, `effect=local-source` and `environment=checkout`.
The item remains limited to its declared Files, Validation and local source
effects. This amendment does not grant G-API and does not authorize a release,
deployment, production change or external publication. The Task runtime still
requires a fresh exact owner `task:plan-run` invocation for P13.12 before
protected product writes.

## Почему

The current provider cannot verify the default protected gate. The local
boundary matches the actual checkout-only effect while retaining an exact
session-bound owner grant and the original contract scope.
