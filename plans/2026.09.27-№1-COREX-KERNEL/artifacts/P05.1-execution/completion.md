# P05.1 — completion notes

Run `3ed100bc61ccd5d79efe701f5ec0f0ec706188202a61917393a7475f409dcd68`, route `claude-code/claude-sonnet-5-5/high` (`implementation/high`, D85). Base commit `18c8bbbb`; hosted CI on its product base `4522e2b3` was `success` (entry gate D73).

## Pre-run repair (owner instruction, D86)

Task 0.26.2 requires English structural headings; the plan carried localized ones (Routing `Почему`, Meta `Поле | Значение`, `План №1`, decision bodies `## Решение/Почему/Последствия`, Phase Handoff tables, an empty P10 Phase Handoff). They were renamed by hand; content, order, routes and review levels are unchanged. The rename changed the design fingerprint, so D86 selects the standard `design-audit-waiver/v1` bound to the new fingerprint and the saved owner message (`artifacts/P05-header-fix/owner-direction.md`). skm roadmap records the rule that localized names must not stop a run.

## VERIFY-AT-EXEC

| Fact | Result |
|:--|:--|
| `scripts/php84 --check` | PHP 8.4.26, extensions complete |
| E1 Testbench order | `RegisterProviders` (CreatesApplication:534) → `defineEnvironment` (543) → `BootProviders` (565); lazy singleton + resolve in `boot()` is correct |
| E8 `CommandStarting` | exists, `public string $command` |
| E2 `forgetScopedInstances` | exists, clears `scopedInstances` |

## Delivered

- **11 internal Options** with `fromArray` shape checks and tests: `WorkflowOptions`, `PanelsOptions`, `SearchIntlOptions`, `MeilisearchOptions`, `TransferOptions`, `WakeupOptions`, `SearchOptions`, `NotificationsOptions`, `TasksWorkflowOptions`, `CustomFieldsOptions`, `IntlOptions`. All pass on an empty environment, accumulate violations in one `InvalidConfiguration`, hide secrets (`SecretRef`).
- **Lazy singletons**: `CoreOptions`, `ModulesOptions` (moved to `RegistryRegistrar` to keep the provider ratchet 0), `AuthOptions`, `MediaOptions`, `ApiOptions`, `BulkOptions`, `CostOptions`, `TenancyOptions`, `WebhookOptions`; every public provider resolves its Options in `boot()`. Where registration depends on an enabled flag, one uncached parse precedes the singleton registration.
- **Readers**: `SqlGeographyProvider`/`SqlDatasetImporter` (dataset key), `CaptureTaskCompletion`/`SqlTaskRecurrenceMaterializer` (values), two tenancy commands (`TenancyOptions`, optional parameter to keep BC), `SqlOutbox` (table name).
- **Runtime layer**: `RuntimeConfigCheck` (Spi Implement), `RuntimeConfigGate` (HTTP after boot; console on `CommandStarting` except `BUILD_COMMANDS`), `DangerousModesCheck` in workflow, notifications, tasks-workflow, wakeup; `AppKeyCheck` in operations; `corex:config:validate` runs the checks (`--production`), prints acknowledged modes as warnings and the `require_physical_tenancy=false` notice. `CoreOptions::$acknowledgedDangerousModes` rejects patterns.
- **Static `Config`** deleted; 134 references replaced (30 files). `git grep -E 'CoreX\\Config\\Internal\\Config\b' -- packages products tests scripts` → exit 1.
- **Gate `config-composition`** C1–C4 (before: `C1 count=0`; after: `C1 count=0 C2 count=0 C3 count=0 C4 count=0`), allowlist exactly D69 p.6 (the provider glob is `*/src/**/*ServiceProvider.php`, covering `Modules/ModulesServiceProvider.php`); negative controls C1–C4 in `ConfigCompositionTest`.
- **Tests**: `OptionsSingletonTest` (runtime + static `bind`/`instance` scan + red control), `BuildStageBootTest` (all rows of the behaviour table), three new `ValidateConfigCommandTest` cases, 11 `*OptionsTest`.
- `table-write-ownership-gate` no longer knows the static holder; `P1.14-pg-lane-split` mutation stays red and restores the tree.
- Provider ratchet raised to the measured numbers (each +1 for the Options singleton, +0 for Modules); P05.2 replaces the scheme.

## Deviations from the dossier

