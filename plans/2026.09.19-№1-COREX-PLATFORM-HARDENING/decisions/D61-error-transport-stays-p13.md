---
id: D61
date: 2026-09-20
status: accepted
item: P12
items: [P12, P12.2]
supersedes: []
superseded_by: null
---
# D61 — P12 enriches error metadata, but P13 owns the wire envelope

**Actor:** plan-designer/codex-gpt-5.6-sol/high
**Evidence:** RAG:— `EnvelopeSerializer` already emits
`{data,error:{code,message,details},meta:{request_id,api_version}}`; P13 owns
`operation-contract/1`, content negotiation and snapshots. RFC 9457 permits extension
members but changing the existing CoreX response here would split one wire decision
between two phases.

## Решение

- P12 adds category/path/retryability to `ApiError` and makes `ErrorMapper` consume
  `DescribesError`, but `EnvelopeSerializer` output remains byte-compatible.
- `RequestMetadata` receives an additive nullable/defaulted correlation property.
  A valid `X-Correlation-ID` is preserved; otherwise the request id is the local
  correlation root. `traceparent` is not reinterpreted as a business correlation id.
- P13 is the sole owner of exposing the new fields, `meta.correlationId`,
  `application/problem+json`, error snapshots and content negotiation.
- Until P13, code may observe the metadata internally but clients see the existing
  envelope and status behavior.

## Почему

The taxonomy must exist before the API profile can snapshot it, but two phases must
not both own the same serialized contract. An internal additive carrier gives P13 a
typed input without an accidental pre-release wire change.

## Consequences

P12.2 includes a regression asserting the old JSON/header shape. P13 must consume,
not recreate, this metadata when it freezes `operation-contract/1`.
