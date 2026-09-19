# P5.5 bridge test evidence

Observed on 2026-09-16 in `/home/vostrikov/projects/packages/corex`.

`composer --working-dir=packages/notifications test` passed: 12 tests, 41 assertions.

`composer --working-dir=packages/notifications test:pg` passed: 2 tests, 2 assertions, after the
healthy `corex_test` PostgreSQL 16 preflight.

The bridge test creates `WakeupDelivery(notification.digest.v1)` with an explicit UTC
`WakeupWindow` and observes the typed `DigestTick`; the PostgreSQL lane holds the digest-run row
lock from a second real connection.
