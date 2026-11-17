# axiomasoft/corex-scheduler

`axiomasoft/corex-scheduler` schedules durable, one-shot work at explicit UTC instants in the current tenant database. A producer
stores an idempotent request in its current account database; `corex:scheduler:sweep` claims due
requests and runs registered transactional handlers with a generation and lease.

Recurrence rules belong to `axiomasoft/corex-recurrence`; recurring task creation belongs to
`axiomasoft/corex-recurring-tasks`. This scheduler executes the resulting due requests. The host
invokes the sweep command through its Laravel scheduler or process supervisor.

The boxed mode uses one SQL connection and no Redis, root fan-out, workflow engine, or timezone
inference. Feature packages may tag a `DueWakeupSource` for the same runner; sources are bounded by
the command limit and rebuild their work from durable rows after restart.

`WakeupRequest` accepts only UTC instants, a registered lower-case kind, a UTF-8 key and bounded
JSON-safe payloads. A request is durable before any optional hint is sent. A host application runs
`corex:scheduler:sweep` at its configured cadence (normally every 10 seconds); handlers must perform
only transactional work on the current tenant connection and persist external delivery through an
outbox.

## Installation

Install with `composer require axiomasoft/corex-scheduler`. Apply the package-owned tenant wakeup schema, register handler kinds, and schedule `corex:scheduler:sweep` for each already trusted tenant connection. The package does not infer an account connection from a request payload.

## Extension contracts

Scheduling contracts (`WakeupScheduler`, `DueWakeupSource`, `DueWakeupHandler` and the wakeup data types) live in the kernel module `CoreX\Scheduling`, as do `DueWakeupSweeper` and `WakeupScheduleHint`. This package implements those canonical contracts directly; it declares no duplicate scheduling interfaces.

Register exported `DueWakeupHandler` implementations in `corex-scheduler.handlers` or by tagging a `WakeupHandlerDefinition` with `WakeupHandlerDefinition::TAG` (`corex.scheduling.wakeup-handlers`). A repeated kind is invalid configuration. Feature packages can tag bounded `DueWakeupSource` implementations with `DueWakeupSource::TAG` (`corex.scheduling.due-sources`) for the same sweeper. Each handler is an array with `handler`, an exact `version` and an optional boolean `accept_legacy_unstamped`; the former scalar `kind => class` form is rejected as an invalid configuration. `accept_legacy_unstamped=true` is a dangerous mode: production starts only when `corex.acknowledged_dangerous_modes` lists `corex-scheduler.handlers.<kind>.accept_legacy_unstamped`. Keep handler effects transactional; external delivery belongs in an outbox.

## Failure semantics

Unknown kinds, unsupported versions and stale generation/lease claims cannot execute a handler. Attempts are bounded by `corex-scheduler.max_attempts` (default 5); leases default to 60 seconds. Reconcile retained/quarantined durable state and handler version before retry, rather than deleting the request identity.

## Schema installation

Register the package provider, then run `php artisan packman:schema:migrate --scope=tenant --database=<tenant>`. Module sets are installed by `ModuleLifecycle::enable()`; template preparation includes them with `--modules`. Inspect the journal with `packman:schema:status`.

Схема: набор(ы) `tenant`, baseline v1. Baseline заменяет невыпущенную upgrade-цепочку; новая установка использует финальную схему.
