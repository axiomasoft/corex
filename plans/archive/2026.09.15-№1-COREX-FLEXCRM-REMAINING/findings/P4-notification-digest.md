# P4.6 notification digest — execution receipt

Status: done with deviations.

`DigestBuilder` freezes at most 100 members in an explicit UTC half-open
window. Its enabled wakeup integration stores a `notification.digest.v1`
request in the same account transaction. `DigestWakeupHandler` rechecks current
cancellation and preferences before it admits an immutable digest-owned
delivery. `NotificationDigestDueSource` rebuilds closed-window residual and
late batches from SQL rows, without mutating a completed wakeup request key.

Evidence on 2026-09-16:

- SQLite suite: `composer --working-dir=packages/notifications test` — PASS,
  12 tests / 41 assertions; includes 101-item residual batching, admission,
  subsequent preference change, and disabled boot.
- Wakeup suite: `composer --working-dir=packages/wakeup test` — PASS,
  2 tests / 9 assertions; includes durable retry generation.
- PostgreSQL suite: `composer --working-dir=packages/notifications test:pg` —
  PASS, 2 tests / 2 assertions; includes independent frozen digest-run locking.
- Static analysis and token-disabled plan lint — PASS.

The remaining deviation is coverage/root-wide validation: Xdebug and PCOV are
not installed in this runtime. No threshold or acceptance test was removed.

Version: p4-extension-receipts/1.

## Cases

| Case | R/AC | Adapter/profile | Given/fault | Expected | Actual evidence | Result | Limit/owner/resume |
|---|---|---|---|---|---|---|---|
| DIG-01 | R16 | digest builder | eligible rows | frozen window is built | `WakeupBridgeTest.php` SHA-256 `64819e9f7b59ccc2a6873e03168383eea3495a3e9916f4a89ffbdfe2f41fbe54` | PASS | host config owns cadence |
| DIG-02 | R16 | wakeup bridge | enabled digest | wakeup request is scheduled | `WakeupBridgeTest.php` SHA-256 `64819e9f7b59ccc2a6873e03168383eea3495a3e9916f4a89ffbdfe2f41fbe54` | PASS | wakeup package owns delivery |
| DIG-03 | R16 | disabled profile | digest disabled | no bridge is tagged | `DisabledDigestBootTest.php` SHA-256 `9a8e2605460f3eada4f0934ba11bfe1fb9fc1cc6e198e20b223c22a8b01c2bcd` | PASS | boxed default remains local |
| DIG-04 | R16 | digest handler | revoked preference | current preference denies admission | `WakeupBridgeTest.php` SHA-256 `64819e9f7b59ccc2a6873e03168383eea3495a3e9916f4a89ffbdfe2f41fbe54` | PASS | preferences owner remains host |
| DIG-05 | R16 | PG run lock | concurrent run | one frozen run advances | `DigestConcurrencyTest.php` SHA-256 `2ae182f95bf2a94a1a7d6e5afdb064f8ff9743fada7701df51bfe89412796881` | PASS | PG test target only |
| DIG-06 | R16 | bounded batch | more than one batch | residual work is scheduled safely | `WakeupBridgeTest.php` SHA-256 `64819e9f7b59ccc2a6873e03168383eea3495a3e9916f4a89ffbdfe2f41fbe54` | PASS | product formatting is excluded |
| DIG-07 | R16 | replay | already dispatched tick | duplicate delivery is refused | `WakeupBridgeTest.php` SHA-256 `64819e9f7b59ccc2a6873e03168383eea3495a3e9916f4a89ffbdfe2f41fbe54` | PASS | P11 owns external transport |

## Inputs

| Path | SHA256 | Role |
|---|---|---|
| research/P4-extension-design.md | 957873c6995cb3811983217a67f99d5068416f4f46da8a1653650cbc595d143a | frozen digest seam |
| packages/notifications/tests/Digest/WakeupBridgeTest.php | 64819e9f7b59ccc2a6873e03168383eea3495a3e9916f4a89ffbdfe2f41fbe54 | bridge conformance |

## Adoption

| Surface | Proposal hash | Protected receipt | Supported local adapter | Installed contract | External owner/resume |
|---|---|---|---|---|---|
| `digest/1` | P2 catalogue | local test receipt | SQL builder + Wakeup bridge | `corex/notifications` | P11 owns transport delivery |
