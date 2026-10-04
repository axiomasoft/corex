---
id: D104
date: 2026-10-04
status: accepted
item: P07
items: [P07]
supersedes: []
superseded_by: null
---
# D104 — owner-no-repeat-design-audit (accepted)

**Actor:** owner, explicit current-conversation instruction; recorder is the authorized repair root.
**Evidence:** artifacts/P07-formal-design-audit-repair/owner-direction.md; installed Task docs/reference/design-audit-waiver.md and plan_readiness.design_gate; original audit-attention report/journal event 222 preserved.

## Solution

The owner requests completing the local repair and explicitly refuses another audit: «аудит уже был, больше не надо. доделай свою задачу!!!». Select this exact fingerprint-bound waiver in plan.md in place of stale D89. It waives only a repeated whole-plan design audit; no independent audit-green event, new reviewer, model substitution or product execution is inferred.

```design-audit-waiver/v1
{
  "schema_version": "design-audit-waiver/v1",
  "plan_id": "2026.09.27-№1-COREX-KERNEL",
  "decision_id": "D104",
  "scope": "whole-plan-design-audit",
  "design_fingerprint": "6c53c4206610981206cf9c23184718411e392ab54384ef8c5bf96350099780e3",
  "owner_provenance": {
    "path": "artifacts/P07-formal-design-audit-repair/owner-direction.md",
    "sha256": "abbab8c0f3c926409042356676620ca94c58949c7e573df198f28e6052ce9b20",
    "message": "аудит уже был, больше не надо. доделай свою задачу!!!"
  },
  "reason": "Owner explicitly refuses a repeated whole-plan design audit and directs completion of the reported local repair in this root. This admission does not assert independent audit GREEN or close P06."
}
```

## Why

The previous current-candidate audit-attention remains true historical evidence. The owner has now explicitly chosen a no-repeat-audit admission path. The sole authored design-input change is the waiver selector D89 → D104; the changed fingerprint must be finalized through Task before this admission is accepted. A waiver must not hide a non-GREEN audit of the same finished candidate; it applies to the new administrative candidate, while the 830e2cb… verdict remains unchanged.

## Consequences

Complete the authorized carrier repair, run Task finish once for this changed administrative candidate, and verify PLAN_DESIGN_AUDIT_WAIVED_BY_OWNER with allowed=true. Do not claim formal audit GREEN. Preserve the original reports, D89 and D94–D103. Later P07 bundles await their declared predecessor outputs, per the adopted Task layout contract; closed-item bundles remain historical snapshots. The first P07 bundle is materialized and verified through Task.

P06 full owner review/hosted CI acceptance remains open under D73/D91/D94. P07.1–P07.6 remain Not started. This decision waives no item Validation, product review, producer dependency, P06/P07 acceptance, phase checkpoint or release gate. No design algorithms, APIs, code, tests, manifests, baselines or product DB are changed.
