# Design lifecycle selection

Normal path: `task.plan_lifecycle.select(LifecycleInput())`; focused validation, one semantic Next, selected projections.

```json
{
  "validation": [
    "focused"
  ],
  "review": "none",
  "audit_action": "not-requested",
  "route_evidence": "reuse",
  "projections": [],
  "commits": 1,
  "context": "continue-root",
  "semantic_next": "exec-items",
  "foreign_baseline": "visible-non-gating"
}
```
