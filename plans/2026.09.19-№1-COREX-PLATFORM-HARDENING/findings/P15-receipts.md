# P15 receipts

## P15.1 — Modules vocabulary and ModuleDefinition (2026-09-26)

**Status:** product green, uncommitted · **Actor:** plan-exec / grok-4.7/high · **Batch:** E15a

Clean rename, no `class_alias`. Composer module ids and `corex:modules:why` stay. Compiled dependency keys moved with the properties: `type`/`target` → `kind`/`package`.

| Old | New |
|:--|:--|
| `Dep` | `ModuleDependency` (`$kind`, `$package`) |
| `Manifest` | `ModuleManifest` |
| `GateDecision` | `ModuleActivationDecision` |
| `SettingDefault` | `DefaultSettingDefinition` |
| `ModuleContext` | `ModuleRuntimeContext` |
| `CorexModule` | `ModuleDefinition` |
| `WhyCommand` | `ExplainModuleCommand` |

### Nine discovery entries

`vendor/composer/installed.json` packages with `extra.corex.module`, each `extends ModuleDefinition` and `manifest()->composerName` matches the package name:

| Package | Class |
|:--|:--|
| `corex/intl` | `CoreX\Intl\IntlModule` |
| `corex/media` | `CoreX\Media\MediaModule` |
| `corex/notifications` | `CoreX\Notifications\NotificationsModule` |
| `corex/panels` | `CoreX\Panels\PanelsModule` |
| `corex/tasks` | `CoreX\Tasks\TasksModule` |
| `corex/tasks-workflow` | `CoreX\TasksWorkflow\TaskWorkflowIntegration` |
| `corex/wakeup` | `CoreX\Wakeup\WakeupModule` |
| `corex/workflow` | `CoreX\Workflow\WorkflowModule` |
| `flex/crm` | `Flex\Crm\CrmModule` |

`corex/attributes`, `corex/search`, `corex/search-intl`, `corex/search-meilisearch`, and `corex/transfer` stay `type=corex-module` without a module class. Discovery skips them.

### Validation

| Carrier | Evidence |
|:--|:--|
| `composer --working-dir=packages/modules lint:check` | Pint passed after formatting `ModuleDiscovery.php` and three modules tests |
| `composer --working-dir=packages/modules analyse` | PHPStan 0 |
| `composer --working-dir=packages/modules test` | 83 passed, 328 assertions |
| Root tests on the renamed types | 60 passed, 215 assertions (`ModuleDiscovery`, `RegistryCompiler`, `Settings`, `OperationContract`, `ModuleRegistry`, `FluentCanon`) |
| `composer --working-dir=packages/api test` | 257 passed, 10 skipped, 1741 assertions; `corex:modules:why` covered by `RegistryIntrospectionTest` |
| `php scripts/package-graph-gate.php` | OK |
| Old PHP symbol sweep | empty outside approved history (HEAD `a3cf8ea`, uncommitted rename) |
| Migration diff | `git diff --name-only -- 'packages/*/database/migrations'` empty |

`composer dump-autoload --strict-psr` exits before a strict check unless `--optimize` is also set. `composer dump-autoload --optimize --strict-psr` then exits 1 on pre-existing non-PSR test doubles in `packages/attributes/tests` and `packages/api/tests`. None of the seven renamed classes appear in that skip list, and `class_exists` is true for all seven.

Approved leftover carriers: historical `CHANGELOG.md` bullets, `docs/architecture/rename-registry.md`, `scripts/naming-gate.php` token `Dep`, and plan artifacts.

Grant record: `decisions/D130-g-api-p15-grant-p151.md`, owner text «разрешаю делать на grok, делай». Item commit was not created: the checkout already mixed unrelated edits into paths this rename also touches.

## P15.2–P15.6 — Bounded package renames (batch E15b, 2026-09-26)

**Status:** product green, uncommitted · **Actor:** plan-exec / composer-2.5 · **Batch:** E15b

Clean rename per D85; no `class_alias`. `CHANGELOG.md` `[Unreleased]` records all five lanes.

### P15.2 — attributes

| Old | New |
|:--|:--|
| `RegistryFieldCatalogue` | `ModuleFieldCatalogue` |
| `StructuralAttributeFilter` | `SchemaAttributeFilter` |
| `WriteResult` | `AttributeWriteResult` |
| `OperationKey` | `SchemaOperationId` |

