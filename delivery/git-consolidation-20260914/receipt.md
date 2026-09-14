# Local branch consolidation — 2026-09-14

Owner request: consolidate local branches into one `main`, clean obsolete branches.
Workspace: `/home/vostrikov/projects/packages/corex`. SOLO, Task Dev.

## Result

13 local branches → 1 (`main`); 2 worktrees → 1 (this workspace).
All 12 removed branch tips passed `git merge-base --is-ancestor <branch> main`
before deletion. Cached `origin/main` also merged; no fetch/push/remote deletion.
Tags and remote-tracking refs unchanged. No production/DB/application publication.

Main consolidation commit before housekeeping:
`948463e802bc9992e55920f4345b43818886ecd9`.
Committed tree after branch merges:
`ec7375b0fca2c1e00a4b74654c89bf91c9eb3968` — exactly the original CRM integration
tree at `550c1b33f117b0d1640bb4b53a948cc1567c74c6`. Branch merging did not regress code.
CRM subtree remains `c5f4d5d3131626f3d6fa47706b188037f7bfedd8`.

Two equivalent histories (`local-library`, CRM task branch) were normal merges.
Old `feat/corex-tasks-module` was merged with `-s ours`: its early CRM/Tasks rebase
history duplicated the already integrated implementation while referring to removed
TenantService/tenant_id, legacy namespace and root test topology. Current AGENTS.md /
dev-doctrine prohibit restoring row-level tenancy and require per-package tests.
Corresponding canonical Tasks commits: `7abc46b`, `e3f496f`, `306e04e`, `76ee3a4`;
CRM subsequently evolved through `61f0d42` and final parity commits. Old branch
commits remain reachable in main and the backup, without resurrecting obsolete APIs,
DDL or test placement. No broad `ours` strategy used for genuinely new work.

Housekeeping for normal main testing: root Composer path-package version labels
changed only from dev-codex/crm-company-contact-parity to dev-main (5 lines);
no third-party lock updates. Root lock SHA-256:
`0e684efe63f66caa57b1cd182a6d2b38e3891e59114539c27cf35b46178f12b1`.
PHPStan tmpDir set to project-local `build/phpstan` to eliminate a shared cache
containing PHAR paths into the removed worktree; canonical `composer analyse` passes.

## Preserved working changes / recovery

Existing dirty core/modules/tenancy/tooling/plans changes were temporarily stashed,
restored to main, and NOT committed with our work. Before/after Git binary diff SHA-256:
`b1986535311fcd8daff40e83b59dc4304d0c9a703436d4999140f74e9d715542`.
Post-housekeeping comparison excludes our composer.lock/phpstan.neon/delivery files.
Untracked filename-list checksum before and immediately after restoration:
`6d6f19145019c7e15edc59f3651e2a458fa45b30c5bbff76f54f8c1934afc815`.
Untracked file content blobs also compared with temporary stash's third-parent tree:
all match, except our own README pointer deliberately updated to the new canonical path.

Temporary stash `09582a00c6d390c8e93329c60d79cda0c518042a` dropped only after successful
restoration/checksum verification; preserved in the working backup bundle.
Three pre-existing stashes retained exactly, not applied or discarded:

- `6532e86cc76627af6545bbd057a14b5164ea961d` P1R-P1.20-pointfix-uncommitted
- `8edfbac2220f32d1f55b8ebbaa39cee7a68a4599` P1R-P1.14-partial
- `03867e6449e0ea592eea020fac5a5aee605d23af` WIP corex-auth

Backup bundles (local .git, no publication):

- `/home/vostrikov/projects/packages/corex/.git/branch-cleanup-20260914.bundle`:
  complete pre-consolidation refs/history, verified with git bundle verify;
  SHA-256 `81a4a2dbf21a4d4a5dc90fed87a3036b1c8dbc99b2af2169568c128669ff9a98`.
- `/home/vostrikov/projects/packages/corex/.git/branch-cleanup-working-20260914.bundle`:
  additionally contains temporary dirty snapshot;
  SHA-256 `4ec9211414f55c115df636190b267c0846cb8cb9f8c83b9f95c109198fcda8a9`.

Recovery example (only if wanted):
`git branch recovered-tasks-module 6cb9d93f91c901d194466851153ab12b15c9d6e6`.
All old commits are still reachable in main; bundle is an extra recovery source.
For external recovery: `git clone <absolute-bundle-path> <new-empty-directory>`.
Restore a dirty snapshot deliberately with git stash apply SHA; never blindly apply
obsolete stashes over current code.

Removed extra worktree:
`/home/vostrikov/projects/packages/corex-crm-parity-20260914`.
Its committed code/logs/archives/receipt are now in main. Its ignored, generated vendor/
build/cache copies were removed with the clean worktree; dependencies are reinstallable,
not unique user source data. Canonical CRM handoff now:
[/home/vostrikov/projects/packages/corex/delivery/crm-parity-20260914/receipt.md](/home/vostrikov/projects/packages/corex/delivery/crm-parity-20260914/receipt.md).

