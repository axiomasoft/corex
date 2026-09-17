# P5.2 local wakeup — execution receipt

**Schema:** p5-wakeup-receipts/1

Local boxed `wakeup/1` is implemented by `corex/wakeup`: durable SQL rows,
idempotent schedule/cancel, generation-bound leases, bounded retry, and the
same transaction for a local handler. Root routing, engine/FSM and external
delivery remain outside this P5.2 receipt.

Commands executed on 2026-09-16:

- `composer --working-dir=packages/wakeup test` — PASS, 4 tests / 16 assertions.
- `composer --working-dir=packages/wakeup test:pg` — PASS, 1 test / 1 assertion,
  after `docker compose ps` and `pg_isready` on isolated `corex_test`.
- `composer --working-dir=packages/wakeup analyse` — PASS, 0 errors.
- `composer --working-dir=packages/wakeup lint:check` — PASS.

## Cases

| Case | R/AC | Adapter/profile | Given/fault | Expected | Actual evidence | Result | Limit/owner/resume |
|---|---|---|---|---|---|---|---|
| WAK-01 | R10 | boxed SQL scheduler | duplicate, conflicting key, forged/unknown input | one durable request or explicit refusal | `WakeupSchedulerTest.php` SHA-256 `fe0957179f42530c2395d8784bafb95da0731483dc143ef8530327ef9a744914`; Pest PASS | PASS | transaction rollback/hint-after-commit remains handler boundary |
| WAK-02 | R10 | boxed local_sweep | no Redis/root/engine dependency | durable local runner only | `SqlWakeupScheduler.php` SHA-256 `78cd065515d923c18cb13f4bb88fbb7294c916fc257ed390e2a0255ab0595d73`; package Pest PASS | PASS | physical/root profiles belong to P5.3/P9 |
| WAK-03 | R10 | PostgreSQL claim | independent connection locks due row | second claimant times out | `WakeupClaimTest.php` SHA-256 `fec856ca1a929e5c4c1342ffae4c61eb5dd4a987ff16e01dededa5a8123ca4a5`; Pest PG PASS | PASS | fairness at fleet scale is P10 |
| WAK-04 | R10 | generation lease | stale/expired claim | stale generation cannot complete | `SweepRunner.php` SHA-256 `0c38866a9849f158efb13198d385e0012a496309cd7cf16f770833e8b2bf6c89`; package Pest PASS | PASS | kill/restart remains local DB recovery only |
| WAK-05 | R10 | retry policy | handler exception | bounded retry or visible terminal failure | `WakeupSchedulerTest.php` SHA-256 `fe0957179f42530c2395d8784bafb95da0731483dc143ef8530327ef9a744914`; Pest PASS | PASS | external exactly-once requires outbox consumer |
| WAK-06 | R10 | cancellation | cancellation before/after admission | explicit durable cancellation outcome | `SqlWakeupScheduler.php` SHA-256 `78cd065515d923c18cb13f4bb88fbb7294c916fc257ed390e2a0255ab0595d73`; package Pest PASS | PASS | authorization owner remains tenant context |
| WAK-07 | R10 | UTC request/window | non-UTC or invalid window | reject invalid boundary data | `WakeupSchedulerTest.php` SHA-256 `fe0957179f42530c2395d8784bafb95da0731483dc143ef8530327ef9a744914`; Pest PASS | PASS | civil/DST policy is supplied by consumer |
| WAK-08 | R10 | due source runner | missing handler and bounded sweep | no unsafe import or blind execution | `SweepRunner.php` SHA-256 `0c38866a9849f158efb13198d385e0012a496309cd7cf16f770833e8b2bf6c89`; PHPStan/Pest PASS | PASS | optional root/engine providers remain absent |

## Inputs

| Path | SHA256 | Role |
|---|---|---|
| research/P5-wakeup-design.md | ac703e502544138dd6acebaf3778fe20968186b0aacec14049ed16a4f337f3fc | frozen local invariants |
| packages/wakeup/tests/Feature/WakeupSchedulerTest.php | fe0957179f42530c2395d8784bafb95da0731483dc143ef8530327ef9a744914 | SQLite behavior lane |
| packages/wakeup/tests/Pg/WakeupClaimTest.php | fec856ca1a929e5c4c1342ffae4c61eb5dd4a987ff16e01dededa5a8123ca4a5 | isolated PostgreSQL lock lane |
| packages/wakeup/database/migrations/2026_09_15_000001_create_sys_wakeup_requests_table.php | 2bb76f6a482585c6ceb195c7caec80479b895624576ea7d3d27ede4b569e3d05 | additive durable schema |

## Adoption

| Surface | Proposal hash | Protected receipt | Supported local adapter | Installed contract | External owner/resume |
|---|---|---|---|---|---|
| `wakeup/1` | `8101a51c2cde939584511f9af24da730ce1b2b7438fb8b4037692483fad1348b` | D38 scoped P5.2 owner receipt | `corex/wakeup` boxed SQL runner | path package only | P5.3 root hints; P9 installed cohort; P10 live timing |
