**P09.9 — Laravel 13 platform reconciliation: complete.**

Owner-approved D132 is applied to all 23 Composer roots. PHP ^8.4, existing development aliases, stability/package versions, upstream locked records and exact Pest 5.2.1 / PHPUnit 13.3.4 / Infection 0.35.6 are preserved. The finite root lock delta consists only of first-party requirements/path references and Composer’s root content hash. Every affected package/product has an explicit Laravel 12 support-removal CHANGELOG entry.

All declared V1–V9 checks pass: real compatibility matrix/checker, 29 protocol/platform negatives, locked security audit, manifest gate, finite platform contract, Pint, isolated PHP 8.4/8.5 root solves/platform checks/audits and zero-error maintained PHPStan analysis. The existing Operations platform contract test is reconciled with D132: 3 cases / 20 assertions pass on SQLite memory. Pint excludes only immutable generated P09.9 matrix archives; a disposable live-source style error still rejects.

| PHP | Laravel | DB | Tests / assertions | Package kills | Escaped / uncovered / timeout / exit-137 |
| --- | --- | --- | --- | --- | --- |
| 8.4.26 | 13.35.0 | sqlite | 13 / 25 | 4 | 1 / 1 / 1 / 1 |
| 8.4.26 | 13.35.0 | pgsql | 13 / 25 | 4 | 1 / 1 / 1 / 1 |
| 8.5.11 | 13.35.0 | sqlite | 13 / 25 | 4 | 1 / 1 / 1 / 1 |
| 8.5.11 | 13.35.0 | pgsql | 13 / 25 | 4 | 1 / 1 / 1 / 1 |

Each row retains byte-original Pest stdout/stderr, process traces, raw XML/JUnit, actual Infection mutation reports, runtime/lock/binary hashes and a PDO witness. PostgreSQL preflight proved the healthy host endpoint 127.0.0.1:5434 and corex_test; the probe uses only connection-local temporary tables. PHP 8.5’s missing bcmath module was supplied through a process-local scan directory, with its original module hash recorded; system configuration was untouched.

The fragment is compatibility evidence, not production MSI. Security95 / messaging90 / other75 and all uncovered/timeout/error/equivalence rules remain unchanged. Historical eight-row/v1 incompatibility evidence and earlier raw attempts remain historical.

Ordinary CI was tested only on the owner-provided [ax1oma mirror](https://github.com/ax1oma/corex-mirror/actions/runs/37607935396) at `6a67f3b9450824658a81c031f75d1a1fb28f7a89`. All 60 tested product paths equal the final local product bytes. Final result: 26/29 jobs pass; overall CI remains RED. The earlier academici mirror is billing/spending-limit blocked, and its annotation is captured. Origin, release candidates, tags/splits and publication were not touched.

The three foreign baseline failures are retained for subsequent P09 quality/review work:

- CRM coverage: 94.3%, below the existing 95% floor; its tests pass.
- Anti-weakening history: commits 9cff6888 (P09.3) and 3cdd5bde lack Golden REBASELINE entries.
- Hosted source audit: setup-php inherited COMPOSER_NO_AUDIT=1, so the strict auditor rejects before auditing any roots. Local declared locked audits pass.

No production PHP/API/SPI/schema changed. Stop at P09.9; P09.5 and release execution remain outside this invocation. The exact next recommendation is recorded by plan.py after closure.

Next recommendation (not executed):

```text
run-items: task:plan-run 2026.09.27-№1-COREX-KERNEL P09.5  (implementation/xhigh)
```
