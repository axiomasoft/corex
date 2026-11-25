# axiomasoft/corex-notifications

Durable, tenant-local notification inbox primitives for CoreX. The package stores an idempotent
notification intent and a delivery record in the same transaction. It defaults to denying all
notification actions until the host application supplies `NotificationPolicy`.

`corex:notifications:sweep` recovers expired leases and dispatches due delivery jobs. Unknown
external outcomes are retained for reconciliation and are never blindly retried.

`corex:notifications:prune` deletes notifications read, dismissed or cancelled more than
`corex-notifications.retention.settled_days` days ago (90 by default), together with their
deliveries once every delivery is final. Unread notifications, notifications with a pending,
unknown or quarantined delivery and notifications that belong to a digest are kept. It works in
bounded batches (`--batch`, default 1000), one transaction each; schedule it like `sweep`.

Recipient preferences use the existing Settings cascade; their revision is mirrored in the
tenant-local recipient-state row, so an outdated preference write is rejected rather than silently
overwriting a newer choice. The built-in `in_app` channel is registered by default; consumer
providers can add channel drivers and recipient resolution without changing the inbox schema.

When `corex-notifications.digest.enabled` is enabled, the package tags a
`WakeupHandlerDefinition` (`corex.scheduling.wakeup-handlers`) and a `DueWakeupSource`
(`corex.scheduling.due-sources`). It does not depend on `axiomasoft/corex-scheduler` and does not
write `corex-scheduler.handlers`. An enabled digest without a bound `WakeupScheduler` is a
configuration violation. The package registers the
`notification.digest.v1` wakeup consumer. A digest freezes at most 100 notification IDs per
explicit UTC half-open window. The shared local wakeup runner admits a separate digest delivery;
its SQL due source selects only frozen runs and recreates missing durable requests for
those exact runs after a restart. Recovery never invokes the handler directly or
replaces a cancelled, quarantined or completed request. The durable runner remains
the admission boundary. Residual and late batches require an explicit builder call. Current cancellation and preference state is
checked before admission. Once admitted, the delivery is an immutable effect.
## Installation

Install with `composer require axiomasoft/corex-notifications` through the approved CoreX channel. Apply the package-owned notification schema on the tenant connection and provide `NotificationPolicy` before exposing inbox or delivery actions. Schedule `corex:notifications:sweep` and `corex:notifications:prune` in an already trusted tenant context.

## Extension contracts

Register consumer channel drivers and recipient resolution through the package public contracts. Keep recipient identities and notification authorization application-owned. Channel delivery must preserve the durable intent/delivery identities and report unknown external outcomes for reconciliation.

## Typed errors

Package error identities use backed enums and a boot compiled kernel registry.
Error constructors require `ErrorCode`; descriptors keep scoped string wire codes.
See the monorepo [error contract](../../docs/reference/errors.md) for contributions,
validated replay and safe mapping.

## Schema installation

Register the package provider, then run `php artisan packman:schema:migrate --scope=tenant --database=<tenant>`. Module sets are installed by `ModuleLifecycle::enable()`; template preparation includes them with `--modules`. Inspect the journal with `packman:schema:status`.

Схема: набор(ы) `tenant`, baseline v1. Baseline заменяет невыпущенную upgrade-цепочку; новая установка использует финальную схему.
