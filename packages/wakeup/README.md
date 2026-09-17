# corex/wakeup

`corex/wakeup` provides durable, tenant-local wakeup requests for CoreX modules. A producer
stores an idempotent request in its current account database; `corex:wakeup:sweep` claims due
requests and runs registered transactional handlers with a generation and lease.

The boxed mode uses one SQL connection and no Redis, root fan-out, workflow engine, or timezone
inference. Feature packages may tag a `DueSource` for the same runner; sources are bounded by
the command limit and rebuild their work from durable rows after restart.

`WakeupRequest` accepts only UTC instants, a registered lower-case kind, a UTF-8 key and bounded
JSON-safe payloads. A request is durable before any optional hint is sent. A host application runs
`corex:wakeup:sweep` at its configured cadence (normally every 10 seconds); handlers must perform
only transactional work on the current tenant connection and persist external delivery through an
outbox.
