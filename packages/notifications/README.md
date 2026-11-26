# axiomasoft/corex-notifications

Durable, tenant-local notification inbox primitives for CoreX. The package stores an idempotent
notification intent and a delivery record in the same transaction. It defaults to denying all
notification actions until the host application supplies `NotificationPolicy`.

`corex:notifications:sweep` recovers expired leases and dispatches due delivery jobs. Unknown
external outcomes are retained for reconciliation and are never blindly retried.

Schedule `corex:notifications:prune` in each active account and workspace. The command removes
expired read notifications from the inbox and clears their payload and related reference.
The read time and any dismissal or cancellation must all be older than
`corex-notifications.retention.settled_days` (a positive integer, default 90).
The generated configuration schema records the integer type and default. The package also
validates the minimum of one day when it resolves the configuration, before it scans any rows.
Unread notifications, digest members and notifications with any unfinished delivery retain their
full content. An accepted provider delivery remains unfinished until its final confirmation.

The command retains the original notification ID, request identity and digest so late retries return
the same reference and conflicting requests still fail. Terminal local deliveries retain their outcome
and hash. Deliveries linked to a provider retain the message, target and receipt required for reconciliation.
The settings control inbox content retention, while replay identities and provider evidence have no expiry.
`--older-than-days` overrides the configured window. Each transaction examines at most `--batch`
read notifications (default 1000, maximum 10000). PostgreSQL skips rows locked by other work and
revisits them on a subsequent scheduled run. `--database` must match the verified connection.

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

Tenant schema v3 adds the archive marker and builds two inbox indexes in the same transaction.
On PostgreSQL the migration holds an exclusive lock on `ntf_notifications` until both indexes
are ready. Schedule a maintenance window for large inboxes. The command's `--batch` setting
applies to pruning. Rollback is blocked once the command has compacted a notification.