### P15.3 — search

| Old | New |
|:--|:--|
| `UnsupportedSearchProvider` | `UnavailableSearchProvider` |

### P15.4 — search-meilisearch

| Old | New |
|:--|:--|
| `MeilisearchQueryAdapter` | `MeilisearchSearchProvider` |

### P15.5 — transfer

| Old | New |
|:--|:--|
| `DatabaseTransferRunner` | `SqlTransferExecutor` |
| `ImportTarget` | `TransferSink` |

### P15.6 — tasks-workflow

| Old | New |
|:--|:--|
| `CompletionCaptureObserver` | `CaptureTaskCompletion` |
| `TasksWorkflowModule` | `TaskWorkflowIntegration` |

Composer module id `corex/tasks-workflow` unchanged; `extra.corex.module` points at `TaskWorkflowIntegration`.

### Validation

| Carrier | Evidence |
|:--|:--|
| `composer --working-dir=packages/attributes` lint/analyse/test | 70 passed; architecture gate OK |
| `composer --working-dir=packages/search` lint/analyse/test | 10 passed |
| `composer --working-dir=packages/search-meilisearch` lint/analyse/test | 3 passed (meilisearch group excluded) |
| `composer --working-dir=packages/transfer` lint/analyse/test | 8 passed |
| `composer --working-dir=packages/tasks-workflow` lint/analyse/test | 17 passed |
| `php scripts/package-graph-gate.php` | OK |
| Old PHP symbol sweep (`packages`, `scripts`, `docs/architecture`, `tests`) | empty except `docs/architecture/rename-registry.md` (P15.13) |
| `scripts/package-graph-undeclared-surface.json` | `SchemaOperationId`, `AttributeWriteResult`, `TaskWorkflowIntegration` |

Item commits withheld: dirty worktree overlaps predecessor edits (same as P15.1).

## P15.7–P15.8 — Wakeup contracts and API classes (batch E15c, 2026-09-26)

**Status:** product green, uncommitted · **Actor:** plan-run / grok-4.7/high · **Batch:** E15c

Clean rename per D85/D87. No `class_alias`, no package identity change. `WakeupScheduler` kept. Routes, config keys, operation ids, and OpenAPI/error wire stay.

### P15.7 — wakeup

| Old | New |
|:--|:--|
| `DueSource` | `DueWakeupSource` |
| `WakeupHandler` | `DueWakeupHandler` |
| `WakeupHint` | `WakeupScheduleHint` |
| `WakeupSweeper` | `DueWakeupSweeper` |

Preserved names: `WakeupScheduler`, `NotificationDigestDueSource`, `MaterializationWakeupHandler`, `WakeupHandlerRegistry`, `WakeupHandlerDefinition`, `RootRegistryWakeupHint`, `WakeupRoutingSweeper`.

### P15.8 — api

| Old | New |
|:--|:--|
| `Dispatcher` | `OperationDispatcher` |
| `ErrorBoundary` | `OperationErrorBoundary` |
| `RequestMetadata` | `InvocationMetadata` |
| `OpenApi` | `OpenApiDocumentGenerator` |

Illuminate `Dispatcher` in tenancy/modules was not touched. `scripts/mutations/API-undeclared-input.patch` is reanchored to `packages/api/src/OperationDispatcher.php` (`@@ -110,6 +110,6 @@`); `git apply --check` passed.

### Validation

| Carrier | Evidence |
|:--|:--|
| `composer --working-dir=packages/wakeup lint:check` / `analyse` | Pint passed; PHPStan 0 |
| `composer --working-dir=packages/wakeup test` | 21 passed, 91 assertions (`--exclude-group=pg`) |
| wakeup `--group=pg` | 2 passed, 5 assertions |
| `composer --working-dir=packages/api lint:check` / `analyse` / `test` | PHPStan 0; 257 passed, 10 skipped (Postgres), 1741 assertions. `ContractSnapshotTest` reports generation byte-stable |
| `composer --working-dir=packages/notifications test` | 19 passed, 73 assertions |
| `composer --working-dir=packages/tasks-workflow test` | 17 passed, 128 assertions (includes Postgres materialization) |
| `composer --working-dir=packages/tenancy test -- --filter=WakeupRoutingTest` | 4 passed, 20 assertions |
| `composer --working-dir=packages/crm test -- --filter=ApiOperationsTest` | 2 passed, 22 assertions |
| Root `PackageGraphTest`, `ApiContractTest`, `OperationContractTest` | 19 passed, 89 assertions |
| `php scripts/package-graph-gate.php` | OK; undeclared surface uses `OperationDispatcher`, `OperationErrorBoundary`, `OpenApiDocumentGenerator`, `InvocationMetadata` |
| Old PHP class/interface declarations | empty under the repo; no `class_alias` |
| Consumer PHPStan | notifications, tenancy, tasks-workflow, crm: 0 errors |

