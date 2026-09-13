# Reducer-owned successor continuity

P1.1 solo inventory producer uses implementation/high Routing; P1.2 open public-contract/central-trust authoring requires qualified producer artifacts and frontier/high. Current provider-attested authoring route/capabilities are sufficient for repository preparation, context telemetry unavailable. Design-root-terminal selects separate routed root; no successor started. Remaining dispatch digest binds final plan/Routing and exact selected spec, capabilities observed available, checkpoint durable.

```json
{
  "work_estimate": {
    "route_attested": true,
    "capabilities_attested": true,
    "capabilities_sufficient": true,
    "context_attested": false,
    "context_health": "unavailable",
    "batch_receipt_valid": true,
    "remaining_batch_digest": "207bdf71900f0175c13ed15a5c95673f1d9cbcfb16e70a17953f869b64bf529f",
    "loaded_inputs_reusable": true,
    "cold_start_cost": 11326,
    "lifecycle": "design-root-terminal",
    "isolated_review": false,
    "reviewer_task": null,
    "checkpoint": "plan.md + phases/P1/P1.1.md + journal.jsonl",
    "schema_version": "session-work-estimate/v1"
  },
  "decision": {
    "outcome": "cold-start-root",
    "reason": "design-root-terminal",
    "evidence": [
      "batch:207bdf71900f0175c13ed15a5c95673f1d9cbcfb16e70a17953f869b64bf529f",
      "loaded-inputs:reusable",
      "cold-start-cost:11326",
      "checkpoint:plan.md + phases/P1/P1.1.md + journal.jsonl"
    ],
    "runnable": true,
    "schema_version": "session-continuity-decision/v1"
  },
  "next_carrier": {
    "kind": "run-items",
    "command_id": "task:plan-run",
    "plan_id": "2026.09.13-№1-COREX-TENANCY-IDENTITY",
    "arguments": [
      "2026.09.13-№1-COREX-TENANCY-IDENTITY",
      "P1.1"
    ],
    "context_kind": "cold-start-root",
    "item_ids": [
      "P1.1"
    ],
    "cold_start_scope": [],
    "continuity": {
      "outcome": "cold-start-root",
      "reason": "design-root-terminal",
      "evidence": [
        "batch:207bdf71900f0175c13ed15a5c95673f1d9cbcfb16e70a17953f869b64bf529f",
        "loaded-inputs:reusable",
        "cold-start-cost:11326",
        "checkpoint:plan.md + phases/P1/P1.1.md + journal.jsonl"
      ],
      "runnable": true,
      "schema_version": "session-continuity-decision/v1"
    }
  }
}
```
