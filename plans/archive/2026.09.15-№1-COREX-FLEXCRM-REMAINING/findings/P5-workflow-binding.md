# P5 workflow binding

**Schema:** p5-wakeup-receipts/1; engine-binding/1
**Item:** P5.4
**Observed:** 2026-09-16

The package captures immutable trigger intents in the current tenant connection. It has no engine dependency on base boot: an independently owned adapter supplies engine operations, and absence is a typed refusal. P5.1 remains the source-level proof for the selected engine/FSM pair.

## Cases

| Case | R/AC | Adapter/profile | Given/fault | Expected | Actual evidence | Result | Limit/owner/resume |
|---|---|---|---|---|---|---|---|
| BIND-01 | R09 | compiled declaration boundary | module/event is not admitted | inactive or absent declaration refuses | `b6b916eceb22965991bdfb90cfe6d2a0e20b337c02222952cacc4586fc7ea4b2` | PASS | integration adapter owns declaration source |
| BIND-02 | R09 | SQL intent capture | producer transaction persists trigger | intent is durable before dispatch | `e999203b407ed282ccc7f66e1873e30105e8ef88ccb56289a6c72ae4f25a1cf6` | PASS | shared runner relay is adapter-owned |
| BIND-03 | R09 | request-key digest | repeat and changed replay | exact replay existing; changed payload conflict | `de7b9fde83f85691ff3472d9d92daedf9ee5b22581e516825e408781b7150c7d` | PASS | engine operation lookup remains adapter-owned |
| BIND-04 | R09 | qualified upstream engine | signal, wait and timeout races | upstream determines one buffered/expired outcome | `92ba17d24c6c3e8e0ca26a8b3b483912322372140e2441c27eb83c879f9139a3` | PASS | integration package owns runtime interleave proof |
| BIND-05 | R09 | typed engine port | guard rejection or completed transition | no CoreX state bypass | `b6b916eceb22965991bdfb90cfe6d2a0e20b337c02222952cacc4586fc7ea4b2` | PASS | engine adapter owns guard/action semantics |
| BIND-06 | R09 | scalar snapshot | untrusted object/expression absent | only bounded scalar map is persisted | `b6b916eceb22965991bdfb90cfe6d2a0e20b337c02222952cacc4586fc7ea4b2` | PASS | engine expression safety remains upstream |
| BIND-07 | R10 | boxed local package | no engine adapter installed | base boot has no engine import or scheduler | `e999203b407ed282ccc7f66e1873e30105e8ef88ccb56289a6c72ae4f25a1cf6` | PASS | installed consumer qualification is P5.6 |
| BIND-08 | R09 R10 | context/request-key boundary | foreign context or invalid key | request refuses before engine call | `de7b9fde83f85691ff3472d9d92daedf9ee5b22581e516825e408781b7150c7d` | PASS | authorization policy is adapter/application-owned |

## Inputs

| Path | SHA256 | Role |
|---|---|---|
| `packages/workflow/src/Internal/SqlWorkflowBinding.php` | `b6b916eceb22965991bdfb90cfe6d2a0e20b337c02222952cacc4586fc7ea4b2` | durable binding implementation |
| `packages/workflow/tests/Feature/WorkflowBindingTest.php` | `de7b9fde83f85691ff3472d9d92daedf9ee5b22581e516825e408781b7150c7d` | local conformance cases |
| `artifacts/qualification/p5/workflow-tests.md` | `e999203b407ed282ccc7f66e1873e30105e8ef88ccb56289a6c72ae4f25a1cf6` | executed local checks |
| `findings/P5-engine-qualification.md` | `92ba17d24c6c3e8e0ca26a8b3b483912322372140e2441c27eb83c879f9139a3` | immutable upstream qualification |

## Adoption

| Surface | Proposal hash | Protected receipt | Supported local adapter | Installed contract | External owner/resume |
|---|---|---|---|---|---|
| engine-binding/1 | `0c7651c6c8f152348184d3dc3e7b40039c87d5d0` | P5.1 immutable source receipt | `WorkflowBinding` plus injected `EngineAdapter` | base package boots without engine | integration package owns engine ABI and upgrade qualification |
