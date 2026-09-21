---
id: D103
date: 2026-09-20
status: accepted
item: P11.4
items: [P11, P11.4, P11.5, P11.8]
supersedes: []
superseded_by: null
---
# D103 — Registry fingerprint is stored provenance, never exact-work replay identity

**Actor:** plan-designer/codex-gpt-5.6-sol/high
**Evidence:** `RAG:—` DA06 shows the old payload hash/digest contradicted D59 by
including ambient registry fingerprint and conflicting after unrelated module changes.

## Решение

Workflow request hash and wakeup replay digest include schema, business payload and
exact point/id/version, but exclude `registryFingerprint`. The fingerprint remains in
stored metadata, is type-validated and reported diagnostically. A versioned identity
schema selects the new algorithm; legacy unstamped/specific-version rows retain their
original comparison algorithm and bounded admission rules.

## Почему

Exact implementation version is the durable compatibility boundary. Ambient catalogue
membership is valuable provenance but cannot change whether the same request key is the
same work.

## Consequences

P11.4/P11.5 test replay after unrelated registry changes and legacy rows. Missing or
mismatched exact id/version remains fail-closed.
