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
