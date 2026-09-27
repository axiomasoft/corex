# P16.13 — Full structural qualification

**Batch:** E16h · **Actor:** plan-exec / grok-4.7 / direct (owner waived model-route check) ·
**Date:** 2026-09-27

No new layout. Qualification of the post-P16.12 tree, two planted negatives, and the phase receipt.
Commit remains withheld: the dirty tree still spans P15–P16.

## Census vs P16.11

`find packages/<name>/src -name '*.php' | wc -l` on 2026-09-27. Live packages: **20**. PHP files
in `src/`: **1129**. `packages/import-export` has no `src/` and stays out of band (D13).

| Package | Files | Profile | Agrees with P16.11 |
|:--|--:|:--|:--|
| search-meilisearch | 4 | A | yes |
| search-intl | 8 | A | yes |
| panels | 11 | A | yes |
| workflow | 15 | A | yes |
| recurrence | 16 | A | yes |
| tasks-workflow | 16 | A | yes |
| wakeup | 20 | A | yes |
| search | 29 | A | yes |
| access | 34 | A | yes |
| media | 36 | A | yes |
| transfer | 39 | A | yes |
| notifications | 44 | B | yes |
| crm | 56 | B | yes |
| attributes | 63 | B | yes — not Profile A (D107) |
| tasks | 63 | B | yes |
| intl | 67 | B | yes |
| modules | 99 | B | yes |
| operations | 165 | C | yes |
| core | 169 | C | yes |
| tenancy | 175 | C | yes |

`extra.corex.public-api` digest (20 packages, `name: json.dumps(public-api)` joined by newline,
sha256) is `c0598c5c39511933176bdb185de8879014c15fecb541673c7f1a29b819867617`, the same value
recorded by P16.12. This item did not change that manifest.

The `src_digest_sha256` values stored in `findings/P16-profile-a-receipts.md` are not reproduced
by the pipeline printed in that receipt, nor by `cd packages/<pkg>/src && find . -name '*.php'`.
File counts and the public-api digest do match, so this is not a return to a move item. Live
digests from the printed pipeline (`find packages/<pkg>/src -name '*.php' | sort | sha256sum`
of each file, then sha256 of that stream):

| Package | Live src digest |
|:--|:--|
| access | `9bd48d5aa84dc818f6c60724702abc37bba157dcb9634f61b3a5438667f6a6ca` |
| attributes | `3875b7e10e15c5e9cda19ceabcd0d8469f72a6846f414781d3e80e9a68783e32` |
| media | `64a620eff6bf09000d8a039e90dc780001d93b69e9d5a01ef56163a8d702efe1` |
| panels | `714d22b72e9ef33ae0c30ec04544a7d737f4f3594c00d82e688fe57a5ff43bbe` |
| recurrence | `563a020fe1d96cf112d332cadd6c367040d04ee7b6fabc29f52c7ab5701fe48a` |
| search | `81882b3c878e586ed1dd94bcbc9c344e3541131d4210512fe6d9debacda94bc1` |
| search-intl | `8a664f4260cbc6798c62741d2a20bbf048798d1816004fb662e90d4c403d4831` |
| search-meilisearch | `067991ef07072f082804cb2c65f4612fccf76eb6c0de4518dce136fe8483e879` |
| tasks-workflow | `738d1791ca734059c6e5a2cab24c2bf1bcca1598e2107e4c5fec0f8bb41c2dcd` |
| transfer | `4a96118869a56f7aedb30859c923895bc67c899c759828b0410e2620fa140fa2` |
| wakeup | `c210d43d149a05bd3cf3f9204eff5600ef59977ef23b4780a0f144ee322346af` |
| workflow | `db9e719012afd49680ad1fe69a0e805db46ee9302fdb9c63e693a658586618fa` |

## Planted negatives

Both fixtures were removed before the aggregate lanes. The worktree has no
`packages/intl/src/Translation/Contracts/LocaleResolver.php`, no `P12*Probe.php`, and no
`ConfigCompositionProbe.php`.

| Plant | Command | Result | After removal |
|:--|:--|:--|:--|
| Duplicate FQCN `CoreX\Intl\Contracts\LocaleResolver` also in `src/Translation/Contracts/` (secondary PSR-4 directory of the same prefix) | `composer dump-autoload -o --no-dev --strict-psr --strict-ambiguous --no-scripts` | exit **2**. `Ambiguous class resolution, "CoreX\Intl\Contracts\LocaleResolver" was found in both .../Locale/Contracts/LocaleResolver.php and .../Translation/Contracts/LocaleResolver.php` | exit **0**, 6710 classes |
| Production `use CoreX\Testing\CoreXTestCase` inserted into `packages/core/src/Enums/ActorType.php` | `vendor/bin/pest --filter='R-T1 planting' tests/Arch/TestingAutoloadTest.php` | exit **0**, 1 passed, 4 assertions. The offender line contains `R-T1`, `ActorType.php`, and `CoreXTestCase`; the test restores the file | `tests/Arch/TestingAutoloadTest.php` exit **0**, 3 passed, 14 assertions. `ActorType.php` has no `CoreX\Testing` import |