Grant records: `decisions/D131-g-api-p15-grant-p157.md`, `decisions/D132-g-api-p15-grant-p158.md`, owner text «делай». The shared gate `G-API-P15` now has four grant rows (D130–D133), so the single-grant resolver stays fail-closed; execution followed the same owner override as P15.1. Journal rows 368–372 now carry the E15b session `40a01162-02ce-40d3-afa7-869e5eea841b` and derived `run_id`. Item states P15.2–P15.8 are generated 🟢 Done. Commit withheld: dirty worktree overlaps predecessor edits.

## P15.9 — Tenancy available accounts result (batch E15d, 2026-09-26)

**Status:** product green, uncommitted · **Actor:** plan-exec / composer-2.5 · **Batch:** E15d

Clean rename per D85. No `class_alias`. Constructor fields, Redis/cache encode-decode shape, and central wire parsing unchanged.

| Old | New |
|:--|:--|
| `AccountsResult` | `AvailableAccounts` |

### Validation

| Carrier | Evidence |
|:--|:--|
| `composer --working-dir=packages/tenancy lint:check` / `analyse` | Pint passed; PHPStan 0 |
| `composer --working-dir=packages/tenancy test -- --filter='AccountSwitcher\|RedisAccountMenu'` | 6 passed, 3 skipped (Redis pg group), 27 assertions |
| `php scripts/package-graph-gate.php` | OK |
| Old PHP symbol sweep (`packages`, `scripts`, `tests`) | empty except `docs/architecture/rename-registry.md` (P15.13) |
| `composer dump-autoload --optimize --strict-psr` | no `AvailableAccounts` / `AccountsResult` in skip list |

Item commit withheld: dirty worktree overlaps predecessor edits (same as P15.1–P15.8).

## P15.10 — Core public FQCN set D88 (batch E15e, 2026-09-26)

**Status:** product green, uncommitted · **Actor:** plan-exec / grok-4.7 / high · **Batch:** E15e

Clean rename per D85 and D88. No `class_alias`, no behavior change, no table or morph change. Grant: `decisions/D133-g-api-p15-grant-p1510.md`, owner text «это и так grok!!!! какого хера не делаешь?» in `brief/owner-invocation-p1510-grok.md`. The shared gate `G-API-P15` now has four grant rows (D130–D133), so the single-grant resolver stays fail-closed; execution followed the same owner override as P15.1.

| Old | New |
|:--|:--|
| `CoreX\PubSub\*` | `CoreX\Messaging\*` |
| `CoreX\Contracts\{Auditable,AuditEntry,AuditLogger,AuditRetention}` | `CoreX\Audit\Contracts\*` |
| `CoreX\Models\AuditLog` | `CoreX\Audit\Internal\AuditLog` |
| `CoreX\Views\*` | `CoreX\SavedViews\*` |
| `CoreX\Data\DataScopeAccessDecision` | `CoreX\Support\Data\DataScopeAccessDecision` |

`CoreX\Contracts\PubSub` and `CoreX\Messaging\Contracts` / `Data` stay. View contracts stay in `CoreX\Contracts`. Tenancy impersonation exemption is the class-string `CoreX\Audit\Internal\AuditLog` (no `use` of Internal). Public-api gained `CoreX\Audit\Contracts` and `CoreX\Support\Data\DataScopeAccessDecision`.

### Validation

