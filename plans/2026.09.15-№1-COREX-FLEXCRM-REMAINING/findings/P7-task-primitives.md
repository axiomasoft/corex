# P7 task primitives

**Schema:** p7-utility-receipts/1
**Item:** P7.3

`corex/tasks` now ships frozen `TaskPlanningCommand`/`TaskPlanningAccess`, typed planning DTOs, `corex_task_operations` dedup receipts, and PRIM-01..08 local conformance in `packages/tasks`.

## Cases

| Case | R/AC | Expected | Actual evidence | Result | Owner | Resume |
|---|---|---|---|---|---|---|
| PRIM-01 | R17 | existing Create/Update/Complete/Move commands unchanged | `5ed09809fbea53a99c797550f43d6276d1c747471df494feba157673ef0c0f22` | PASS | P7.3 | none |
| PRIM-02 | R17 | planning createProject/createSprint enforce trusted context | `e9d5a393520410d5e8ecc7c68879ee7fdc31a7dc99d146d389b0f9ed5b7dd47c` | PASS | P7.3 | none |
| PRIM-03 | R17 | parent/date ordering and request-key dedup | `e9d5a393520410d5e8ecc7c68879ee7fdc31a7dc99d146d389b0f9ed5b7dd47c` | PASS | P7.3 | none |
| PRIM-04 | R17 | recordTime atomic with spent total and operation receipt | `e9d5a393520410d5e8ecc7c68879ee7fdc31a7dc99d146d389b0f9ed5b7dd47c` | PASS | P7.3 | none |
| PRIM-05 | R17 | concurrent time-log totals serialize and repeated keys dedup | `7e5923f55a2cbe2a3ddbb8bd0f5e70f8210534ab71e23d50b06d77dd815ecacf` | PASS | P7.3 | none |
| PRIM-06 | R17 | existing completion/status policy preserved | `e9d5a393520410d5e8ecc7c68879ee7fdc31a7dc99d146d389b0f9ed5b7dd47c` | PASS | P7.3 | none |
| PRIM-07 | R17 | boxed/null context supported without ID weakening | `e9d5a393520410d5e8ecc7c68879ee7fdc31a7dc99d146d389b0f9ed5b7dd47c` | PASS | P7.3 | none |
| PRIM-08 | R17 | neutral maintenance fixture; canonical schema only | `b3c7377fc9e4799db578b02a1ab00ca2ea52b1a9d7c2d62f179af904c8436b9d` | PASS | P7.3 | none |

## Inputs

| Path | SHA256 | Role |
|---|---|---|
| `research/P7-utility-design.md` | `e8030ad4a090f1bb13f6def331f8858606fe2716754d5e8e9b12c8ef2c4cbc43` | frozen task-planning/1 profile |
| `packages/tasks/src/Contracts/TaskPlanningCommand.php` | `8728735f4acc3fbf9ef804822eb89c9c0a564780fafa5c117e12e573407ed2ff` | public planning command port |
| `packages/tasks/src/Contracts/TaskPlanningAccess.php` | `dcebd0ade8d6df48c751243b637c17ef624c8f65abb0a2cc22a8837b87658d47` | planning authorization port |
| `packages/tasks/src/Internal/Planning/PlanningCommand.php` | `8d7a66219f596b2003366299357af2170765d06f2ca7e132398c274ca7df5218` | qualified implementation |
| `packages/tasks/database/migrations/2026_09_15_000071_create_task_operation_receipts.php` | `2a0cc79670090824a7e7b68b3e9158ce8d07ceffcae0abe93ea8153487995df3` | neutral operation receipt DDL |
| `packages/tasks/tests/Feature/PlanningCommandsTest.php` | `e9d5a393520410d5e8ecc7c68879ee7fdc31a7dc99d146d389b0f9ed5b7dd47c` | PRIM-02..04/06/07/08 executable proof |
| `packages/tasks/tests/Pg/PlanningConcurrencyTest.php` | `7e5923f55a2cbe2a3ddbb8bd0f5e70f8210534ab71e23d50b06d77dd815ecacf` | PRIM-05 PG concurrency proof |

## Adoption

| Surface | Gate | Exact digest | Owner | Result | Resume |
|---|---|---|---|---|---|
| TaskPlanningCommand | G-API | `8728735f4acc3fbf9ef804822eb89c9c0a564780fafa5c117e12e573407ed2ff` | P7.3 | PASS | P7.4 may materialize |
| TaskPlanningAccess | G-API | `dcebd0ade8d6df48c751243b637c17ef624c8f65abb0a2cc22a8837b87658d47` | P7.3 | PASS | host policy binding |
| task-planning/1 DTO bundle | G-SCHEMA | `e8030ad4a090f1bb13f6def331f8858606fe2716754d5e8e9b12c8ef2c4cbc43` | P7.3 | PASS | immutable profile |
| corex_task_operations | G-SCHEMA | `2a0cc79670090824a7e7b68b3e9158ce8d07ceffcae0abe93ea8153487995df3` | P7.3 | PASS | lifecycle opt-in |
