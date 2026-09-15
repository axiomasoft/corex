---
type: workflow
id: D10
date: 2026-09-14
status: accepted
item: P4.1
items: [P4.1, P4.2, P4.4, P4.5]
supersedes: []
superseded_by: null
---
# D10 — Bounded successor-input bookkeeping repair

**Date:** 2026-09-14
**Status:** accepted
**Actor:** plan-run/frontier/high · gpt-5.6-sol.
**Evidence:** RAG:— current P4.1 invocation and delegated routine technical choices in D9;
post-P4.1 focused plan-lint reproduced seven startup-read errors; original item carriers,
lock hashes and producer Deliverables.

## Решение

Repair the mechanical startup reader contract exposed when P4 became active. No item
Intent, product Files, Validation, public contract, adoption/private-read or external
authority changes. P4.1 product evidence remains GREEN and unchanged.

P4.2 starts from the compact source-qualified comparison in
`brief/P4.1-source-inputs/lock-comparison.md`, instead of exposing two complete oversized
lockfiles in its initial bundle. The projection records exact whole-file hashes, focused
stancl metadata and canonical complete graph digests; its owning executor must recheck
live hashes and machine-read/compare the complete locks before edits, as its existing
Implementation Rules and V-LOCK-DELTA require. The ignored package-lock source is the
already committed byte-identical snapshot. No lock information or delta gate is weakened.

Annotate the existing P4.4/P4.5 export-map/test-input edges with `producer=P4.3`. Their
required outputs already belong to P4.3; absent future producer artifacts mean
pending-producer, not a broken P4.1 output. Do not manufacture those outputs or bundles.

## Почему

The former whole-lock startup reads exceeded the frozen read budget; these files are
machine-readable inputs whose full bytes remain available for exact comparison. Missing
producer annotations misclassified legitimate future artifacts as dispatch errors.
These are reversible navigation/readiness repairs inside the owning plan, not new design.

## Consequences

Regenerate only changed item states, the now-readable P4.2 bundle and decision views;
rerun the exact focused plan-lint carrier GREEN. Retain downstream's prior unknown result
and append a current consumer-spec-bound result after the bookkeeping change. Update
the post-close continuity checkpoint to the new successor digest. No successor execution,
auth metadata exposure, public/private install, or renewed owner question is authorized.
