# P10.4 execution

Actor: executor/codex/gpt-6.1-sol/high (provider-attested route). Owner scope: P10.4 only.
Base: c5e5a94698424cbff852252a601202e8e7ef2906. Candidates: f204341e, d4f8dc07, c35a46fe; final product candidate 4522e2b3f1ac741518387d24d1dd83248e97cc34.

## Changes

Two existing webhooks PostgreSQL methods now have method-level Group('pg'); tests remain in their original suites. Webhooks coverage is SQLite-only; root PG executes the two methods. Tasks coverage measures both lanes, and its job supplies PG connection settings. Panels now has a PostgreSQL 18 service, SQLite step and both-lane coverage with the service port.

Tools tests cover offline schema admission/validation and nested error pointers. Webhooks tests cover deterministic major-filtered contract snapshots and invalid configuration diagnostics. Tasks tests cover validation failures with no partial write, update no-ops, nullable/date fields, tenant-local relations, hierarchy depth, sprint replay and malformed planning tokens. Panels tests cover optimistic revisions, default replacement and deletion.

Laravel 12 webhooks analysis exposed a stale whereRaw baseline entry. A stable retained_inbox SQL alias removes runtime identifiers from raw SQL while retaining the PostgreSQL UUID-to-text comparison. SQLite/PG retention tests pass; the obsolete baseline entry is removed. No new baseline or allowlist entries, floor reductions or public-signature changes.

The package aggregator initially stopped at media's missing standalone autoload. Its job now installs and audits every standalone harness consumed by the existing aggregator. The aggregator explicitly excludes group pg; dedicated PG jobs remain present. Tenancy's base TestCase opens PostgreSQL for every Feature test, including files without inline driver configuration; its entire Feature and Pg directories now belong to group pg. The standalone hermetic tenancy lane passes 83 tests with port 1, while both dedicated PG18 jobs still execute all Feature/Pg tests.

Root coverage executes the same complete suite in three processes: main, TransferPackage and TasksWorkflowPackage. These two suites exercise absolute process-memory limits; their budgets must not inherit the memory occupied by unrelated instrumented suites. PHPUnit XML reports supply each file’s full executable-line total and covered line numbers; the merger unions covered lines, verifies per-lane totals and identical source inventories/hashes, and retains the largest executable-line denominator per file, including uncovered files; no line or suite is removed, and the root floor remains 80. Local isolated suites pass 31/107 and 21/143. Native and XML transfer accounting both give 561/14522; synthetic overlapping lanes give 17/20 (85%), and controls reject a below-floor union, source-hash drift and a requested floor of 79; differing static/driver denominators retain the larger total. No PHPUnit-internal API or new suppression is used. The root PHPStan entry passes after the reporter change.

## Validation and economy

Owner D84 waives reviews and duplicate local replay/installations. `ci-local.sh` was not executed. Broad PG/package/Arch/Golden checks are supplied by the final hosted CI jobs, not claimed as local runs.

- Tools: 75.9% → 82.8%; webhooks: 75.9% → 81.9%; tasks: 82.1% SQLite historical / 83.5% both-lane baseline → 93.3%. Panels: 72.4% hosted → 86.5%.
- test:hermetic: 926 + 14 passed; PG host 127.0.0.1 port 1, corex_test. No shared service stopped.
- Two newly grouped PG methods: 2 passed / 6 assertions against corex_test at 127.0.0.1:5434. Target DB existence and Docker health verified before writes.
- Mutation registry: 35/35; all original 34 retained. New mutation removes Group('pg'), reproduces connection refusal at port 1 and restores original bytes.
- Lint and tests-lint pass. Initial all-package analysis overlapped an architecture probe and observed a disappearing StaleMembershipProbe.php; after test completion the exact full analyse command passes (analyse-final.log). Later retention delta: webhooks analyse and tests-lint pass.
- Retention delta: SQLite/PG 6 passed / 41 assertions.
- Quality ceilings OK; anti-weakening OK. Coverage floors unchanged; no removed tests and no added skip calls.
- PG inventory: pg-inventory.txt. Base TestCase files define dormant PG connections; concrete PG tests are grouped through class/method attributes or Pest Pg mappings. ApiProfileTest's driver string is a configuration-value fixture and opens no PG connection. Root PG runs operations/webhooks/core/modules/other included package tests; package PG jobs run access/media/notifications/tasks/tenancy; existing dedicated aggregators run the remaining PG suites.

## Hosted CI

First candidate CI 36917699023 exposed panels coverage, missing media vendor and the Laravel 12 unmatched baseline. Repaired together in d4f8dc07. CI 36918692967 fixes panels and both webhooks lanes; aggregator reaches recurrence and identifies another standalone bootstrap requirement. Final fix includes recurrence installation and explicit group exclusion in the separate core/modules SQLite step. Local `composer test:packages` passes all 21 packages/product harnesses; CI 36919395670 revealed the ungrouped tenancy Feature/Pg dependency and was cancelled once that failure and the root coverage memory failure were established. Both fixes are in 26fc080d. CI 36921016213 then passed complete root coverage (12573/14519 = 86.6%) and all four root matrix lanes; its remaining failures were duplicate nested core-suite registration and PHPStan errors from the native merger’s internal PHPUnit API. c8fc74a1 removes the duplicate nested path; its redundant intermediate CI was cancelled. cd007298 replaces internal API calls with portable XML merging. CI 36922385810 passed 35 jobs, skipped the event-gated mutation job, and failed only the new XML denominator-equality guard: PHPUnit static seeding and Xdebug disagree for unloaded/loaded files. 4522e2b3 conserves the larger per-file denominator (a conservative estimate) and still rejects changed source inventory/hashes. Local Xdebug transfer/tasks-workflow both pass; mixed PCOV/Xdebug reports and synthetic denominator controls preserve all files and reject below-floor coverage. Final hosted CI [36923853013](https://github.com/axiomasoft/corex/actions/runs/36923853013) on `4522e2b3f1ac741518387d24d1dd83248e97cc34`: success, 36 successful jobs, zero failures, only the event-gated Mutation registry skipped. Root conservative merged coverage 86.6% (12573/14522), floor 80. The skipped hosted mutation job is backed by all 35 local mutation controls. A local broad coverage diagnostic was red due to unavailable Meilisearch and mixed standalone/root brick/math autoload versions, so it is not acceptance evidence. Hosted coverage installs only root dependencies and supplies the Meilisearch service.

## State

P10.4 and P10 accepted GREEN on the final product candidate. Plan states, closure and next bundle are generated by the compatible Task owners. Review/audit: not executed, owner waiver D84. ci-local.sh and final duplicate broad local replay: not executed, D84 hosted substitutions. Recommended B05-A (P05.1 → P05.2) is not executed; no successor authorization inferred.
