# P07.1 — Validation summary (2026-10-04)

Product anchor: `1f42e01d10d21582746b31f89525f59e5ab024ff`; base: `57369e086473d87d4a8ccc308cbd0ce55050a28f` (`base-commit.txt`). Product sources are unchanged during final closure; the dependency manifest and legacy test configuration were repaired after a RED dependency carrier. Final evidence: `final-verification/verification.json` and logs below.

Environment: PHP 8.4.26, PostgreSQL 16 at `127.0.0.1:5432`, isolated `corex_test`. DB readiness/database identity checked; test harnesses rebuild their isolated schema. No ext-redis: package resolution uses `COMPOSER_IGNORE_PLATFORM_REQ=ext-redis`; six existing Redis tests remain skipped. Test order: default. Hosted CI ignored by owner (D106); no new audits/reviewers/subagents.

| Validation carrier | Final result / evidence |
|:--|:--|
| D103 real Roave finite approval + negatives | GREEN: unchanged product API; existing `bc-check-tenancy.log` (actual base `76f8bdde`, exact decision-bound delta). Root hermetic runs BC gate positive/negative tests. |
| AccountStatusGate + HttpRegistrar / dual failure, poison, logger | GREEN in full tenancy suite; `final-verification/tenancy-tests.log` (AccountStatusGateTest, CompositionBindingsTest, TenancyEngineTest). |
| contract-guard default and explicit base-commit | exit 0: `final-verification/contract-guard.log`, `contract-guard-base.log`; also checked after closure commit. |
| Engine / queue / bootstrapper / host / session / revert tests | GREEN in full package run: `final-verification/tenancy-tests.log`. |
| ScopeBoundary / ResidentTenantSwitch PG + mutation | 8 passed, 60 assertions: `final-verification/tenancy-scope-pg.log`; unchanged mutation evidence `mutation-P07-scope-boundary.log` (3 RED tests under sabotage). Host-port override 5432. |
| git grep stancl runtime / tenancy() in src | No matches (exit 1 as required for an empty grep); `final-verification/runtime-scan.log`. |
| Cache isolation, unsupported array, discovery, UUID/provisioning | GREEN: full tenancy run includes TenantCacheIsolationTest, TenancyEngineTest, StanclDiscoveryTest, ProvisioningTest. |
| error-registry / runtime-globals / config-cache-check | exit 0: `final-verification/error-registry.log`, `runtime-globals.log`; ConfigCacheTest GREEN in root hermetic. |
| package / root suites | tenancy **588 passed, 6 skipped**, 2123 assertions; root hermetic **1124 + 17 passed**, 5645 + 79 assertions; root PG **242 passed**, 1156 assertions. Logs: `tenancy-tests.log`, `root-hermetic.log`, `root-pg.log` in `final-verification/`. Owner allows tenancy-only package validation here; the other package suites are not claimed to have been rerun. |
| php-api write idempotence / strict check / BC / Golden delta | Original double-write/idempotence and real BC evidence reused (product API unchanged); strict check rerun exit 0, diagnostics []: `final-verification/php-api.log`; existing `golden-semantic-delta.json` (+17 / −4 / ~8 types, wire +1). |
| lint / analyse / anti-weakening / package-docs | Scoped lint exit 0 (`packages/tenancy packages/core/src scripts`); root lint exit 1 exclusively on 15 foreign-baseline artifacts, all byte-identical to base (`lint-baseline-comparison.json`), left unchanged by explicit owner direction. P07.1 lint delta GREEN; full root exit 0 is not claimed. Analyse all 22 configurations exit 0 + ceilings OK after generated Testbench cache refresh; anti-weakening and package-docs exit 0. |
| Review light | Existing `review: self-check GREEN` in completion reused; no new review. |

## Tail repairs

- Dependency analyzer initially exited 1 after successful staging: 3 shadow dependencies plus one deleted resolver class used only as a legacy config key. Declare ext-ctype, ext-session, symfony/http-foundation; preserve that vendor-owned transitional config key as a string until P07.2, with the test behavior unchanged. Analyzer repeat exit 0, 323 files (`dependency-tenancy-after.log`). No analyzer exceptions or ceilings added.
- Root composer manifest restored after staging; tenancy's authorized dependency declarations preserved. Lock hash computed through `Composer\Package\Locker::getContentHash` from pristine root composer.json, without staging: `206ead12b1f28c451a3c070cc405a6ea`. Lock diff exactly one replaced line; `composer validate --no-check-all --no-check-publish` exit 0.
- Initial analyse bootstrap failure came from stale vendor Testbench generated caches containing stancl provider. Removed only generated cache files; exact composer analyse repeat GREEN (`analyse-after-cache-refresh.log`). Product-code edits: none.

Terminal evidence binds all 12 declared Validation bullets to this summary and their detailed carriers. Closure reuses the open run `88a6e4f216e6b9fe3bd7a6e4e9bef0de3cbff6b00c7ef07f11ef298015cda536`. P07 remains In progress; P07.2–P07.6 remain Not started. Semantic Next and freshness: `final-verification/terminal-receipt.json`.
