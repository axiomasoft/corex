# P7 task recurrence materialization

**Schema:** p7-utility-receipts/1
**Item:** P7.4

`corex/tasks-workflow` ships frozen `TaskRecurrenceCommand`, series/occurrence DDL, `SqlTaskRecurrenceMaterializer`, optional wakeup and workflow completion bridges, and MAT-01..09 local conformance in `packages/tasks-workflow`.

## Cases

| Case | R/AC | Expected | Actual evidence | Result | Owner | Resume |
|---|---|---|---|---|---|---|
| MAT-01 | R17/R20 | one task per occurrence key; concurrent sessions serialize | `ba36fe7a7dd7b3143368ab58f19cbe226731a2338b3203f811a550e221643785` | PASS | P7.4 | none |
| MAT-02 | R17/R20 | durable operation replay returns stored receipt | `6067c2943afc668dd3f7cedd796ac8afc43a076c04941502ef4add5de31b9be4` | PASS | P7.4 | none |
| MAT-03 | R20 | partial budget stops without holes beyond contiguous prefix | `6067c2943afc668dd3f7cedd796ac8afc43a076c04941502ef4add5de31b9be4` | PASS | P7.4 | none |
| MAT-04 | R20 | retired series denies new materialization; committed tasks kept | `6067c2943afc668dd3f7cedd796ac8afc43a076c04941502ef4add5de31b9be4` | PASS | P7.4 | none |
| MAT-05 | R20 | moved override keeps original key; revision cutover explicit | `6067c2943afc668dd3f7cedd796ac8afc43a076c04941502ef4add5de31b9be4` | PASS | P7.4 | none |
| MAT-06 | R20 | durable wakeup bridge schedules and materializes locally | `ba36fe7a7dd7b3143368ab58f19cbe226731a2338b3203f811a550e221643785` | PASS | P7.4 | none |
| MAT-07 | R20 | optional workflow completion capture in task transaction | `ba36fe7a7dd7b3143368ab58f19cbe226731a2338b3203f811a550e221643785` | PASS | P7.4 | none |
| MAT-08 | R17/R20 | stale restore epoch and access denied before writes | `6067c2943afc668dd3f7cedd796ac8afc43a076c04941502ef4add5de31b9be4` | PASS | P7.4 | none |
| MAT-09 | R17 | boxed sqlite materialization off root queue assumptions | `6067c2943afc668dd3f7cedd796ac8afc43a076c04941502ef4add5de31b9be4` | PASS | P7.4 | none |

## Inputs

| Path | SHA256 | Role |
|---|---|---|
| `research/P7-utility-design.md` | `e8030ad4a090f1bb13f6def331f8858606fe2716754d5e8e9b12c8ef2c4cbc43` | frozen materialization profile |
| `findings/P7-recurrence.md` | `4654e86c1769528f6e78c49a0172da01dc2dda95aa1bc455bcfe04d7c6c41e8b` | recurrence expander adoption |
| `findings/P7-task-primitives.md` | `3329b51f2b552ca6e24ca23b931e60389b7bd78ef0e3c913fe323d3052862773` | task operations + CreateTaskCommand adoption |
| `packages/core/src/Support/Data/DataScope.php` | `4f4da2f5a17ea56e0270fcf253b6e26475bbc3b4fe596821ff5ea6da45b7f284` | shared write scope DTO |
| `packages/core/src/Support/Data/WriteToken.php` | `e8f05733dfda14584a723795c96ddfdeced114ddf0302e22d4415561618c3ff2` | shared idempotency token |
| `packages/tasks-workflow/src/Contracts/TaskRecurrenceCommand.php` | `89d1bfd5699eac6a3d990095b3631b252cd747d16f5d7a2114359ca61676407c` | public materialize port |
| `packages/tasks-workflow/src/Internal/SqlTaskRecurrenceMaterializer.php` | `9ba39c8d6f93b3ba2b2ce5fc240c63802440771dd9f6dac32b6c4b7c7bb8b238` | qualified SQL materializer |
| `packages/tasks-workflow/database/migrations/2026_09_15_000072_create_task_series_mapping.php` | `b53c4e572d328f9fa98881e005b86b8990729976c28ad6e09c70b277a11e6500` | series/occurrence DDL |
| `packages/tasks-workflow/tests/Feature/MaterializationTest.php` | `6067c2943afc668dd3f7cedd796ac8afc43a076c04941502ef4add5de31b9be4` | MAT-01..05/08/09 sqlite proof |
| `packages/tasks-workflow/tests/Pg/MaterializationPgTest.php` | `ba36fe7a7dd7b3143368ab58f19cbe226731a2338b3203f811a550e221643785` | MAT-01/02/06/07/08 PG proof |

## Adoption

| Surface | Gate | Exact digest | Owner | Result | Resume |
|---|---|---|---|---|---|
| TaskRecurrenceCommand | G-API | `89d1bfd5699eac6a3d990095b3631b252cd747d16f5d7a2114359ca61676407c` | P7.4 | PASS | P8 may consume |
| task-materialization/1 DTO bundle | G-SCHEMA | `e8030ad4a090f1bb13f6def331f8858606fe2716754d5e8e9b12c8ef2c4cbc43` | P7.4 | PASS | immutable revision keyed |
| corex_task_series mapping | G-SCHEMA | `b53c4e572d328f9fa98881e005b86b8990729976c28ad6e09c70b277a11e6500` | P7.4 | PASS | lifecycle opt-in |
| CreateTaskCommand bridge | G-DEPENDENCY | `3329b51f2b552ca6e24ca23b931e60389b7bd78ef0e3c913fe323d3052862773` | P7.4 | PASS | same-connection writes |
