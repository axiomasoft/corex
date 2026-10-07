# Pinned Pest / Infection compatibility spike

The root dev lock pins Infection 0.35.6, Pest 5.2.1 and PHPUnit 13.3.4. This
local Infection extension uses the published `TestFrameworkAdapterFactory`
interface from `infection/abstract-testframework-adapter` 0.5.1. It invokes the
actual Pest CLI in the same PHP process using `pcntl_exec`, preserving its exit
code and Infection's ability to terminate it. It never changes vendor code.

The native PHPUnit adapter mistakes Pest's CLI version for the PHPUnit version
and expects PHPUnit's console summary. The fallback retains raw Pest output,
coverage XML and JUnit. Pest pretty-prints JUnit class names while coverage uses
generated `P\` classes; this adapter explicitly disables Infection's JUnit name
lookup and executes the complete bounded fragment for every covered mutant.
JUnit remains mandatory evidence for the spike checker. No report is rewritten.

Run from the repository root:

```bash
scripts/php84 python3 scripts/quality/infection-spike.py --package tenancy --matrix all --output build/quality/infection-spike
python3 scripts/quality/infection-spike.py --check build/quality/infection-spike/receipt.json
python3 -m unittest discover -s scripts/quality/tests -p test_infection_spike.py
```

Each cell has its own Composer manifest, lock, vendor directory and PHP binary.
Stable dependency resolution is required; version downgrades and ignored
platform requirements are unsupported. PostgreSQL runs serially on `*_test`
only. The PDO probe creates a connection-local temporary table; it does not
install or change a package schema. PHP 8.4 uses PCOV, PHP 8.5 uses Xdebug when
available; actual versions and driver details are recorded.

The selected production file and existing Pest tests are copied byte-for-byte
from tenancy. Generated controls prove escaped, uncovered, timeout and exit-137
classification. Unexpected process errors cannot satisfy those controls or
count as a killed package mutant. These intentionally surviving controls are
not a production MSI measurement or evidence for the 95/90/75 thresholds.

A failed solve records `incompatible` with raw diagnostics and any verified
partial rows. `--check` rejects it. Laravel 12 requires Symfony Process 7 while
Pest 5.2.1 requires Symfony Process 8, so the current complete matrix remains
blocked pending a plan decision. Successful Laravel 13 rows do not discharge
that prerequisite. The engine's wider source runner remains separate work.
