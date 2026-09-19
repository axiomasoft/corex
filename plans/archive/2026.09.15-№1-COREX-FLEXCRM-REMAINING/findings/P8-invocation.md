# P8 guarded invocation

**Schema:** p8-tool-receipts/1  
**Item:** P8.4

Guarded headless `ToolInvoker` routes compiled tool declarations through fresh policy,
schema validation, consumer `ToolExecutionGuard` and owning `OperationHandler` adapters.
Default `ReadOnlyToolExecutionGuard` admits reads only; mutations require a consumer guard.

## Cases

| Case | R/AC | Expected | Actual evidence | Result | Owner | Resume |
|---|---|---|---|---|---|---|
| INVOKE-01 | R36 | Read fake transport and direct call share catalogue/schema/policy and owning handler with identical structured results | `987b2c8aa944bb3ece6ad893ac6f22a5959234db563b04d1bdd37effae5c3aca`; `0f0a9be32b7d28e6d96f78894a6f5c68446b95363842c9aeb7a72bbaf7effc9e`; `8049bd8642dada32811a07c131ef1673e34558754f895b0d59735e15a875119b` | PASS | P8.4 | transport projection in P8.7 |
| INVOKE-02 | R36 | Invalid input, unknown fields, wrong types rejected before handler | `987b2c8aa944bb3ece6ad893ac6f22a5959234db563b04d1bdd37effae5c3aca`; `8049bd8642dada32811a07c131ef1673e34558754f895b0d59735e15a875119b` | PASS | P8.4 | schema profile unchanged |
| INVOKE-03 | R36 | Mutation default guard denies; client hints cannot bypass side_effect | `987b2c8aa944bb3ece6ad893ac6f22a5959234db563b04d1bdd37effae5c3aca`; `e8543e2afb1b9189ccb301c1e0843e0c63f1e9020f259714da6db70a42714737` | PASS | P8.4 | consumer guard adapters |
| INVOKE-04 | R36 | Consumer guard binds approval to principal/account/hash/args/operation key; changed scope or proof denies | `987b2c8aa944bb3ece6ad893ac6f22a5959234db563b04d1bdd37effae5c3aca`; `4371a1463ae4bb040ea193ba9913086f37b3a61f4b4ef37473be13bbc283f1ef` | PASS | P8.4 | consumer production guard |
| INVOKE-05 | R36 | Domain-key idempotency replays equal requests and conflicts on arg mismatch | `987b2c8aa944bb3ece6ad893ac6f22a5959234db563b04d1bdd37effae5c3aca`; `1a81f728550a308d77229521068c3e38a3c9eeeb3345c4f2c7a89e9917f5eb7d` | PASS | P8.4 | PG concurrency proof in P8.7 |
| INVOKE-06 | R36 | Stale resource version denies before final domain write | `987b2c8aa944bb3ece6ad893ac6f22a5959234db563b04d1bdd37effae5c3aca`; `4371a1463ae4bb040ea193ba9913086f37b3a61f4b4ef37473be13bbc283f1ef` | PASS | P8.4 | consumer version store |
| INVOKE-07 | R36 | Provider-key timeout keeps unknown outcome and stable operation key | `987b2c8aa944bb3ece6ad893ac6f22a5959234db563b04d1bdd37effae5c3aca`; `0f0a9be32b7d28e6d96f78894a6f5c68446b95363842c9aeb7a72bbaf7effc9e` | PASS | P8.4 | external provider owner |
| INVOKE-08 | R36 | Output schema mismatch rolls back local transaction | `987b2c8aa944bb3ece6ad893ac6f22a5959234db563b04d1bdd37effae5c3aca`; `8049bd8642dada32811a07c131ef1673e34558754f895b0d59735e15a875119b` | PASS | P8.4 | external committed mismatch in consumer guard |
| INVOKE-09 | R36 | Replay requires current visibility; revoked access never leaks prior result | `987b2c8aa944bb3ece6ad893ac6f22a5959234db563b04d1bdd37effae5c3aca`; `4371a1463ae4bb040ea193ba9913086f37b3a61f4b4ef37473be13bbc283f1ef` | PASS | P8.4 | consumer replay window |
| INVOKE-10 | R36 | Read cannot mutate; draft/irreversible receive guarded treatment | `987b2c8aa944bb3ece6ad893ac6f22a5959234db563b04d1bdd37effae5c3aca`; `e8543e2afb1b9189ccb301c1e0843e0c63f1e9020f259714da6db70a42714737` | PASS | P8.4 | consumer strong confirm |
| INVOKE-11 | R36 | Unexpected exceptions produce safe error without secrets | `987b2c8aa944bb3ece6ad893ac6f22a5959234db563b04d1bdd37effae5c3aca`; `0f0a9be32b7d28e6d96f78894a6f5c68446b95363842c9aeb7a72bbaf7effc9e` | PASS | P8.4 | safe error mapping only |
| INVOKE-12 | R36 | Handler reuses verified context; HTTP-dependent handlers need explicit module wrapper | `987b2c8aa944bb3ece6ad893ac6f22a5959234db563b04d1bdd37effae5c3aca`; `8049bd8642dada32811a07c131ef1673e34558754f895b0d59735e15a875119b` | PASS | P8.4 | module wrapper pattern |

