review:codex/gpt-6-astra FINDINGS:1

Independent reviewer: **codex/gpt-6-astra/high**, native session `01a117f9-85b5-7eb1-ba90-8f4a0eda7465`, provider `openai`. Identity observed directly from `session_meta` and `turn_context` in `/home/vostrikov/.codex/sessions/2026/10/07/rollout-2026-10-07T23-06-31-01a117f9-85b5-7eb1-ba90-8f4a0eda7465.jsonl`. This reviewer is distinct from the repair executor.

Reviewed candidate **`e2dc908194cea0a13108b88d5b02242d2684a763`**, against `1bfcb0324197c9e3ae8a050dea2b8f1d84b7f6dc`, excluding plans. The repair diff changes 16 files; no maintained production PHP, runtime requirements, production autoload configuration, public contracts, or schema changed.

**F3 — Blocker: mandatory Rector gate remains red.** Exact-candidate [CI run 37672026959](https://github.com/axiomik/corex-mirror/actions/runs/37672026959) completed **failure**, with **29 ordinary jobs successful, one failed, and three intentional D133 skips**. Static-analysis job `112965922624` failed its `Rector` step: `composer refactor:check` reported 12 files requiring changes and exited 2. This is a demonstrated acceptance failure; it does not establish a production behavior defect.

Evidence: `artifacts/P09-repair/hosted/37672026959/job-112965922624.log`, lines 1642–2218, relative to the active plan. The 12 reported paths are:

- `packages/access/tests/Feature/ScopeVersionConcurrencyTest.php:32`
- `packages/core/src/Messaging/Internal/DefaultWorkerRuntime.php:4`
- `packages/core/tests/Feature/ReleaseContextLifecycleTest.php:21`
- `packages/core/tests/Unit/ContextStorageDefaultTest.php:23`
- `packages/core/tests/Unit/SchemaStatusContractTest.php:59`
- `packages/tenancy/tests/Feature/StorageContextConformanceTest.php:230`
- `packages/tenancy/tests/Pg/ClaimLifecycleConcurrencyTest.php:110`
- `packages/webhooks/tests/Unit/WebhookInputBoundaryTest.php:106`
- `scripts/quality/infection-pest-adapter/src/DefaultObjectArgument.php:4`
- `scripts/quality/infection-pest-adapter/src/NonNullReturn.php:4`
- `scripts/quality/infection-pest-adapter/src/NullReturn.php:4`
- `scripts/quality/mutation-ast.php:2`

Examples include `NewMethodCallWithoutParenthesesRector`, explicit PhpParser imports, and `StringClassNameToClassConstantRector` converting the Octane binding string in `DefaultWorkerRuntime` to `Octane::class`. The owning repair should reconcile these concrete differences while preserving optional Octane behavior, package dependency boundaries, existing gates, and D133’s prohibition on mutation execution. This reviewer performed no repair.

**Prior findings resolved:**

- **F1:** Each affected PostgreSQL and Meilisearch test file now declares its own TestCase and group. Redundant package registrations were removed. Standard root/package PG receipts show Transfer **24 tests/118 assertions** and Search **7/28**; real Meilisearch root/package receipts show **3/18** each. Five retained JUnits were independently parsed and hashed: no failures, errors, or skips. Closed-port hermetic receipts pass. Final hosted PHP 8.4/8.5, PostgreSQL 16/18, coverage, and package-aggregator jobs independently confirm repaired integration.
- **F2:** New CRM tests exercise stored-null versus missing fields, native integer limits and invalid representations, accepted/rejected text, map/list validation, and strict booleans. The `src` denominator and **95%** coverage floor remain unchanged. Exact-final hosted CRM reports **92 tests/433 assertions**, **Value 100%**, and **overall 95.8%**.

Other bounded checks passed:

- Direct PHPUnit dev dependencies are declared for Meilisearch and Access; Core’s Octane/Monolog dependencies and lifecycle fixture classmap remain development-only. The final standalone dependency/audit job succeeded.
- All three mutation jobs use literal `if: false`, remain skipped, and introduce no suppression. Workflow security passed.
- Explicit CI audit boundaries unset `COMPOSER_NO_AUDIT`; `audit-roots.py` remains unchanged and fail-closed. **Final candidate source audit passed all 25 roots with no errors**, at static-job log line 1579. This supersedes the earlier local audit’s stale bindings for four manifests.
- PHPStan changes only add `SourceSnapshot.php` to `scanFiles`; levels, exclusions, suppression counts, and quality floors remain unchanged. Final tests-lint passed.
- Final merged root coverage is **87.1% (13,311/15,277), minimum 80%**. Both dedicated Octane lifecycle jobs passed four handler cases. BC passed under the existing decision-bound unpublished baseline policy.

**Nonblocking evidence limitation:** The hosted direct Core-directory run reports **362 passed plus four warning cases, 1,363 assertions**, with job success. The warnings are suppressed vendor reads of the optional Octane fixture `.env`; lifecycle assertions executed. This must not be described as a warning-free hosted run. The separate local receipt reports 366 passed.

**Integrity verified before and after review:**

- HEAD stayed `e2dc908194cea0a13108b88d5b02242d2684a763`.
- All **3,452 manifest-listed product files** retained identical hashes; product Git status was clean.
- Six additional tracked directory symlink targets independently matched candidate Git blobs.
- `scripts/.plan-close-deferred` was absent.
- Foreign P09.9 proposal artifacts and generated views were untouched.

Fingerprints:

| Evidence | SHA-256 |
|---|---|
| Product manifest | `966b128240bb0cffabf2c5ca953ad26e52e9e149de5cc4a32646b6919ce6993d` |
| Product diff, excluding plans | `da20b87854e6fe2ca5669c88355511514edc648c155c73eb15b1683ed8140b81` |
| Symlink annex | `606e7a1d1cfb0e438a52452a8591077125677d18d733c1705ec7b08ce588cb2c` |
| Failed static-job raw log | `9ff1b6f0f2e52ef5f8e48c55e1d9cb6b778d6ab6eb06900e8bc87e37da7c3eea` |
| Final `run.json` | `458c8ec8f6cc48c982246764a5341474981576395ee99a101859b303c9866145` |
| Final `jobs.json` | `4e80d8761d374ba1c9a469081301a94e74700dad4166d70263a7c738a6151a92` |

The review was strictly read-only: no file edits, tests, builds, installers, PHP/Composer execution, mutation tooling, commits, or publication. D125 exclusions and P09.12’s D133 deviation remain intact; no release qualification or MSI achievement is claimed. **P09.8 cannot receive GREEN on this candidate while F3 remains unresolved.**
