# P09.9 primary-source verification — 2026-10-07

Question: can pinned Infection 0.35.6 run actual Pest 5.2.1 / PHPUnit 13.3.4
tests on PHP 8.4–8.5 with Laravel 12–13, without downgrading dependencies?

Verified with `web.run` and direct HTTPS captures; compatibility itself is
established or rejected by the retained Composer solves and actual processes,
not inferred from upstream declarations.

| Capture | Exact primary source | Finding |
|---|---|---|
| `infection-0.35.6-composer.json` | https://raw.githubusercontent.com/infection/infection/0.35.6/composer.json | Engine dependencies resolve with the existing root lock without changing existing package rows. |
| `pest-5.2.1-composer.json` | https://raw.githubusercontent.com/pestphp/pest/v5.2.1/composer.json | Requires `symfony/process ^8.1.7`; PHPUnit is bounded to exactly 13.3.4 by the require/conflict pair. |
| `laravel-12.69.3-composer.json` | https://raw.githubusercontent.com/laravel/framework/v12.69.3/composer.json | Requires `symfony/process ^7.2.0`; the current complete Laravel 12 solve is unsatisfiable with pinned Pest. |
| `infection-help.txt` | Installed Infection 0.35.6 CLI; upstream https://raw.githubusercontent.com/infection/infection/0.35.6/src/Command/RunCommand.php | Actual `--with-uncovered` and `--with-timeouts` options exist; every matrix invocation retains both. |

The published extension interface and runtime implementation were checked
against the installed, root-locked `infection/abstract-testframework-adapter`
0.5.1 and Infection 0.35.6 (`TestFrameworkAdapterFactory`,
`TestFramework/Factory.php`, `LegacyTestFrameworkBridge`,
`TestFramework/Coverage/JUnit/JUnitTestExecutionInfoAdder`). The fallback uses
that interface and the installed IncludeInterceptor API; no vendor patch or
Composer replacement metadata is used.

Native direct-Pest execution is retained in `../validation/native-pest-final.log`:
it incorrectly reports PHPUnit 5.2.1 and rejects the actual Pest run. The
fallback executes the full fragment while preserving Pest's raw coverage and
JUnit. This avoids the pretty-name/generated-class mismatch without rewriting
reports or manufacturing test locations.

The `matrix/` archive and completion record contain current runtime versions,
locks, coverage, JUnit, command flags, process traces and the exact unresolved
version conflict. These captures do not prove a supported Laravel 12 run.
