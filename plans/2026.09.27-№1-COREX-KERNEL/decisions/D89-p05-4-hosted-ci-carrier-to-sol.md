---
id: D89
date: 2026-10-03
status: accepted
item: P05.4
items: [P05.4]
supersedes: []
superseded_by: null
---
# D89 — The hosted-CI carrier leaves P05.4; the hosted run is done separately

**Actor:** repository owner; запись executor/claude-code/claude-sonnet-5-5/high.
**Evidence:** `artifacts/P05.4-execution/owner-direction-d89.md` (sha256 `4341fd230f771c2f3b369118b7b5ccd2d5df2e84213c7c7d82f20a121ebef3cd`) — verbatim owner messages and the chosen option; RAG:—.

## Solution

The hosted-CI Validation bullet (D73 п.4) is removed from the P05.4 spec, together with the phase-closure wording that depended on it. P05.4 closes on its local Validation (self-check review, `light`). Phase P05 is **not** closed by this decision: D73 still requires a green workflow `CI` on the final phase commit, and the owner runs that (the push, the read of the run, repair of any red job) separately with the "sol" executor. Until that evidence exists the phase stays open and `handoff.md` names the requirement. The spec edit changes the design fingerprint, so this decision carries a `design-audit-waiver/v1` for the new fingerprint and replaces D88 in `plan.md`; no repeated whole-plan design audit is assigned (the change deletes one Validation bullet and rewords two lines, routes, order and review are unchanged).

## Why

The owner does not want hosted CI handled in this session. Closing P05.4 with the bullet unsatisfied is forbidden by plan-lint (D76), so the carrier has to move rather than be skipped. The owner also asked for an independent review on `codex/gpt-6.1-sol` (high); that is a separate read-only run and is not started from this session.

## Consequences

P05.4 closes GREEN on local evidence; P05 stays open on the hosted-CI requirement (D73). No push was made; the product change is local. A red hosted job on the final P05 commit is repaired by whoever runs the hosted CI, not by this item. D88 stays accepted: its P05.3 carrier move is unchanged; only its waiver is replaced for the new fingerprint.

```design-audit-waiver/v1
{
  "schema_version": "design-audit-waiver/v1",
  "plan_id": "2026.09.27-№1-COREX-KERNEL",
  "decision_id": "D89",
  "scope": "whole-plan-design-audit",
  "design_fingerprint": "be4962cced9497af358502806c8b666e9c3466f81339bcd9e9dcefa182285f5d",
  "owner_provenance": {
    "path": "artifacts/P05.4-execution/owner-direction-d89.md",
    "sha256": "4341fd230f771c2f3b369118b7b5ccd2d5df2e84213c7c7d82f20a121ebef3cd",
    "message": "Перенести carrier (Recommended)"
  },
  "reason": "Owner removes the hosted-CI carrier from P05.4 (hosted CI is done separately); the amendment deletes one Validation bullet and rewords two lines, so no repeated whole-plan design audit is assigned for this fingerprint."
}
```
