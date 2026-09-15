---
type: workflow
id: D13
date: 2026-09-14
status: accepted
item: P4.3
items: [P4.3]
supersedes: []
superseded_by: null
---
# D13 — Committed export input for P4.3

**Date:** 2026-09-14
**Status:** accepted
**Actor:** plan-exec/implementation/high · gpt-5.6-terra
**Evidence:** RAG:— D11, P4.2 terminal proof and scoped commit `ea7d0dbdf36b122904abba91e124018c11b939ed`.
**Supersedes:** —
**Superseded By:** —

## Решение

The deterministic exporter receives exactly commit
`ea7d0dbdf36b122904abba91e124018c11b939ed`; the source tree is read with
Git, not from the working directory. Staged versions are local unpublished
`0.1.0-p4.3` candidates for the four approved CoreX packages. Their metadata
is available only in disposable staging and has no path repository, sibling
source, private auth, CRM or API package.

## Почему

P4.2 is now a committed immutable predecessor. This resolves EXPORT-INPUT
without treating the active dirty checkout, a publication destination or an
unapproved package version as source evidence.

## Consequences

P4.3 may create only its declared local exporter, policy, tests, findings and
documentation. Publication, tag/push/release, secrets and every package outside
the four-member allowlist remain excluded.
