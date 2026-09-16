# corex/wakeup

`corex/wakeup` provides durable, tenant-local wakeup requests for CoreX modules. A producer
stores an idempotent request in its current account database; `corex:wakeup:sweep` claims due
requests and runs registered transactional handlers with a generation and lease.

The boxed mode uses one SQL connection and no Redis, root fan-out, workflow engine, or timezone
inference. Feature packages may tag a `DueSource` for the same runner; sources are bounded by
the command limit and rebuild their work from durable rows after restart.
