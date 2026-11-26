# Pest adapter for Infection

The mutation runner uses this extension to run the locked Pest and PHPUnit
versions in each package fragment. The adapter reads the tests selected by
coverage, keeps every method and dataset of their test classes, and launches
the Pest CLI through `pcntl_exec`. Raw output, coverage XML and JUnit remain
available to the runner. The adapter name `pest-spike` is part of the Infection
configuration used by existing fragments.

From the repository root, validate the parser and adapter controls with
`scripts/php84 python3 scripts/quality/mutation.py --self-test`. Run the
bounded tenancy probe with
`scripts/php84 python3 scripts/quality/mutation.py --probe --package tenancy --zone security`.
The [mutation runner guide](../README.md) documents the PostgreSQL connection,
coverage driver, fragment scores and evidence needed for a full run.