| Carrier | Evidence |
|:--|:--|
| `composer --working-dir=packages/core lint:check` / `analyse` | Pint passed; PHPStan 0 |
| `composer --working-dir=packages/core test` | 139 passed, 387 assertions (`--exclude-group=pg`) |
| Root `CoreZoneTest`, `CoreLitmusTest`, `AuditLogTest`, `PubSubTest`, attributes write guard | 30 passed, 112 assertions |
| API retention/replay, panels view schema, saved-view repository | 17 passed, then repository retest 3 passed after Pint |
| `php scripts/package-graph-gate.php` | OK |
| `php scripts/extension-contract-gate.php` | OK (29 classified) |
| `git diff --name-only -- 'packages/*/database/migrations'` | empty |
| Old runtime FQCNs | absent; historical carriers: `docs/architecture/core-zones.md` was→became table, `docs/architecture/rename-registry.md` (P15.13), `scripts/mutations/P1.24-*.patch` |
| `composer dump-autoload --optimize --strict-psr` | moved classes not in the skip list; pre-existing attributes/api test doubles still fail strict PSR |
| Autoload probe | new FQCNs load; old FQCNs gone |

Journal rows 368–372 are schema-complete. P15.10 is recorded `closed-green` and the generated state is 🟢 Done. Commit withheld: dirty worktree overlaps predecessor edits.

## P15.11–P15.12 — Package identity rename (batch E15f, 2026-09-27)

**Status:** product green, uncommitted · **Actor:** plan-run / grok-4.7 / high · **Batch:** E15f

Owner text «делай, я подтверждаю» accepted D89 and D90. Grants: `decisions/D134-g-pkg-api-grant-p1511.md`, `decisions/D135-g-pkg-auth-grant-p1512.md`. Briefs: `brief/owner-invocation-p1511-grok.md`, `brief/owner-invocation-p1512-grok.md`. Cursor still cannot satisfy provider-proof `pre_mutation`, so the durable start stays outside `plan-work.py prepare`. No Composer `replace`.

| Old | New | Unchanged |
|:--|:--|:--|
| `packages/api`, `corex/api` | `packages/operations`, `corex/operations` | `CoreX\Api`, `corex-api.*`, route prefix, operation ids |
| `packages/auth`, `corex/auth` | `packages/access`, `corex/access` | `CoreX\Auth`, `corex-auth.php`, database prefixes |

`api/v1` was regenerated by `corex:api-snapshots` so the manifest hashes match the generator. History allowlist for the old ids: `plans/`, `CHANGELOG.md` (including the Unreleased migration sentence), `docs/audit-2026-09-14*`, `scripts/anti-weakening-allowances.txt`, and the from-column of `docs/architecture/rename-registry.md`.

### Validation

| Carrier | Evidence |
|:--|:--|
| `composer --working-dir=packages/operations test -- --exclude-group=pg` | 257 passed, 2 skipped, 1741 assertions; `ContractSnapshotTest` `--check` green |
| `composer --working-dir=packages/access test -- --exclude-group=pg` | 38 passed, 65 assertions |
| Root `ApiContractTest`, `ErrorContractTest`, `LayerTest`, `AchievedInvariantTest` | 29 passed, 80 assertions |
| `php scripts/package-graph-gate.php` | OK after directory keys in `scripts/package-graph-undeclared-surface.json` |
| `php scripts/check-api-contract.php` | OK |
| Package `composer validate --no-check-publish --strict` | lock matches; existing `@dev` warnings remain |
| Old package ids outside the allowlist | absent |

## P15.13 — Registry and surface reconciliation (batch E15g, 2026-09-27)

**Status:** product green, uncommitted · **Actor:** plan-exec / composer-2.5 · **Batch:** E15g

Reconciled P15.1–P15.12 outcomes with `docs/architecture/rename-registry.md` (column «Исполнено», status исполнен). Machine input: `scripts/p15-rename-completeness-manifest.json` (`registry_sha256` from current registry; 40 tokens including registry rows plus `CompletionCaptureObserver` and D88 FQCN carriers). Gate diagnostic: `P15C`.

### Validation