- `ApiOptions` already carried `idempotencyLeaseSeconds`/`idempotencyRetentionSeconds` at the base; only the reads in `ApiServiceProvider` moved to them.
- `CoreOptionsParser::current()` stays (deprecated, returns the shared `CoreOptions`): core is `stable`, so its removal is an undeclared BC break for `bc-check`. The Repository use is gone from the parser (C2).
- `RuntimeConfigGate` is a public Spi-call type (`API-UNCLASSIFIED` otherwise), an addition to the delta listed in §4.
- Providers that had no `boot()` gained one (resolves their Options): manifest delta.
- `acknowledged_dangerous_modes` of notifications and tasks-workflow also surfaces the derived wakeup handler path `corex-wakeup.handlers.<kind>.accept_legacy_unstamped` (wakeup check reads the merged handler list); both exact paths must be acknowledged.
- `CustomFieldsOptions` keeps the shipped table defaults next to `AttributeTables` construction: `table-write-ownership-gate` resolves them statically.
- Tests that rewrite `corex*`, `tenancy*` or package config after boot got `forgetInstance(<Options>::class)` only where they failed without it; `custom-fields` Pg harness registers `CoreServiceProvider` (playbook).
- Wakeup scalar handler form is rejected (D01); one test replaced accordingly.
- PHPStan: seven categories of obsolete baseline entries removed (counts reduced or dropped), no new ignores.

## Moved checks of secrets

No `fromArray` required a secret at the base. New runtime checks: operations `app.key` (production). Meilisearch host/index/key and search cursor secret stay driver-creation requirements (unchanged behaviour).

## Golden delta

`golden-semantic-delta.json`: 1 removed record (static `Config`), 18 added, 16 changed (constructors of Internal readers, tenancy command `handle()`, provider `boot()`, `CoreOptions` field). Routes, events and wire are byte-identical; no `GOLDEN_UPDATE` in the final run (the baseline was edited by hand to exactly this delta, the update mode also re-adds promoted fixtures and renamed route actions). `REBASELINE.md` row added.

## Evidence (local, `scripts/php84`)

| Check | Result |
|:--|:--|
| `composer test:hermetic` | 960 passed / 4064 assertions |
| `PGSQL_PORT=5434 composer test:pg` | 242 passed / 1155 assertions |
| `composer test:packages` | all packages and `products/flex-crm` pass |
| `composer test:arch` | 108 passed / 399 assertions |
| `composer golden` | 4 passed / 1352 assertions, no update mode |
| `composer lint:check`, `analyse`, `analyse:tests-lint` | exit 0 |
| `php scripts/quality-ceiling-guard.php` | `ceilings OK` |
| `config-composition-gate`, `table-write-ownership-gate`, `provider-size-gate`, `module-layout-gate` (R0–R6 = 0), `package-docs-gate`, `anti-weakening-gate` | OK |
| `php-api --write` twice | byte-identical; `composer php-api` exit 0 |
| `composer bc-check` | OK |
| `mutation-check.sh P1.14 pg-lane-split` | red under the patch, tree restored |

review: self-check GREEN (D71 light).

## Hosted CI

- Product commits: `5911120c` (implementation) and `2c625e4d` (CI repair, tests only).
- `origin` (`axiomasoft/corex`) run `37027169858` on `5911120c`: 36 of 37 jobs failed in two seconds without starting a step; the check-run annotation reads «The job was not started because recent account payments have failed or your spending limit needs to be increased». External billing boundary, no product signal (`validation/25-origin-ci-37027169858-billing.json`).
- By owner instruction the hosted CI runs on the mirror `academici/corex-mirror`. First run `36999145336` on `5911120c`: 34 success, 2 red — `Crm package` (flex-crm Pg tests rewrote `corex.ids.strategy`/`corex.modules.connection` after boot without invalidating `CoreOptions`/`ModulesOptions`) and `Standalone dependencies` (the wakeup Options test declared its probe handler in the test file; dependency-analyser could not autoload it). Both repaired in `2c625e4d`; `flex-crm` Pg 46 passed locally.
- Mirror run `37029892294` on `2c625e4d`: **conclusion success, 36 success / 0 failure / 1 skipped** (the mutation registry job is event-gated, as in P10.4) — `validation/24-mirror-ci-37029892294.json`.
- Deviation from Validation: the plan names `origin`; the owner redirected hosted CI to the mirror because the `origin` account cannot start jobs.
- The first push went to the mirror by mistake before `origin` (reported to the owner); `origin/main` and `mirror/main` both carry `5911120c`, the mirror additionally `2c625e4d`.
