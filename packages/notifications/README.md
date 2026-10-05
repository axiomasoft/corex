# axiomasoft/corex-notifications

Durable, tenant-local notification inbox primitives for CoreX. The package stores an idempotent
notification intent and a delivery record in the same transaction. It defaults to denying all
notification actions until the host application supplies `NotificationPolicy`.

`corex:notifications:sweep` recovers expired leases and dispatches due delivery jobs. Unknown
external outcomes are retained for reconciliation and are never blindly retried.

Recipient preferences use the existing Settings cascade; their revision is mirrored in the
tenant-local recipient-state row, so an outdated preference write is rejected rather than silently
overwriting a newer choice. The built-in `in_app` channel is registered by default; consumer
providers can add channel drivers and recipient resolution without changing the inbox schema.

When `corex-notifications.digest.enabled` is enabled, the package tags a
`WakeupHandlerDefinition` (`corex.scheduling.wakeup-handlers`) and a `DueWakeupSource`
(`corex.scheduling.due-sources`). It does not depend on `axiomasoft/corex-wakeup` and does not
write `corex-wakeup.handlers`. An enabled digest without a bound `WakeupScheduler` is a
configuration violation. The package registers the
`notification.digest.v1` wakeup consumer. A digest freezes at most 100 notification IDs per
explicit UTC half-open window. The shared local wakeup runner admits a separate digest delivery;
its durable SQL due source recreates residual and late closed-window batches after a restart,
without mutating a completed wakeup request key. Current cancellation and preference state is
checked before admission. Once admitted, the delivery is an immutable effect.
`corex-notifications.digest.accept_legacy_unstamped=true` is a dangerous mode: production starts only when
`corex.acknowledged_dangerous_modes` lists that exact path.

## Installation

Install with `composer require axiomasoft/corex-notifications` through the approved CoreX channel. Apply the package-owned notification schema on the tenant connection and provide `NotificationPolicy` before exposing inbox or delivery actions. Schedule `corex:notifications:sweep` in an already trusted tenant context.

## Extension contracts

Register consumer channel drivers and recipient resolution through the package public contracts. Keep recipient identities and notification authorization application-owned. Channel delivery must preserve the durable intent/delivery identities and report unknown external outcomes for reconciliation.

## Typed errors

Package error identities use backed enums and a boot compiled kernel registry.
Error constructors require `ErrorCode`; descriptors keep scoped string wire codes.
See the monorepo [error contract](../../docs/reference/errors.md) for contributions,
validated replay and safe mapping.
