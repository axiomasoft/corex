# P4.4 notification outbox — execution receipt

Status: done with deviations.

The new `corex/notifications` package owns tenant-local `ntf_notifications`,
`ntf_deliveries`, and `ntf_recipient_state` tables. `SqlNotificationCenter`
persists an idempotent inbox intent and its `in_app` delivery in one database
transaction. `DeliveryStore` advances a durable claim epoch and ignores stale
completions; expired claims are retained as `unknown`, not blindly retried.

Local evidence on 2026-09-16:

- `composer lint -- packages/notifications packages/wakeup` — PASS
- `composer --working-dir=packages/notifications test` — PASS (12 tests, 41 assertions)
- `composer --working-dir=packages/notifications test:pg` — PASS (2 tests, 2 assertions)
- `vendor/bin/phpstan analyse -c phpstan.neon packages/notifications/src packages/wakeup/src --memory-limit=1G` — PASS
- `python3 .../scripts/task-check.py --check lint` — PASS (0 errors, 0 warnings)

The PostgreSQL lane uses the disposable `corex_test` target. It now proves an
independent delivery-row lock; the later digest run lock is recorded in the
P4.6 receipt.

The remaining deviation is coverage/root-wide validation: this runtime has no
Xdebug or PCOV driver, so the package coverage command cannot run. No coverage
threshold was changed.
