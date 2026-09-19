# P5 engine qualification

**Schema:** engine-qualification/1; p5-wakeup-receipts/1
**Execution status:** PASS — immutable upstream sources qualified; CoreX binding remains P5.4
**Item:** P5.1
**Observed:** 2026-09-16

## Decision

The paid FlexCRM product uses the permissive **MIT** license profile for these
dependencies. The qualified source pair is `symfony/workflow` 7.4.9 for the
FSM and `durable-workflow/sdk` 2.0.10 for durable engine operations. Both are
independently owned and have no `corex/*` dependency. CoreX retains the tenant
transaction, authorization, request-key/dedup and `DueSource` boundaries; this
qualification does not implement or publish that adapter.

## Inputs

| Path | SHA256 | Role |
|---|---|---|
| `artifacts/qualification/p5/candidate-sources.lock` | `73cee6bcdcf2a936c312405d473932fba1539a0934cff1497f6869c37c4ae7f2` | immutable refs, licenses and source manifests |
| `artifacts/qualification/p5/engine-upstream-suite.md` | `22a20c26804a46b928120a8b3884815155659ef3b0852a43054cca06cc04428b` | clean-install and upstream-suite evidence |
| `research/P5-wakeup-design.md` | `ac703e502544138dd6acebaf3778fe20968186b0aacec14049ed16a4f337f3fc` | frozen P5 qualification contract |

## Cases

| Case | R/AC | Adapter/profile | Given/fault | Expected | Actual evidence | Result | Limit/owner/resume |
|---|---|---|---|---|---|---|---|
| ENG-01 | R09 | standalone engine/FSM | two clean upstream source trees | install and suite without `corex/*` | `22a20c26804a46b928120a8b3884815155659ef3b0852a43054cca06cc04428b` | PASS | Symfony 218/579; Durable 639/3673, one skipped |
| ENG-02 | R09 | immutable source/license/closure | pinned tag commits and manifests | exact source, MIT license and dependency identity | `73cee6bcdcf2a936c312405d473932fba1539a0934cff1497f6869c37c4ae7f2` | PASS | upgrade only through a new immutable qualification |
| ENG-03 | R09 | engine-binding/1 ABI map | actual public source APIs inspected | freeze engine/FSM FQNs and operation seams | `22a20c26804a46b928120a8b3884815155659ef3b0852a43054cca06cc04428b` | PASS | CoreX DTO and transaction mapping is P5.4 |
| ENG-04 | R09 | upstream behavior suite | signal, timer, retry, replay and FSM test suites | execute actual upstream behavior proofs | `22a20c26804a46b928120a8b3884815155659ef3b0852a43054cca06cc04428b` | PASS | CoreX acceptance remains item-owned, not inferred |
| ENG-05 | R09 | shared-runner profile | bounded `Worker::run(int $maxPolls)` and no registered upstream schedule | CoreX runner owns polling/timers; no second scheduler starts | `22a20c26804a46b928120a8b3884815155659ef3b0852a43054cca06cc04428b` | PASS | P5.4 must prove the actual `DueSource` wiring |

## Adoption

| Surface | Proposal hash | Protected receipt | Supported local adapter | Installed contract | External owner/resume |
|---|---|---|---|---|---|
| FSM | `0c7651c6c8f152348184d3dc3e7b40039c87d5d0` | MIT source/license hash in candidate lock | `Symfony\Component\Workflow\WorkflowInterface` | no CoreX binding installed in P5.1 | Symfony upstream; requalify exact tag on upgrade |
| durable engine | `19b2695d0bbfb37f0dd1ac54632bd5b1d5023b67` | MIT source/license hash in candidate lock | `DurableWorkflow\Client`, `WorkflowHandle`, `Worker` | no CoreX binding installed in P5.1 | Durable Workflow upstream; requalify exact tag on upgrade |

## Conclusion

P5.1 has selected and qualified the upstream inputs without copying an engine
into CoreX. The subsequent P5 items own the CoreX public adapter, database and
tenant-specific runtime proof; they must not treat this source qualification as
their own acceptance evidence.
