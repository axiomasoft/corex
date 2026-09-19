# P3.4 durable projection receipts

Version: p3-adapter-receipts/1. The local adapter now has an explicit tenant
physical mapping, but remains unregistered: no container binding, provider boot,
central migration, root fallback or production label was added.

## Cases

| Case | R/AC | Adapter/profile | Given/fault | Expected | Actual evidence | Result | Limit/owner/resume |
|---|---|---|---|---|---|---|---|
| counters-overflow-cas | R05 | PairStore reference + physical mapping | uint64 boundaries and stale operation | canonical counters and stale fences deny | `MembershipPairStoreTest.php` `37067be9cc4973c55df19d531b5d444314d39a3a2c448692262af55f08e598ba`; affected suite GREEN | PASS | Physical DDL is D33-only; P10 deployment pending |
| equal-revision-conflict | R05 | tnt_membership_pairs | same revision with a changed canonical digest | conflict denies without regrant | `ProductionPairStoreConformanceTest.php` `9fde49b898dac94c1bb889eaf10f063b3f78dd6c724a5fa35a19c1a5f5b5f1a8`; affected suite GREEN | PASS | Final credential fence is P3.5/G-TIMING |
| stale-fence-takeover | R05 | PairStore | old operation after newer admission/barrier | old apply/commit/regrant deny | `MembershipPairStoreTest.php` `37067be9cc4973c55df19d531b5d444314d39a3a2c448692262af55f08e598ba`; package suite GREEN | PASS | No automatic runtime projection |
| concurrent-inbox-retry | R05 | tnt_membership_inbox | duplicate delivery, UUID owner and post-crash takeover | one logical row; only current leased owner acknowledges | `ProductionPairStoreConformanceTest.php` `9fde49b898dac94c1bb889eaf10f063b3f78dd6c724a5fa35a19c1a5f5b5f1a8`; `MembershipInboxCrashTest.php` `573e87c1d6fab1a099264f98eb65702c745d0b8bcb57929638ff5a62643a0306` | PASS | Pull/central reconciliation remains P3.5/P10 |
| invalid-trigger-no-write | R05 | reference inbox boundary | expired signed trigger | parser rejects before durable write | `MembershipInboxTest.php` `e5e76506ee057d0ab032d397d5174072b4fb3e0bf0231684866de5f81ca12748`; affected suite GREEN | PASS | Trigger cannot grant membership |
| independent-witness-restore | R05 | PairStore restore | absent or stale external witness | restore denies and clears active operations on accepted restore | `MembershipPairStoreTest.php` `37067be9cc4973c55df19d531b5d444314d39a3a2c448692262af55f08e598ba`; package suite GREEN | PASS | Actual external restore witness remains P10 |
| populated-migration-refusal | R05 | tenant migration | populated physical pair table during rollback | migration refuses destructive down | `ProductionPairStoreConformanceTest.php` `9fde49b898dac94c1bb889eaf10f063b3f78dd6c724a5fa35a19c1a5f5b5f1a8`; affected suite GREEN | PASS | Export/quiesce authorization remains G-STORAGE/P10 |

## Inputs

| Path | SHA256 | Role |
|---|---|---|
| packages/tenancy/src/Membership/Internal/Persistence/PairStore.php | d7e1c31a8afe450f7b1c7e5d50809aad1885215ca6acc7b0eeafb2d243462414 | hidden table allowlist, digest and fence transitions |
| packages/tenancy/src/Membership/Internal/Persistence/PairState.php | 7268a2b61bff06ed151e8f92a626fa2480ca6f41b0ec220a14fa273a5d2dad97 | immutable physical-state view |
| packages/tenancy/src/Membership/Internal/Inbox/DurableInbox.php | 11f60b581982f15c138205b7c9208ccf61005776ad962b9a44e2b419ae416623 | atomic inbox admission and leased acknowledgement |
| packages/tenancy/src/Membership/Internal/Refresh/RefreshCoordinator.php | 2015920e059088016bafc47a3aa846c2374803bff68c3dd2f46686287beab3d1 | pull outside transaction, current-lease acknowledgement |
| packages/tenancy/database/migrations/tenant/2026_09_15_000700_create_membership_adapter_tables.php | 18d261746252a8163cfbfa41716783dbea946a44817f25bc48ed9dc26b39c0fe | scoped tenant DDL and populated-down refusal |
| packages/tenancy/tests/Pg/ProductionPairStoreConformanceTest.php | 9fde49b898dac94c1bb889eaf10f063b3f78dd6c724a5fa35a19c1a5f5b5f1a8 | physical mapping/DDL conformance |
| decisions/D33-P3.4-physical-adoption.md | e5d3dfe4ea13a005b8a32641959fd3aef1e1bc2503f1371567c26a530ae88170 | matching scoped owner adoption receipt |

## Adoption

| Surface | Baseline/proposal digest | Local supported mode | Protected receipt | Registration | Remaining P10 gate |
|---|---|---|---|---|---|
| PairStore and DurableInbox physical tables | pair-inbox-final-write/1; D33 `e5d3dfe4ea13a005b8a32641959fd3aef1e1bc2503f1371567c26a530ae88170` | Explicit tenant connection and exact approved table name only | D33 scoped G-SCHEMA/G-STORAGE | None; no provider/container binding | installed consumer mapping, external restore witness and deployment |
