---
id: D120
date: 2026-09-24
status: accepted
item: P11.8
items: [P11, P11.6, P11.7, P11.8]
supersedes: []
superseded_by: null
---

# D120 — Repair two historical E11e batch receipts before P11.8

**Actor:** owner/vostrikov (human), delegated the concrete repair choice to the executor on 2026-09-24.

**Evidence:** `RAG:—` `task.plan_recovery.prepare()` failed with
`JOURNAL_CORRUPT` at `journal.jsonl:249`. A full schema scan found only lines
249 and 251 invalid, both E11e `in-progress` events. Source journal SHA-256 is
`0f17303bf7dc033c38a3adf85db8bfcceb50cf06767131411cbe9f432b36295b`;
line digests are `9979a107f4f43677ba725be7851eb85a95339587610abe5759df094f3e4d7042`
and `a266e9d3542b1e8e680180e5b2432e070be3f0f152526c3062a0ad8628042d51`.
The owner asked the executor to choose the best quality and efficiency outcome
after being shown the exact two-line repair and migration-tool scope.

## Решение

Authorize exactly one `journal-migration/v1`,
`corex-e11e-batch-receipts`, for this plan. Preserve the entire source journal
as a sealed preimage. Replace only `batch_receipt` in lines 249 and 251,
binding before/after line digests and whole-file digests in the manifest and
receipt. Keep `batch_id`, membership, completed prefix, stop disposition,
item, command, actor, result, session, run, and all terminal records unchanged.
Backfill the missing digest fields from the unchanged E11e routing and item
specification; retain `handoff_digest: null` where historical provenance is
not available. Reject a changed source digest, additional line, or semantic
batch change.

Permit the narrowly constrained migration-tool repair at the Task package's
owning source. This decision does not grant a new execution route, rewrite
terminal evidence, or expand P11.8 product scope.

## Почему

The strict journal reader cannot reach P11.8 while either old start event is
schema-invalid. An append cannot make those lines valid. The migration keeps
their exact preimage and records the smallest correction needed to resume
normal plan validation.
