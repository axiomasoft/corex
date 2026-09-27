---
id: D113
date: 2026-09-22
status: accepted
item: P5.6
items: [P5, P5.6]
supersedes: []
superseded_by: null
---
# D113 — Owner-gated migration of the legacy Task journal

**Actor:** owner (Dmitry Vostrikov), 2026-09-22; recorded during P5.6 recovery.

**Evidence:** owner instruction to bring the active plan and installed Task package into exact
current compatibility before continuing P5.6; reproduced Task 0.20.4 failures in
`findings/task-0204-lifecycle-compatibility-2026-09-22.md`.

## Решение

Authorize exactly one `journal-migration/v1` named `corex-task-0204-legacy-journal` for this
plan. The migration must preserve the complete original `journal.jsonl` bytes as a sealed
preimage and bind every changed line through before/after SHA-256 operations and a receipt.

Allowed repairs are limited to:

- neutral backfill of fields required by the current Task journal schema;
- completion of already-recorded terminal evidence without changing its GREEN carrier status;
- normalization of free-form legacy friction to the canonical `deviation` enum while retaining
  the exact original strings in the sealed preimage;
- normalization of the D109 `owner-decision/decision-accepted` observation to
  `plan-design/no-op`, preserving its owner-gate payload;
- reset of the orphan P5.6 `in-progress` row to `no-op` because it has neither `session_id` nor
  `run_id`; P5.6 must then start a new provider-attested durable run.

Any additional semantic edit, missing preimage, digest mismatch, or non-idempotent rerun is
outside this decision and must fail closed.

## Почему

The active plan predates the strict Task 0.20.4 journal and terminal-evidence readers. Append-only
compensation cannot make independently invalid historical rows valid, while direct manual edits
would destroy provenance. A digest-bound migration with an immutable full preimage is the
smallest reversible repair that lets the current runtime regenerate state and admit P5.6.

## Consequences

- The preserved preimage remains the historical source for forensic comparison.
- New journal writes use the current strict schema; no legacy exception is inherited forward.
- P5.6 may proceed only after the migrated journal, regenerated views, bundle and plan lint are
  validated by the current source and installed Task runtime.