## Original local branch refs

| Removed local branch (main retained) | Original tip |
| --- | --- |
| codex/crm-company-contact-parity | `f4565e1f9a75bf774dbc40d74ac854bb0dc7644d` |
| feat/corex-auth | `f2390c454f422197f7dd78f667aed090b7fdf61c` |
| feat/corex-core-scaffold | `18f43fc94567059a2a162a417b47de9261a6f64c` |
| feat/corex-crm | `1288de140168ae7544e962bef96aab49559887b9` |
| feat/corex-crm-domain | `76ee3a4b8b5a775c073675acc921c00c3a5cc958` |
| feat/corex-id-strategy | `e0c1cbc5a44af47346d8ca72790eb64e4d66c6cc` |
| feat/corex-module-docs | `d3c17c9f60a5137d48cc44df33e13e83d10a089a` |
| feat/corex-tasks | `76ee3a4b8b5a775c073675acc921c00c3a5cc958` |
| feat/corex-tasks-module | `6cb9d93f91c901d194466851153ab12b15c9d6e6` |
| fix/module-registry-compilation | `550c1b33f117b0d1640bb4b53a948cc1567c74c6` |
| local-library | `b05f7d62cb9690ba6115c3b13a01677e1c2e0763` |
| p1r-remediation | `27d44e49dfe257d2898a2e9cb7a4519c7a52512e` |
| main (retained, updated) | `5e396e7210fc17e1dc0e6917e70bef10b74becab` |

## Main snapshot checks

CWD root unless package specified. Logs:
`/home/vostrikov/projects/packages/corex/delivery/crm-parity-20260914/logs`.
Host PHP; PG only dedicated `corex_crm_parity_test` on127.0.0.1:5434/corex,
Docker corex_pgsql_test healthy PG16.14. Host PDO preflight confirmed actual database;
test harnesses own migrations/reset within this isolated DB. Suites run serially.
Main checks include pre-existing DIRTY remedies: runtime proof, not immutable release proof.

- `composer update corex/core corex/modules corex/tasks corex/tenancy flex/crm --minimal-changes --prefer-dist --no-interaction --no-progress`:
  lock labels only; [main-dependency-refresh.log](../crm-parity-20260914/logs/main-dependency-refresh.log).
  Composer reported10 security advisories for one locked baseline package; no dependency
  upgrade/audit scope claimed. This does not alter Git consolidation acceptance or approve release.
- package CWD `composer update --prefer-dist --no-interaction --no-progress`:
  [main-package-install.log](../crm-parity-20260914/logs/main-package-install.log), no advisories reported.
- `composer lint:check`: PASS [main-lint.log](../crm-parity-20260914/logs/main-lint.log).
- `composer analyse`: PASS0 errors after project-local cache configuration
  [main-analysis-final.log](../crm-parity-20260914/logs/main-analysis-final.log).
  Original stale-cache failure preserved [main-analysis.log](../crm-parity-20260914/logs/main-analysis.log).
- `PGSQL_DATABASE=corex_crm_parity_test /usr/bin/php8.4 vendor/bin/pest packages/core/tests/Feature/DeliveryRemediationTest.php --filter='serializes publishers'`:
  PASS1 test/3 assertions [main-publisher-smoke.log](../crm-parity-20260914/logs/main-publisher-smoke.log).
- `PGSQL_DATABASE=corex_crm_parity_test timeout 90s /usr/bin/php8.4 vendor/bin/pest --group=pg`:
  PASS107 tests/356 assertions [main-pg.log](../crm-parity-20260914/logs/main-pg.log), no timeout.
- package CWD `PATH=/usr/bin:$PATH XDEBUG_MODE=coverage PGSQL_DATABASE=corex_crm_parity_test composer test:coverage -- --coverage-clover=../../delivery/crm-parity-20260914/logs/main-package-clover.xml`:
  PASS64 tests/321 assertions,96.9% floor95
  [main-package-coverage.log](../crm-parity-20260914/logs/main-package-coverage.log).
- `PATH=/usr/bin:$PATH XDEBUG_MODE=coverage PGSQL_DATABASE=corex_crm_parity_test composer test:coverage -- --coverage-clover=delivery/crm-parity-20260914/logs/main-root-clover.xml`:
  PASS217 tests/667 assertions,88.8% floor80 (canonical full suite, no exclusions)
  [main-root-coverage.log](../crm-parity-20260914/logs/main-root-coverage.log).

The earlier immutable artifact dependency NO-GO/full-sales-DDL/release limitations in
CRM receipt remain applicable to that frozen candidate; local main PG GREEN does not
silently qualify an archive excluding the dirty remedies or publish a package.
