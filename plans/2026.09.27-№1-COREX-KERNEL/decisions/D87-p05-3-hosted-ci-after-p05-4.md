---
id: D87
date: 2026-10-03
status: superseded
item: P05.3
items: [P05.3]
supersedes: []
superseded_by: D88
---
# D87 — Hosted CI for P05.3 is configured after P05.4

**Actor:** repository owner; запись executor/claude-code/claude-sonnet-5-5/high.
**Evidence:** `artifacts/P05.3-execution/owner-direction.md` (sha256 `74897810f3e20ec1ebfa5a1744ea0186483e1b12ea9af0fa271e9d00194e7f0e`) — verbatim owner messages in this session; RAG:—.

## Solution

The hosted-CI Validation carrier of P05.3 (D73 п.4, D76: push of the item commit and a green run, `origin` or the owner-redirected mirror) is not run now. The owner configures hosted CI after P05.4 and executes P05.4 in a separate session. Local Validation of P05.3 is green and the independent review is recorded, but `plan-lint` (D76) forbids closing an item whose `pre_final` is not `allow`, so P05.3 stays `In progress` until either that carrier has a receipt (a green hosted run on a commit that contains `a59c38b2`) or the owner amends the carrier in the item spec under a new decision and design-audit waiver. The deferral changes nothing else: every other carrier, the review result and the empty allowlist/baseline rules stay binding.

## Why

The owner postponed the hosted-CI setup (the `origin` account cannot start jobs; the mirror needs an explicit push) and asked to record the status in the plan. Closing the item with a missing carrier would be a green claim the evidence does not support.

## Consequences

`handoff.md` and `status.md` show P05.3 as In progress with the open carrier named; `completion.md` records the local evidence. P05.4 depends on P05.3 GREEN: when the owner starts it before the carrier is satisfied they decide whether to amend that dependency or run the hosted CI for P05.3 first. Product commit: `a59c38b2`.
