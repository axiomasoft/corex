# Mutation quality gates

Run commands through `scripts/php84` in the development checkout. The locked
stack is Infection 0.35.6, Pest 5.2.1 and PHPUnit 13.3.4, with the existing
whole-suite Pest adapter. Mutation files, coverage XML and JUnit remain raw.

```bash
scripts/php84 python3 scripts/quality/mutation.py --inventory --check
scripts/php84 python3 scripts/quality/mutation.py --self-test
scripts/php84 python3 scripts/quality/mutation.py --probe --package tenancy --zone security
scripts/php84 python3 scripts/quality/mutation.py --package tenancy --zone security
scripts/php84 python3 scripts/quality/mutation.py --zone security
scripts/php84 python3 scripts/quality/mutation.py --all
```

`--probe` runs the real DatabaseValue fragment with selected mutators. It proves
the runner/parser works and never writes a full-fragment acceptance receipt.
`--all` verifies every fragment's fresh receipt; a missing fragment, changed
source/test/config/tool/lock, missing PG coverage or changed PHP binary fails.
Zone scores are diagnostics: every package × zone must independently meet
security 95%, messaging 90%, or other 75%.

`mutation-zones.json` explicitly lists every production file in packages and
the application. Inventory checks the disjoint map against the working tree and
committed paths, enforces the immutable zone rules and required Data/activation
security anchors. After an intentional source addition/removal, regenerate with
`--write-inventory`, inspect the map and zero-ledger diff, then check inventory.

`mutation-ast.php` uses PHPParser to conservatively recognize declarations and
immutable storage without value expressions or branches. The zero ledger binds
each such file to its exact source hash and AST reason. Behavioral files must
generate valid mutants. A completely declarative fragment is N/A only after the
independent P09.8 review confirms its ledger; it never receives numeric MSI100.

The score uses only test kills. Timeouts, escaped and uncovered mutants remain
in the denominator. Unknown errors, bootstrap/discovery failures, DB connection
failures and OOM/exit137 block the run. Syntax exclusion requires a failing
`php -l` at the retained mutant. Compile exclusion additionally proves that the
original independently loads and the mutant fails with a declaration error.
Test assertions, TypeError and domain exceptions remain behavioral results.
Every executed mutant retains its process exit, stdout/stderr and duration.

Equivalents need an exact mutant identity, source hash, rationale, hashed replay
and independent P09.8 confirmation. They may not exceed 5% in any fragment or
globally. The cap uses integer arithmetic; 1/19 fails, 1/20 is the boundary.
Source changes expire every exception and measurement.

Each worker copies production/test/tooling and installed dependencies into an
isolated sandbox under `build/quality/mutation`. Its PostgreSQL database is
`corex_msi_<package>_<zone>_test`; readiness, existence and actual PDO connection
are checked before tests. SQLite initial coverage excludes the PG group; the
combined initial suite includes PG tests and supplies the mutation coverage.
The runner is serial (`threads=1`), initial tests have a 600s limit, mutants 10s,
and CI workers a 60 minute job budget with at most four concurrent workers.
Existing fixed sabotage cases remain a separate `scripts/mutation-check.sh` gate.
