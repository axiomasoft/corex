---
id: D136
date: 2026-09-27
status: superseded
item: P17.1
items: [P16, P16.4, P16.5, P16.6, P16.9, P16.10, P16.11, P16.12, P16.13, P17.1]
supersedes: []
superseded_by: D137
---

# D136 — Normalize historical P16 friction before P17.1

**Actor:** owner / human through this exact P17.1 `task:plan-exec` conversation,
recorded by plan-maintenance / codex-gpt-5.6-sol/high.

**Evidence:** `RAG:—` P17.1 recovery failed before its durable start with
`JOURNAL_CORRUPT` at `journal.jsonl:391`. A complete schema scan found exactly
lines 391–406 invalid, each solely because `friction` is
`["owner-waived-model-check"]`. The source journal SHA-256 is
`00d58f1f097aefb28159083be791d05225e2d23be697b93f5539bbf20647df90`.
The owner first authorized a `route-deviation` normalization, then explicitly
accepted the Task runtime's supported canonical target `["deviation"]`.

## Решение

Authorize exactly one `journal-migration/v1`,
`corex-p16-friction-normalization`, for this plan. Preserve the complete source
journal as a sealed preimage. On lines 391–406 replace only `friction`, changing
`["owner-waived-model-check"]` to `["deviation"]`. Bind every before/after line
digest and both whole-file digests in the manifest and receipt. Keep item,
command, actor, result, session, run, terminal evidence, timestamps and all
other fields unchanged. Reject a changed source digest, any additional line or
any additional operation.

## Почему

An append cannot make invalid historical rows schema-valid. The Task migration
runtime admits only the conservative legacy-friction normalization to
`deviation`; the sealed preimage retains the exact original classification text.
This bounded repair restores deterministic recovery without changing any P16
product or terminal result.