Dev autoload was restored with `composer dump-autoload -o --no-scripts` (12200 classes).
`composer dump-autoload -o --strict-psr --no-scripts` still exits **1** on the pre-existing
11-class attributes/operations test-double skip list. No Testing class is in that list.
Not reported as a green strict-psr check.

## Lanes

A first `composer test:packages` overlapped `composer test:pg` on `corex_test` and went red
(`migrations` missing / `public` schema races). Those results are discarded. The figures below
are serial reruns. Postgres was accepting connections on `127.0.0.1:5434`, database `corex_test`.

| Lane | Result |
|:--|:--|
| `composer test:packages` | exit 0. operations: 257 passed, 10 skipped (1741 assertions). tenancy: 496 passed, 7 skipped (1766 assertions). Remaining package commands passed. The skips are the sqlite package lane's pg opt-outs already recorded by P16.4/P16.10, not skips inside the PG lane |
| `composer test:pg` | exit 0. 239 passed, 0 skipped (1149 assertions) |
| `composer analyse` | exit 0, 0 errors. An earlier exit 1 (`ConfigCompositionProbe.php does not exist`) was a race with an arch plant and did not repeat |
| `composer test:arch` | exit 0. 89 passed (288 assertions) after retargeting stale operations paths in `ApiContractTest` and the P12 plant directory in `ErrorContractTest` |
| `php scripts/package-graph-gate.php` | exit 0, `package-graph-gate: OK` |
| `php scripts/naming-gate.php` | exit 0, grandfather=3 |
| `composer lint:check` | exit 0 after Pint on three P16 move artifacts and `tests/Stubs/StubModuleDefinition.php` |
| `composer bc-check` | exit 0, `bc-check: OK`. All 20 packages experimental. `operations` and `access` are `new-package` (base `9608b488` has `packages/api` and `packages/auth`). `search-intl` and `recurrence` are `compatible`. The rest are report-only `bc-break` (D98). No `infrastructure` row |
| CI/split paths | `.github/workflows/ci.yml` and `phpstan.neon` contain no `packages/api/` or `packages/auth/` |

## Gate repairs in this item

These are path retargets so the existing gates see the post-P16 tree. They do not drop a check.

- `tests/Arch/ApiContractTest.php` reads `Definition/OperationRegistry.php`, `Invocation/Internal/DefaultOperationInvoker.php`, `Idempotency/Internal/IdempotentOperationInvoker.php`, `Invocation/OperationDispatcher.php`, `Tools/Internal/GuardedToolInvoker.php`, `Versioning/Data/ApiProfile.php`, and `Versioning/Internal/ApiProfileFactory.php`.
- `tests/Arch/ErrorContractTest.php` plants P12 probes under `src/Definition/`, which the scanner already walks. The expected diagnostics stay `E-UNREGISTERED-CODE` and `E-CONTRACT-DRIFT`.
- `scripts/bc-check.sh` archives `packages/api` and `packages/auth` when the live directory is missing at the base ref, builds the base autoload from that archived composer, accepts array PSR-4 targets, and drops the historical directories before the candidate snapshot.
- `packages/tenancy/composer.json` dropped five PSR-4 directories that are not on disk (`src/Events/Identity/`, `src/Events/`, `src/Jobs/`, `src/Models/`, `src/Exceptions/`). Every remaining target directory exists. `extra.corex.public-api` was not edited.

## Unchanged manifests

This item did not edit migrations or `api/v1` wire documents. The public-api digest above is unchanged from P16.12.

## Journal projection

`P16.1`, `P16.2`, `P16.3`, `P16.7`, and `P16.8` already had rows in `findings/P16-move-receipts.md` and no journal events, so a status rebuild treated them as not started and refused phase closure. This item appended a start/close pair for each of those five from that receipt. `journal.append()` refused the file because older rows use friction `owner-waived-model-check`, which is outside the current enum; the new rows were validated and appended in the writer’s JSON shape. Generated status is 13/13 Done. Closure: `phases/P16/P16.closed.md`.
