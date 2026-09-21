---
id: D71
date: 2026-09-20
status: superseded
item: P13
items: [P13, P13.1, P13.2, P13.8, P13.12]
supersedes: []
superseded_by: D105
---
# D71 — API authorization is an intersection of five independent layers

**Actor:** plan-designer/codex-gpt-5.6-sol/high
**Evidence:** RAG:✅ existing Q17 supports scope, actor, record and field controls as
non-substitutable layers. RAG:— N08 proves static `onlyFields` is currently ignored.

## Решение

- Invocation checks client scope, actor operation permission, record policy, field
  policy and tenant boundary independently.
- Shared invoker owns client/actor and field seams. Module handler retains record
  policy and tenant-scoped lookup; no generic repository is introduced.
- Static declared fields are a maximum. Dynamic host policy may narrow, never widen.
- Forbidden writes reject the whole invocation before handler. Forbidden read fields
  are pruned in both HTTP and exposed tool projections before output validation.
- `ResourceId` type prefix improves validation/logging but grants no authority.

## Почему

Merging these checks lets a broad OAuth scope bypass record/FLS policy or lets an
opaque id bypass tenant ownership. Silent `onlyFields` metadata is an actual leak.

## Consequences

P13.8 closes N08 and supplies negative cross-transport fixtures. P10 eight-check
attribute writes remain authoritative inside their handler and are not duplicated.
