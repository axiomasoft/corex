# P5.3 root routing — execution receipt

**Schema:** p5-wakeup-receipts/1

`routing-hint/1` is an opt-in tenancy adapter. Tenant-local durable wakeup
state remains authoritative: root rows are only acceleration hints. The root
runner switches through `TenancyManager`, calls the published
`WakeupSweeper` port, and never accepts a DSN or account from request input.

Executed on 2026-09-16 against disposable PostgreSQL database `corex_test`:

- `composer --working-dir=packages/tenancy test -- tests/Pg/WakeupRoutingTest.php` — PASS, 4 tests / 20 assertions.
- `XDEBUG_MODE=coverage /usr/bin/php8.4 vendor/bin/pest --configuration=phpunit-wakeup-coverage.xml tests/Pg/WakeupRoutingTest.php --coverage --min=80` (cwd: `packages/tenancy`) — PASS, 86.6% of the P5.3 adapter source.
- `composer --working-dir=packages/tenancy test -- tests/Arch/NoCrossDbFkTest.php` — PASS, 8 tests / 19 assertions.
- `composer --working-dir=packages/tenancy analyse` — PASS, 0 errors.
- `composer --working-dir=packages/tenancy lint:check` — PASS.

## Cases

| Case | R/AC | Adapter/profile | Given/fault | Expected | Actual evidence | Result | Limit/owner/resume |
|---|---|---|---|---|---|---|---|
| ROOT-01 | R10 / AC15 | root_registry | three equal/later/earlier publications | minimum fire time is retained and revision increments on each publication | `WakeupRoutingTest.php` SHA-256 `1c17813b03b6b9b35e49293deae706a47ebb33466df4c86a336de593f24935f1`; PostgreSQL PASS | PASS | publication is best-effort after P5.2 durable intent; a root outage is logged and reconciled |
| ROOT-02 | R10 / AC15 | root_registry CAS | worker claims due hint while a tenant refreshes it | stale revision+epoch delete does not remove refreshed hint | `WakeupRoutingTest.php` SHA-256 `1c17813b03b6b9b35e49293deae706a47ebb33466df4c86a336de593f24935f1`; PostgreSQL concurrent interleave PASS | PASS | root hint never changes tenant truth |
| ROOT-03 | R10 / AC15 | root_registry lease | claimant leaves an expired lease | later sweep reclaims it; old claimant has no matching epoch | `WakeupRoutingTest.php` SHA-256 `1c17813b03b6b9b35e49293deae706a47ebb33466df4c86a336de593f24935f1`; PostgreSQL PASS | PASS | crash recovery is local DB behavior, not external delivery proof |
| ROOT-04 | R10 / AC15 | reconciliation | hint row absent; 101 eligible accounts cross a 100-account page boundary | each account is scanned through the same sweeper port; root-local cursor and exponential retry survive between pages | `WakeupRoutingTest.php` SHA-256 `1c17813b03b6b9b35e49293deae706a47ebb33466df4c86a336de593f24935f1`; PostgreSQL 101-account two-page PASS | PASS | fleet cadence and capacity are P10; no Redis is used |
| ROOT-05 | R10 / AC15 | boxed + physical boundary | optional wakeup package is absent or profile stays `local_sweep` | no root binding/command; enabled profile uses TenancyManager and WakeupSweeper contracts only | `TenancyServiceProvider.php` SHA-256 `5ae1add3cab3e003518306e42cb1913c261d08d125fc53799e7bf8ba6c5c2e86`; PHPStan PASS | PASS | installed consumer matrix is P9 |
| ROOT-06 | R10 / AC15 | root schema | fresh root migration inventory and cross-DB FK gate | additive root hints/state schema has no cross-DB FK and root schema inventory is complete | `RootSchemaTest.php` SHA-256 `405b1c09a28b00556741a5fde0fb12ed1561fd670f632a9edc2b3efcafe58bb2`; `NoCrossDbFkTest.php` SHA-256 `bfce5d5131592254fb0c5081142e9bc538213855165cf37da66d2f2d50acdf4e`; Pest PASS | PASS | rollback/export/capacity and AC13 timing remain P10/operator scope |

## Inputs

| Path | SHA256 | Role |
|---|---|---|
| research/P5-wakeup-design.md | ac703e502544138dd6acebaf3778fe20968186b0aacec14049ed16a4f337f3fc | frozen routing-hint invariants |
| findings/P5-local-wakeup.md | 3e644aa885e31fc8f7b2a3d01f4d7292ac58d2321610562936e4e0212223242a | P5.2 durable producer receipt |
| decisions/D38-p5-wakeup-adoption.md | e1cead6cf2d20099154435d128ddd385a87d5ea332fcaafd045146df50a7355f | scoped adoption authority |
| packages/tenancy/src/Wakeup/Commands/SweepRootWakeups.php | d9ee996b041770d5843f74b61fbc21ddca9603db3de977aae571ef4d23fa50e3 | bounded root sweep/reconciliation command |
| packages/tenancy/src/Wakeup/Internal/RootRegistryWakeupHint.php | e42aa3a176cbf9bee96c7f52f347dc715536c76c31eb06ea5c1c0694d5d1a9ac | advisory revisioned root-hint publisher |
| packages/tenancy/src/Wakeup/Internal/RootWakeupReconciler.php | e3265956f87384782923767522c4fb3c2c2a39616651003465616fe1b14f1f34 | lease/CAS sweep and cursor reconciliation |
| packages/tenancy/src/TenancyServiceProvider.php | 5ae1add3cab3e003518306e42cb1913c261d08d125fc53799e7bf8ba6c5c2e86 | optional dependency binding guard |
| packages/tenancy/config/corex-tenancy.php | 85b4312ee2497ef3882e2cf6309f1ea5701d42cdb70ed3b6a94d1daea335334f | opt-in profile configuration |
| packages/tenancy/tests/Pg/WakeupRoutingTest.php | 1c17813b03b6b9b35e49293deae706a47ebb33466df4c86a336de593f24935f1 | isolated PostgreSQL routing proof |
| packages/tenancy/phpunit-wakeup-coverage.xml | c042422f7fba83d87429ccf312dc504a81e2cce4deb07b664146b81fbf605162 | isolated P5.3 source coverage boundary |
| packages/tenancy/database/migrations/root/2026_09_15_000003_create_root_wakeup_hints_table.php | bf0e930a173b3f52927fe3d2efce5a1826485c31d5f020643b148633caf28a95 | advisory hint schema |
| packages/tenancy/database/migrations/root/2026_09_15_000004_create_root_wakeup_reconciliation_state_table.php | d5496e2f975a4ce16b193972731105025b8663d8a2b7b0fed2859e4103dcad14 | persisted cursor and retry state |
| packages/tenancy/tests/Feature/RootSchemaTest.php | 405b1c09a28b00556741a5fde0fb12ed1561fd670f632a9edc2b3efcafe58bb2 | declared root-table inventory proof |
| packages/tenancy/tests/Arch/NoCrossDbFkTest.php | bfce5d5131592254fb0c5081142e9bc538213855165cf37da66d2f2d50acdf4e | physical isolation schema gate |

## Adoption

| Surface | Proposal hash | Protected receipt | Supported local adapter | Installed contract | External owner/resume |
|---|---|---|---|---|---|
| `routing-hint/1` | `8101a51c2cde939584511f9af24da730ce1b2b7438fb8b4037692483fad1348b` | D38 scoped P5.3 owner receipt | `corex/tenancy` root_registry adapter | path-package qualification only | P9 installed cohort; P10 fleet cadence, capacity, export/rollback and AC13 timing |
