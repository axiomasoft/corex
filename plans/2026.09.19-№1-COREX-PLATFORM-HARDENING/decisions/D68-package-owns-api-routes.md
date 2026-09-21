---
id: D68
date: 2026-09-20
status: accepted
item: P13
items: [P13, P13.4]
supersedes: []
superseded_by: null
---
# D68 — Explicit provider installation mounts package-owned API routes

**Actor:** plan-designer/codex-gpt-5.6-sol/high
**Evidence:** RAG:— the provider is manually installed but currently mounts no
routes; every host copies the same wildcard and can omit error-boundary ordering.

## Решение

- Once `ApiServiceProvider` is explicitly installed, package routes are enabled by
  default and can be disabled through validated `ApiOptions`.
- Prefix and application middleware are options; package ordering places discovery
  before wildcard and wraps the wildcard with `ErrorBoundary`.
- Capability route uses trusted authenticated context. No route reads tenant,
  workspace or actor authority from payload.
- Async command is `202 Accepted` plus `Location`; tool projection returns the same
  acceptance data without HTTP metadata.

## Почему

Explicit provider installation is already opt-in. Requiring a second handwritten
mount makes the shipped package incomplete and creates divergent security ordering.

## Consequences

P13.4 adds the route file/controllers and migrates README guidance. Hosts with custom
mounting disable package routes before retaining their adapter.

