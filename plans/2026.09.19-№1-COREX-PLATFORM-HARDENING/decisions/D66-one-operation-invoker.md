---
id: D66
date: 2026-09-20
status: accepted
item: P13
items: [P13, P13.2, P13.4, P13.6, P13.8, P13.9, P13.12]
supersedes: []
superseded_by: null
---
# D66 — HTTP and exposed tools share one operation invoker

**Actor:** plan-designer/codex-gpt-5.6-sol/high
**Evidence:** RAG:— `Dispatcher` and `GuardedToolInvoker` currently call handlers
independently. The latter already distinguishes standalone tools from an operation
projection, so parity can be added without routing all tools through HTTP.

## Решение

- One transport-neutral `OperationInvoker` owns activation, authorization, field
  policy, idempotency and exactly-once handler invocation per admitted attempt.
- HTTP owns request parsing/status/headers/content negotiation. Tool projection owns
  tool policy/schema/guard. Both delegate an exposed operation to the invoker.
- Standalone tools retain the P11 `ToolExecutionGuard` path.
- The invoker imports no HTTP request, route, status, header or media type.

## Почему

Parity cannot be proved while transports duplicate the handler seam. Routing every
tool through HTTP would replace one coupling with another and break headless use.

## Consequences

P13.2 introduces the seam and parity test. P13.6/P13.8 decorate the same invocation;
P13.4/P13.9 remain projections.

