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
