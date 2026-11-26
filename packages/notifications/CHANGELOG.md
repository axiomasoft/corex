# Changelog

## Unreleased — notification retention

- Add `corex:notifications:prune` and `corex-notifications.retention.settled_days` (positive integer, default 90). In the active account and workspace, compact expired read notifications in bounded transactions. Unread notifications, digest members and records with unfinished deliveries, including provider acceptance awaiting confirmation, keep their content.
- Preserve replay identities and provider receipts after compaction. Apply tenant schema v3 before deploying the new runtime. On PostgreSQL, schedule a maintenance window for large inboxes because the migration holds a table lock while it builds both indexes. Rollback checks for archived rows while concurrent pruning is excluded.

## Unreleased — CoreX 2.0 delivery receipts

- Return `InboxPage` with an encrypted continuation and `UnreadCount` with an exact count or an unavailability reason. Inbox reads, mark-read, counts and send eligibility apply related-record visibility.
- Accept typed `DeliveryOutcomeKind` outcomes. Delivery intents capture the verified scope, producer source and channel selection. Provider attempts record evidence independently of the tenant database, and an ambiguous result stays unknown until reconciled.
- Use `DeliveryExecution::reconcile` or `corex:notifications:sweep --reconcile=<id>` to reconcile an existing provider effect.

## Unreleased — Packman governance

- Adopt Packman API/SPI attributes and shared configuration/error contracts directly, with explicit Internal classification and no compatibility aliases. Declare package ownership in `extra.packman` and the monorepo manifest.
- Describe module schema ownership through Packman `WithSchema` / `OwnsSchema`; the existing provider remains the registration boundary.


## Unreleased — Packman runtime

- Breaking: module declarations and schema registrations use Packman contracts directly; module lifecycle and schema execution are owned by Packman.
- Admit each digest and freeze its remaining bounded batch in one transaction; subsequent sweeps deliver every notification without duplicating an admitted batch.

## Unreleased — kernel audit repairs (2026-10-08)

- Recover concrete frozen digest requests through WakeupScheduler instead of direct handler calls; preserve durable admission/cancellation/lease/retry fencing and exclude terminal history from due selection.


## Unreleased — Laravel 13 platform

- Require Laravel 13 and PHP 8.4+; support PHP 8.4–8.5. Laravel 12 support is removed. Testbench uses the Laravel 13 family; package versions and runtime contracts are unchanged.

## Unreleased — PHP 8.4 modernization

- Modernize maintained PHP for PHP 8.4 with final concrete classes, immutable value state and controlled named calls; preserve current public native parameter names and extension behavior.
- Remove unregistered Internal queue jobs: `DeliverDigest`.

## Unreleased — baseline v1

- The tenant schema baseline replaced the unreleased upgrade chain for new installations.

## Unreleased — digest wakeup without a wakeup package dependency

- Schema delivery now uses tagged `SchemaSetProvider` declarations and `packman:schema:migrate`; migration publishing is removed.

- Breaking (pre-1.0): removed the production `axiomasoft/corex-scheduler` requirement. The package keeps it as `require-dev` so the base API compare and the digest bridge tests can install the runner. An enabled digest tags `WakeupHandlerDefinition` and `DueWakeupSource` instead of writing `corex-scheduler.handlers`. `DigestSchedulerCheck` rejects an enabled digest when `WakeupScheduler` is not bound. Disabled digests still freeze a batch and never schedule.

## Unreleased — thin providers and registrars

- `NotificationsServiceProvider` delegates to `NotificationsBindingsRegistrar` and `NotificationsDigestRegistrar`; the digest wakeup adapter no longer uses a multi-statement closure.

## Unreleased — typed Options and runtime configuration checks

- Added internal `NotificationsOptions` and a `RuntimeConfigCheck`: in `production`, `corex-notifications.digest.accept_legacy_unstamped=true` stops the process unless that exact path is listed in `corex.acknowledged_dangerous_modes`.

## Unreleased — kernel package and API migration

- Breaking (pre-1.0): Delivery, digest and preference features expose recipient references, delivery outcomes and digest DTOs through declared ports; channel execution and persistence are Internal.
- Package installation uses the `axiomasoft` Composer vendor and PHP 8.4–8.5 / Laravel 12–13 constraints; `resources/contracts/php-api.json` records the explicitly attributed API and SPI. Old names have no compatibility aliases.

## Unreleased — PHP surface strict cutover

- Breaking (pre-1.0): supported PHP symbols are declared individually with Api/Spi attributes. Concrete implementation types moved to Internal and typed ports/DTOs and error contracts replace predecessor surfaces; namespace metadata no longer grants compatibility.
- Generated `resources/contracts/php-api.json` records supported layers and signatures. Strict source/graph/BC checks enforce the declared surface; Internal has no external BC promise.

## Unreleased — typed error contract

- Added package owned error enums, definitions and safe coded exception descriptors.
- Error constructors require enum identities; existing error wire values are scoped.
- Contributions are validated and frozen at boot; persisted identities use validated lookup.

## [Unreleased]

- PHP API manifests now record direct inheritance, interfaces and traits separately and include only members declared by this package; external dependency members are omitted without changing the `php-api/1` schema.

### Changed

- Implementation classes moved to `<Module>\Internal\…`, public types to module categories
  (`Contracts|Data|Enums|Events|Exceptions`); see `docs/architecture/constitution.md` STR-003. Drain queues before
  upgrading; stored `*_type` values holding an old model FQCN no longer resolve (no aliases).

### Added

- `notification/1`: durable tenant-local inbox intents and claim-epoch protected in-app delivery.
- Settings-backed recipient preferences, registered channel resolution, and recovery sweep support.
- Optional shared-wakeup digest admission with frozen run items, residual closed-window catch-up,
  and a digest-owned delivery foreign key.
