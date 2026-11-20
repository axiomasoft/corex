# Installed scenarios

Run an implemented case from the candidate checkout.

```sh
scripts/php84 python3 scripts/distribution/prove_scenario.py \
  --candidate "$COREX_CANDIDATE_DIR" --case installed-boot
```

Each case declares its package composition and database in `case.json`. A `host/` overlay can supply
the Laravel bootstrap and routes. The `artisan` list runs case-specific migration and activation
commands. The installer verifies the application lock against the candidate ZIPs before running the case.

The `actions` list starts each process, awaits its named marker, releases it and records the
completed process witness. Processes write `<name>.reached` and wait for `<name>.release` under
`COREX_SCENARIO_BARRIERS`. A successful process prints JSON with its PID, positive assertion count,
backend version, connection identity, artifact references and observed states. Set
`distinct_connections` when the case requires independent database sessions. The runner records
worker stderr and the result receipt separately.

The stage 1 cases use disposable PostgreSQL 16 and 18. Set `PGSQL_HOST`,
`PGSQL_PORT`, `PGSQL_USERNAME`, and `PGSQL_PASSWORD` for a test server where the
runner can create isolated databases and enable the `ltree` extension. Use
`--postgres 16` or `--postgres 18` to require that backend version. The runner
records the server version and provisions separate control, tenant, and backup
databases according to the case.

```sh
scripts/php84 python3 scripts/distribution/prove_scenario.py \
  --candidate "$COREX_CANDIDATE_DIR" --case worker-scope --postgres 16
```

`worker-scope` executes its cloud and boxed variants. The cloud host also
reconciles and runs a tenant-local wakeup through the installed root Artisan
command under its named service grant. Select one with `--variant cloud` or
`--variant boxed` for diagnosis. The Octane counterpart
runs through `verify_installed_consumers.py --consumer octane`. The
`binding-restore` case creates and restores a native PostgreSQL template backup
while the external control store remains live. The `workspace-sweep` runner
terminates the first worker only after that worker's durable claim marker, then
starts a separate process for recovery.

Stage 2 adds `notification-visibility` and `provider-unknown` on the same PostgreSQL
lanes. The external provider process binds loopback and records requests, effects,
and lookups in `provider-journal.sqlite` under the individual proof directory.
Its receipt identifies the journal, while each Laravel worker reports its own
provisioned PostgreSQL session. Named barriers establish the order of revocation,
provider commit, lost responses, worker termination, and recovery.

Stage 3 adds `field-write` and `ddl-claim` on the same PHP 8.4 and 8.5 and
PostgreSQL 16 and 18 lanes. Both install the candidate archives with the committed
`custom-attribute-source` extension fixture and compile the Packman catalogue before
the first worker boots. `field-write` observes the contested write token and pauses
a real backfill conversion while an independent session commits a newer document.
`ddl-claim` records PostgreSQL's concurrent index progress or the committed index
before the owner acknowledgement. The receipt includes the index identity and the
actual acknowledgement count. Use `--variant interrupted`, `--variant lost-ack`,
or `--variant stale-ack` to isolate a race during diagnosis.

Stage 4 adds `search-rights` and `generation` on the same PHP and PostgreSQL
lanes. Set `MEILISEARCH_HOST` to the disposable loopback service and
`MEILISEARCH_MASTER_KEY` to its test credential. The runner checks for
Meilisearch 1.16.0, derives a unique namespace for each scenario variant, and
releases only the verified UIDs and scoped credentials issued to that variant.
The host uses the test credential to provision per-UID keys. Search and index
requests use the scoped keys configured through the installed credential SPI.
`search-rights` tests the actual Access role matrix, a PostgreSQL exact-count
snapshot, and Meilisearch candidate hydration. `generation` stops the publisher
on both sides of the real SQL commit and records public restart inspection,
late completion on the old UID, PostgreSQL backup restore, and cancellation.
