---
id: D63
date: 2026-09-20
status: accepted
item: P12
items: [P12, P12.5]
supersedes: []
superseded_by: null
---
# D63 — OpenTelemetry is an optional adapter with pinned messaging semantics

**Actor:** plan-designer/codex-gpt-5.6-sol/high
**Evidence:** RAG:✅ OpenTelemetry messaging spans/metrics remain Development;
producer and consumer work should be connected by propagated context and span links,
not by inventing a span id from `correlationId`. Links are supplied at span creation.

## Решение

- CoreX has no mandatory exporter and no tracing runtime of its own.
  `open-telemetry/api` is suggested/require-dev for adapter qualification, not a
  mandatory runtime dependency of the default installation.
- A tiny CoreX bridge injects/extracts W3C trace carrier fields through the existing
  P5 message `metadata`; correlation and causation retain their separate business
  meanings.
- The OTel adapter starts the consumer span with the extracted producer context as
  a link. It never fabricates trace/span identifiers and never adds links after
  span creation.
- Messaging Development semantic-convention version/schema is an explicit pinned
  adapter constant and release note. Upgrade is deliberate; there is no silent
  switch when a dependency changes.
- P5 health is exported under stable `corex.messaging.*` instruments without a
  contract/account label; the per-contract breakdown remains CLI/log data.

## Почему

This satisfies interoperability and async causality without coupling every CoreX
install to an SDK/exporter or pretending Development conventions are stable.

## Consequences

P12.5 lists PSR-3 and OpenTelemetry as optional `suggest` + qualification-only
`require-dev` dependencies, adds a no-op bridge and adapter tests. Absence of both
optional packages must keep default CoreX boot green.