## Inputs

| Path | SHA256 | Role |
|---|---|---|
| `delivery/p8-invocation-20260918/evidence/tool-invocation.log` | `987b2c8aa944bb3ece6ad893ac6f22a5959234db563b04d1bdd37effae5c3aca` | INVOKE-01–12 pest receipt |
| `packages/api/src/Internal/GuardedToolInvoker.php` | `0f0a9be32b7d28e6d96f78894a6f5c68446b95363842c9aeb7a72bbaf7effc9e` | guarded invoker |
| `packages/api/src/Internal/ReadOnlyToolExecutionGuard.php` | `e8543e2afb1b9189ccb301c1e0843e0c63f1e9020f259714da6db70a42714737` | default read guard |
| `packages/api/src/Contracts/ToolInvoker.php` | `d2f8a41699330903d8b7026cd4b19934981add4cccc22578ce8773fe5c0b5f22` | invoke contract |
| `packages/api/src/Contracts/ToolExecutionGuard.php` | `9a9be792c534f1304510cfee8dacdfc1bb965ff729bf5f0a34af019e3b247470` | guard contract |
| `packages/api/src/Contracts/ToolRequestContext.php` | `e3f3f4a5659fef81361e96e31e116f3b7e8ab8d0a0343e926e53dda9cae96936` | additive context contract |
| `packages/api/src/Data/ToolCall.php` | `94b987012672616b93c29fae45708dbb53d1648c7cf456e57cc87834f623f0d9` | call DTO |
| `packages/api/src/Data/ToolResult.php` | `c81c43d7d933d2b5777300ce710ac9e71e44b2230125d12aab6146bf8b61343a` | result DTO |
| `packages/api/src/ToolServiceProvider.php` | `f16a6f08c27b55a38fcc48a3b7475023df2552a2dbd641d4cdeb45e6a8f6cb01` | provider bindings |
| `packages/api/tests/Feature/ToolInvocationTest.php` | `8049bd8642dada32811a07c131ef1673e34558754f895b0d59735e15a875119b` | INVOKE feature cases |
| `packages/api/tests/Feature/ToolGuardConcurrencyTest.php` | `1a81f728550a308d77229521068c3e38a3c9eeeb3345c4f2c7a89e9917f5eb7d` | idempotency replay case |
| `packages/api/tests/Support/ToolGuardFixture.php` | `4371a1463ae4bb040ea193ba9913086f37b3a61f4b4ef37473be13bbc283f1ef` | consumer guard fixture |
| `findings/P8-catalogue-policy.md` | `b795d425b0be00db79f00889af91059b561ec1a4d7c56d931a0b6554c725870a` | P8.3 predecessor qualification |

## Adoption

| Surface | Gate | Exact digest | Owner | Result | Resume |
|---|---|---|---|---|---|
| ToolInvoker + ToolExecutionGuard + ToolCall/ToolResult | G-API | `d2f8a41699330903d8b7026cd4b19934981add4cccc22578ce8773fe5c0b5f22` | P8.4 | PASS | introspection in P8.5 |
| guarded headless invocation with read-only default | G-API | `0f0a9be32b7d28e6d96f78894a6f5c68446b95363842c9aeb7a72bbaf7effc9e` | P8.4 | PASS | transport projection in P8.7 |
