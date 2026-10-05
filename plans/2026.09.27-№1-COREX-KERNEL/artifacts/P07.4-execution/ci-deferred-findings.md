# CI findings retained under D109

CI is explicitly outside P07.4 acceptance. No CI GREEN is claimed.

- Grok attempt-0003: private AzGuard root dependency can gate native CI before tests. The independent verdict remains FINDINGS.
- Native CI actual run, `ci-native-acceptance-r2/proof.json`: Arch 123 tests, 108 passed, 3 failures, 12 errors. Optional engine-dependent Golden bootstrap and whole-source reflection require the actual engine; discovery with a symlinked Pest executable was not sufficient proof. Experimental CI isolation was rolled back to the original P07.4 CI version from 9e56afe6.
- Bridge root registrar placement R3 and Blade/Event globals were found by CI architecture. Uncommitted namespace/facade repairs were discarded following the owner exclusion; no public API changes from those experiments are delivered.
- Tenancy `CreateDatabaseFromTemplate` R5 and empty runtime-globals baseline assumptions were reported separately as inherited problems, not repaired in P07.4.
- The alleged null-workspace crash is not reproducible on PHP 8.4 because `??` safely handles the original expression; exact source-bound proof is in `review-null-workspace-original-9e56afe6.log`. Explicit nullsafe access and actual permission-resolver regressions are delivered.
