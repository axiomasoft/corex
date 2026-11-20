# Source lifecycle fixture

The fixture installs CoreX from the selected checkout and stable Laravel 13 / Octane 2
from its own lock. It never adds Octane to the package's runtime dependencies.

Install the lock and execute the bounded checks from the repository root:

```bash
scripts/php84 python3 scripts/composer-with-snapshots.py --working-dir=packages/core/tests/Release/octane-consumer update --prefer-dist --no-interaction --no-progress --no-scripts
scripts/php84 python3 scripts/quality/octane-proof.py --source-root . --matrix all --evidence-dir build/quality/P09.15/octane
python3 -m unittest discover -s scripts/quality/tests -p test_octane_proof.py
```

Each PHP 8.4/8.5 process runs four cases against `Laravel\Octane\Worker`, its real
ApplicationGateway, HTTP kernel and default lifecycle listeners. Only the HTTP
transport is captured. The cases exercise tenant separation, an A→B→A sequence
with a B request failure, transport failure, and a worker restart. Each account has
a separate SQLite database and marker. An independent SQLite control database holds
the routes and admission tickets. All files are created under a fresh temporary
directory.

`receipt.json` binds the complete core source, test/config inputs, the consumer
lock, installed Octane sources, raw JUnit and per-case event traces. Verification
can be repeated without executing handlers:

```bash
python3 scripts/quality/octane-proof.py --source-root . --matrix all --evidence-dir build/quality/P09.15/octane --verify build/quality/P09.15/octane/receipt.json
```

Missing/failed/skipped handlers or assertions, stale inputs, wrong implementation,
absent events, mixed process witnesses and wrong cleanup outcomes fail verification.
This is repository source evidence; it carries no release qualification claim.
