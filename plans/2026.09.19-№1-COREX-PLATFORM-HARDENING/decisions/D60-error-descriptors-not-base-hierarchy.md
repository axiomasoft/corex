---
id: D60
date: 2026-09-20
status: accepted
item: P12
items: [P12, P12.1, P12.2, P12.3]
supersedes: []
superseded_by: null
---
# D60 — Error contract — descriptor/interface, not a shared exception hierarchy

**Actor:** plan-designer/codex-gpt-5.6-sol/high
**Evidence:** RAG:— packages already own typed exceptions and typed denial/results,
while `packages/api` alone owns HTTP status and serialization. RFC 9457 makes the
problem type machine identity and treats human detail as non-parseable text; it does
not require one exception inheritance tree.

## Решение

- `corex/core` owns `ErrorCategory`, `UnavailabilityReason`, immutable
  `ErrorDescriptor` and interface `DescribesError`.
- A package keeps its own exception/result type and implements the interface when
  the failure crosses a public boundary. There is no `CoreXException` base class.
- Stable machine contract is `code`, `category`, RFC 6901 `path`, `retryable` and
  documented detail keys. Exception class and human message are not stable codes.
- HTTP status and response format remain projections of `corex/api`; domain/core
  descriptors do not import HTTP concepts.
- Expected business denial remains a typed result/decision. Exceptions represent
  programming/configuration errors, infrastructure failures and explicit conflicts;
  batch partial failure remains a per-record report.

## Почему

A base hierarchy would centralize package semantics in `corex/core` and still would
not define transport behavior. A small interface preserves package ownership while
giving API, logs and telemetry one stable vocabulary.

## Consequences

P12.1 defines the vocabulary and compatibility policy; P12.2 maps it without
changing the external envelope; P12.3 proves the model on measured attributes
failures instead of converting the whole repository speculatively.
