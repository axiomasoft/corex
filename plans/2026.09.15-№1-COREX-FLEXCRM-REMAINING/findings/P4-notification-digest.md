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