| Carrier | Evidence |
|:--|:--|
| `php scripts/p15-rename-completeness-gate.php` | exit 0; `tokens=40 files=1761` |
| `php scripts/package-graph-gate.php` | OK |
| `php scripts/check-api-contract.php` | OK |
| `composer naming-gate` | OK |
| `composer --working-dir=packages/operations` `artisan corex:api-snapshots api/v1 --fixture=tests/fixtures/public-error-contract.php --check` | OK (when run from package cwd) |
| Registry rows | 29 executable rows + 3 property/package rows; none pending P15 |
| Product-tree old-token sweep | empty under manifest allowlist (historical: root `CHANGELOG.md`, registry, `core-zones.md` §5, `naming.md` Dep note, `anti-weakening-allowances.txt`, per-package changelogs, `audits/`, `plans/`) |

Surfaces touched: `docs/architecture/rename-registry.md`, `scripts/p15-rename-completeness-{gate,manifest}.json`, `scripts/mutation-check.sh` (Pest filter string), `scripts/anti-weakening-allowances.txt` (reason path), root `CHANGELOG.md`, `composer.json` script `p15-rename-completeness-gate`. Per-package changelogs intentionally unchanged (P15.13 scope). Item commit withheld: dirty worktree overlaps P15.1–P15.12.

## P15.14 — Full qualification and negative completeness proof (batch E15h, 2026-09-27)

**Status:** product green, uncommitted · **Actor:** plan-exec / grok-4.7 / high · **Batch:** E15h

Owner waived the model-route check for this execution. No new rename. Phase outcome: P15.1–P15.13 are terminal Done; P15.11 and P15.12 executed under accepted D89/D90 (none skipped).

### Planted negative proof

Disposable file `tests/fixtures/P15OldNameProbe.php` (token `CorexModule`), staged so `git ls-files` could see it, then removed.

| Step | Command | Exit | Output |
|:--|:--|--:|:--|
| Baseline | `php scripts/p15-rename-completeness-gate.php` | 0 | `OK`; `tokens=40 files=1761` |
| Planted | same | 1 | `P15C: forbidden old token \`CorexModule\` (P15.1) at tests/fixtures/P15OldNameProbe.php:10`; `1 violation(s)` |
| After removal | same | 0 | `OK`; `tokens=40 files=1761` |

Worktree has no probe. `git status` and `git ls-files` for that path are empty.

### Qualification gates

| Carrier | Evidence |
|:--|:--|
| `php scripts/package-graph-gate.php` | exit 0; `package-graph-gate: OK` |
| `php scripts/check-api-contract.php` | exit 0; `api-contract: OK` |
| `composer naming-gate` | exit 0; `naming-gate: OK`; `grandfather=3` |
| `php scripts/extension-contract-gate.php` | exit 0; `OK (29 classified)` |
| `composer test:arch` | exit 0; 89 passed, 286 assertions |
| `composer test:packages` | exit 0 after the CRM vendor refresh below |

Package lanes: operations 257 passed / 10 skipped / 1741; attributes 70/243; access 38/65; core 139/387; crm 34/135; transfer 8/24; intl 33/96; search-intl 6/14; media 19/54; modules 83/328; notifications 19/73; panels 12/56; recurrence 13/28; search 10/21; search-meilisearch 3/3; tasks 33/106; tasks-workflow 17/128; tenancy 496 passed / 7 skipped / 1766; wakeup 21/91; workflow 15/39.

### Repairs inside this qualification

- `tests/Arch/ApiContractCompatibilityTest.php`: the error-contract-drift pointer now expects `/errors/corex~1operations/access_denied/retryable`. The fixture owner is already `corex/operations` (P15.11). The stale `corex~1api` string made the negative case fail for the wrong pointer.
- `composer install --working-dir=packages/crm` installed the lock's `corex/operations` symlink. The previous `vendor/corex/api` link still pointed at removed `packages/api`, so CRM tests could not load `CoreX\Api\ApiServiceProvider`. Vendor stays gitignored.

### Residual risk (not green)

`composer dump-autoload --optimize --strict-psr` exits 1. Eleven skipped test doubles in `packages/attributes/tests` and `packages/operations/tests` (including `ErrorBoundaryTest` in `OperationErrorBoundaryTest.php` and `RequestMetadataTest` in `InvocationMetadataTest.php`). Production renamed FQCNs are absent from that skip list. Same residual as P15.1/P15.9. Not reported green.

Item commit withheld: the dirty tree still overlaps P15.1–P15.13. Next executable work is P16.1.
