# P09.9 — blocked on the pinned Laravel 12 testing stack

The complete PHP 8.4/8.5 × Laravel 12/13 × SQLite/PostgreSQL compatibility
preflight is **BLOCKED**. Both Laravel 12 solves fail before tests execute:
current `laravel/framework ^12.0` requires `symfony/process ^7.2.0`, while
Pest 5.2.1 requires `symfony/process ^8.1.7`. Their constraints have no
intersection. Exact raw diagnostics are in
[`matrix/work/php8.4-laravel12/solve.stderr`](matrix/work/php8.4-laravel12/solve.stderr)
and [`matrix/work/php8.5-laravel12/solve.stderr`](matrix/work/php8.5-laravel12/solve.stderr).
Primary upstream manifests and verification links are in
[`research/README.md`](research/README.md).

The root dev lock adds Infection 0.35.6 and its bounded local Pest adapter.
Every pre-existing locked package record is unchanged; no package
was updated or removed, and Pest 5.2.1 / PHPUnit 13.3.4 remain unchanged.
The Infection extension installer receives one exact plugin allowance.
The local extension version is supplied by the path repository; its manifest
and the root manifest both pass `composer validate --strict`.

Direct native-PHPUnit-adapter execution incorrectly identifies Pest's version
as PHPUnit 5.2.1 and rejects the real Pest run. The fallback uses the published
Infection extension interface, executes the pinned Pest CLI with preserved
process exits, and runs the complete fragment for every covered mutant.
It retains raw coverage XML and JUnit without rewriting Pest's generated
class names. Adapter implementation and limitations are documented in
[`scripts/quality/infection-pest-adapter/README.md`](../../../../scripts/quality/infection-pest-adapter/README.md).

Four actual Laravel 13 rows pass the raw-evidence verifier:

| PHP | Laravel | Database | Coverage |
|---|---|---|---|
| 8.4.26 | 13.35.0 | SQLite 3.45.1 | PCOV |
| 8.4.26 | 13.35.0 | PostgreSQL 16.14, `corex_test` | PCOV |
| 8.5.11 | 13.35.0 | SQLite 3.45.1 | Xdebug |
| 8.5.11 | 13.35.0 | PostgreSQL 16.14, `corex_test` | Xdebug |

Each row runs 13 Pest cases / 25 assertions: the nine existing tenancy
DatabaseValue cases, one actual Laravel/PDO temporary-table probe, and three
generated controls. Eight actual mutants are generated per row: four package
mutants killed by failed Pest tests, one escaped, one uncovered, one timeout,
and one controlled exit-137 process error. The timeout and error controls are
not counted as package kills. These deliberately surviving controls and the
engine's raw MSI numbers are compatibility diagnostics, not production MSI
evidence for the 95/90/75 thresholds.

The archived [`matrix/receipt.json`](matrix/receipt.json) explicitly records
`result: incompatible`, four verified partial rows, and two failed version
cells whose four database rows never ran. It binds current source, tests,
tooling, locks, PHP binaries, flags, raw XML/JUnit, process traces and database
witnesses. The archive's partial rows were verified after copying; the full
`--check` deliberately refuses this result. Database probes use only a
connection-local temporary table. The existing test database was ready, its
name was verified from the test harness, and its existing migration journal
was observed before any PG execution; package migrations are outside this
fragment and were not executed.

| Check | Actual exit | Evidence |
|---|---:|---|
| V1, complete matrix | 1 | `validation/V1.log`, `V1.process.json`, raw `matrix/` |
| V2, full receipt acceptance | 1 | `validation/V2.log`, `V2.process.json` |
| V3, fake/missing/PHPUnit-only/failed receipt controls | 0 | `validation/V3.log`: 19 tests |
| V4, root locked security audit | 0 | `validation/V4.log` |
| Root and adapter Composer strict validation | 0 | `validation/{composer,adapter}-validate.log` |
| Affected Pint | 0 | `validation/pint.log` |
| Whole root PHPStan max/strict scope | 0 | `validation/phpstan-root.json`: zero errors |
| PHP syntax and product diff whitespace | 0 | Actual local checks; raw process output retains original whitespace |

No full-matrix success, hosted-CI success, production MSI, release qualification,
publication, or public API/schema change is claimed. The fixed-mutation gate
does not apply to this diff because no `packages/*/src` file changed. The
detached solve worktree is disposable; all necessary evidence is retained here.

Required owner/plan-author decision: reconcile the pinned test stack with
Laravel 12 support, or explicitly amend the matrix/testing policy. D120 forbids
silently downgrading Pest/PHPUnit or substituting another engine, and D125
excludes release work. An adapter cannot resolve this Composer conflict.
P09.9 remains unsatisfied and P09.5 remains dependency-blocked. Continue only
the exact independently ready batch returned by `plan.py next PLAN3`.
