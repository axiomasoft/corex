# P16.10 — Dev-only Testing boundary

**Batch:** E16e · **Decisions:** D33 closed, D94 applied · **Actor:** plan-exec / direct (owner waived model check)

Shared Testbench harnesses left production autoload. Each affected package suite boots from its own `tests/` tree. `autoload-dev` of a dependency is not used.

## Carriers removed

| Former production class | Now |
|:--|:--|
| `CoreX\Testing\CoreXTestCase`, `IsolatesDatabasePath` | `packages/core/tests/Support/` via core and root `autoload-dev` only |
| `CoreX\Auth\Testing\CoreAuthTestCase` | `CoreX\Auth\Tests\Support\CoreAuthTestCase` |
| `CoreX\Tasks\Testing\TasksTestCase` | `CoreX\Tasks\Tests\Support\TasksTestCase` (no `CoreX\Testing` parent) |
| `Flex\Crm\Testing\CrmTestCase` | `Flex\Crm\Tests\Support\CrmTestCase` (no `CoreX\Testing` parent) |

intl, search-intl, tenancy, and tasks-workflow each have a local `tests/Support` harness. They do not import another package's test class. `CoreX\Modules\Testing\ExtensionContract` stays a production helper (P11), not a Testbench base.

D33 staging comment and `RT1_EXISTING` allowlist are gone. `tests/Arch/TestingAutoloadTest.php` now fails if production `src` imports `CoreX\Testing`, `CoreX\Auth\Testing`, `CoreX\Tasks\Testing`, or `Flex\Crm\Testing`, and if a package test outside core/root imports `CoreX\Testing`.

## Positive / negative sweep

| Check | Result |
|:--|:--|
| `packages/*/src` harness namespaces above | empty (only `packages/modules/src/Testing/ExtensionContract.php` remains) |
| `use CoreX\Testing\` outside `packages/core/tests` and root `tests/` | empty |
| production `autoload.psr-4` keys/paths containing `Testing` | empty |
| root classmap `CoreX\Testing\CoreXTestCase` | `packages/core/tests/Support/CoreXTestCase.php` |
| `CoreX\Auth\Testing`, `CoreX\Tasks\Testing`, `Flex\Crm\Testing` in root classmap | absent |
| Arch `TestingAutoload` | 3 passed, 14 assertions |
| `packages/core` `composer validate --strict --no-check-publish` | valid |
| `composer dump-autoload -o --strict-psr` | exit 1 on the pre-existing attributes/operations test-double skip list; no Testing class in that list |

## Package tests

| Command | Result |
|:--|:--|
| `packages/core` test | 139 passed (387 assertions), includes publish-isolation DB guard |
| `packages/access` test | 38 passed (65 assertions) |
| `packages/tasks` test | 33 passed (106 assertions) |
| `packages/crm` test | 34 passed (135 assertions) |
| `packages/intl` test | 33 passed (96 assertions) |
| `packages/search-intl` test | 6 passed (14 assertions) |
| `packages/tasks-workflow` test | 17 passed (128 assertions) |
| `packages/tenancy` test | 496 passed, 7 skipped (1766 assertions) |
| root `composer test:packages` | exit 0 (operations through workflow) |

Package vendors that still mapped `CoreX\Modules\` only to `src/` were refreshed with `composer update corex/modules` so the P16.7 multi-path prefix is what those suites load. `packages/access` was left on its lock: `axioma-studio/azguard-core` is not in the remote set, and its own test command was already green.

`scripts/package-graph-gate.json` dropped the seven public-api exceptions whose files were the deleted `src/Testing` carriers, and retargeted six exceptions onto the post-P16.1/P16.8 paths (`RateImporter`, `SqlRateImporter`, `ConvertLead`, `CrmIdentifier`, `DealAmount`, `ContextGuard`). `package-graph-gate` then exits 0. Undeclared-surface snapshot no longer lists the five removed harness FQCNs.
