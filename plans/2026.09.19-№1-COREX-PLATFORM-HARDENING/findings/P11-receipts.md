# P11 receipts

## P11.2 — Deterministic compiler and canonical frozen snapshot (2026-09-24)

**Status:** GREEN · **Actor:** plan-run/cursor-grok-4.6/high · **Batch:** E11b

Compiler-private `NormalizedExtension` expands every §2 point before collision
checks. Identity is point-specific (`entity`, `entity.field`, `filament.*`,
`workflow.event`, `workflow.listener`, `api.operation`/`tool`/`resource` including
wire identity). Diagnostics name the point, id and both owners.

`Manifest::replace()` is applied after identity validation: target and replacement
must exist in one point, the owner must own the replacement, one incoming edge,
acyclic. Menu `afterHandle` is a stable topo; missing target and cycles fail.
Default order is id ASC. Canonical bytes are `corex-registry/1\0` plus JSON
(`JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE`).
`CompiledRegistry::fromArray` fail-closes on unknown/missing schema or fingerprint
mismatch; empty `[]` is a missing artifact and hydrates as `empty()`. Hydrated
extender objects are detached copies; later mutation does not change
`toArray()`/fingerprint.

`fromArray([])` returning `empty()` is the missing-cache path used by feature
boot, not a corrupt payload.

### Validation

| Carrier | Evidence |
|:--|:--|
| Permutation / canonical fingerprint | `packages/modules/tests/Unit/RegistryCompilerDeterminismTest.php` |
| Duplicate identity, replacement, menu, corrupt artifact, detached mutation | `tests/Feature/RegistryCompilerTest.php` |
| Pint | `composer --working-dir=packages/modules lint:check` passed |
| PHPStan | `composer --working-dir=packages/modules analyse` 0 errors |
| Modules package suite | `composer --working-dir=packages/modules test` 53/53 then 56/56 after P11.3 |

P11.3 consumes the fingerprinted detached snapshot.

## P11.3 — Runtime publication, collector freeze and refresh boundaries (2026-09-24)

**Status:** GREEN · **Actor:** plan-run/cursor-grok-4.6/high · **Batch:** E11b

`ArrayEntityRegistry::register()` throws `RegistryFrozen` for late and duplicate
registration before mutation. `ModulesServiceProvider` registers a `booted`
callback after `ModulesBootRegistrar::boot()`, so freeze runs after compiled
entity projection, morph map and audit wiring.

`ReadRegistryIntrospection::revisionFor()` returns the compiled fingerprint.
Activation epoch stays off that hash. `CompiledModuleRegistry` documents
`octane:reload` and queue restart as deploy obligations; `JobProcessing` still
flushes the memo before a job and the first `compiled()` pins the instance for
that job. Empty `[]` remains missing, not corrupt.

### Validation

| Carrier | Evidence |
|:--|:--|
| Duplicate / late freeze leave collector unchanged | `packages/modules/tests/Unit/RegistryFreezeTest.php` |
| Introspection equals fingerprint; activation does not change it | `packages/modules/tests/Feature/RegistryIntrospectionTest.php` |
| Job pin, next-job reload, corrupt artifact, activation off fingerprint | `tests/Feature/ModuleRegistryTest.php` |
| Pint | `composer --working-dir=packages/modules lint:check` passed |
| PHPStan | `composer --working-dir=packages/modules analyse` 0 errors |
| Modules package suite | `composer --working-dir=packages/modules test` 56/56, 250 assertions |
| Root feature | `vendor/bin/pest --filter=ModuleRegistryTest` 20/20, 59 assertions |

### Remaining risk

Consumer tests that call `ArrayEntityRegistry::register()` after the application
is fully booted will now throw `RegistryFrozen`. That is the intended publication
boundary. Auth/CRM suites were not re-run as a full package lane in this item
(`Files` is modules + root `ModuleRegistryTest`). Measure those consumers in
E11c if they fail after this freeze.

P11.4 consumes the published fingerprint as diagnostic provenance only (D103).

## P11.6 — Complete tasks-workflow materialization provenance (2026-09-24)

**Status:** GREEN · **Actor:** plan-run/cursor-grok-4.6/high · **Batch:** E11e

Producer always emits `seriesId`, `immutableRevision`, `definitionHash` and the full
window. `MaterializationHasher` is the single encode/decode site. Missing or empty
required fields throw `UnsupportedMaterialization`/`StaleMaterialization`; the handler
never returns as success. Wakeup `WriteToken` uses `MaterializationHasher::payload()`
so definition hash, window and handler version `task-materialization/1` (§11.1; item
text `tasks-materialization/1` is the same existing hasher/receipt version) participate
in the operation hash. Completion capture stores `definitionRef`/`versionRef` =
`task-completion/1`. Structured P11.5 handler config is declared in
`corex-task-recurrence.php` and registered in PG tests. Idempotency keys stay
`wakeup:<requestKey>` / `materialize:<seriesId>`.

### Validation

| Carrier | Evidence |
|:--|:--|
| Complete payload and window round-trip | `packages/tasks-workflow/tests/Feature/MaterializationTest.php` MAT-10 |
| Valid handler materialize + duplicate delivery | Feature MAT-11 |
| Missing/tampered payload, no write | Feature MAT-12 |
| Stamped schedule, handler sweep, mismatch quarantine | PG MAT-06, MAT-06b |
| Completion `task-completion/1` | PG MAT-07 |
| Pint | `composer --working-dir=packages/tasks-workflow lint:check` passed |
| PHPStan | `composer --working-dir=packages/tasks-workflow analyse` 0 errors |
| Feature suite | `composer --working-dir=packages/tasks-workflow test -- --exclude-group=pg` 10/54 |
| Isolated PG | `composer --working-dir=packages/tasks-workflow test -- --group=pg` 7/74 |

## P11.7 — Versioned notification digest wakeups (2026-09-24)

**Status:** GREEN · **Actor:** plan-run/cursor-grok-4.6/high · **Batch:** E11e

Digest kind stays `notification.digest.v1`. Implementation version is
`notification-digest/1`. The provider writes a structured P11.5 definition; legacy
admission is `corex-notifications.digest.accept_legacy_unstamped` and defaults false.
New rows are stamped by wakeup (`wakeup-payload/2`). Version mismatch and missing
stamp never reach `DigestWakeupAdapter`. Adapter still receives the unwrapped
`DigestTick` DTO. No notifications-side registry or stamp serializer was added.
`SqlDigestBuilder` scheduling payload is unchanged (`run_id` only).

### Validation

| Carrier | Evidence |
|:--|:--|
| Canonical declaration/stamp | `packages/notifications/tests/Digest/WakeupBridgeTest.php` |
| Exact match + unwrapped DTO | WakeupBridgeTest |
| Default-denied legacy | WakeupBridgeTest |
| Explicit legacy bridge | WakeupBridgeTest |
| Version mismatch, no invocation | WakeupBridgeTest |
| PG digest concurrency / no duplicate lock | `packages/notifications/tests/Pg/DigestConcurrencyTest.php` |
| Pint | `composer --working-dir=packages/notifications lint:check` passed |
| PHPStan | `composer --working-dir=packages/notifications analyse` 0 errors |
| Notifications suite | `composer --working-dir=packages/notifications test` 19/73 |
| Isolated PG | `composer --working-dir=packages/notifications test:pg` 2/2 |

## P11.8 — Final extension contract proof (2026-09-24)

**Status:** GREEN · **Actor:** plan-exec/codex-gpt-6-sol/xhigh

### Authority, publication and durable compatibility

| Producer / consumer | Evidence and boundary |
|:--|:--|
| P11.1 vocabulary | `Manifest::replace`, `InstalledPackageVersion`, `ExtensionStamp`; modules 37 tests / 200 assertions, PHPStan 0, Pint green. |
| P11.2 compiler | `corex-registry/1` canonical JSON; point-specific duplicate owners, replacement graph, menu topological order, scalar detached snapshot. |
| P11.3 publication | `CompiledRegistry` is the code authority; `ArrayEntityRegistry` freezes after boot projection. Queue job flushes memo before its next job; Octane deploy reload is required. Activation epoch remains separate from code fingerprint. |
| P11.4 workflow | Stored `definitionRef`/`versionRef` and `workflow.trigger` stamp; unrelated registry fingerprint does not change replay hash. Existing legacy refs keep their identity algorithm. Workflow suite 15/39. |
| P11.5 wakeup | Frozen typed handler map; `wakeup-payload/2` exact kind/version; missing/mismatch quarantines without handler invocation. Legacy unstamped requires per-handler opt-in, and read-only preflight leaves rows unchanged. Wakeup unit/feature 21/91 and isolated PG 2/5. |
| P11.6 tasks-workflow | `definitionHash`, immutable revision and window are persisted; incomplete payload throws rather than silently succeeding. Feature 10/54 and PG 7/74. |
| P11.7 notifications | `notification-digest/1` exact handler version; default-denied legacy, explicit bridge, mismatch without adapter invocation. Feature 19/73 and PG 2/2. |

`SearchManager` is a driver factory. API tool/operation and entity registries are projections
of the compiled code snapshot. Panel catalogue is built at boot; P10 field catalogues
own data-schema fingerprints. The gate classifies 27 current `*Registry|*Catalogue|*Manager`
types; the injected `CoreX\Fixture\SurpriseRegistry` exits 1.

### Reusable suite and negative fixtures

`CoreX\Modules\Testing\ExtensionContract` takes manifests plus explicit installed
package versions, returns `passed()`, `summary()` and a fingerprint, and has no
test-runner or container dependency. The optional compiler metadata parameter is
additive and keeps existing calls unchanged. `CoreX\Modules\Testing` is declared
in `corex/modules` `public-api` for external extension tests.

| Fixture | Expected | Fingerprint / diagnostic |
|:--|:--|:--|
| `positive.json` | pass | `d0f99736274b923e70f793875fa8a377b5eb6e242b66c1c36f5d0babc72da0df` |
| `replacement.json` | pass | `625e608da6dfc886b325217505c36719d4f5a746f26b1a95ee170d664651b533` |
| `duplicate.json` | fail | canonical id plus both owners |
| `replacement-cycle.json` | fail | cyclic replacement graph |
| `unclassified.php` | fail | unclassified `SurpriseRegistry` |

The suite reverses module, extender and replacement order; compares bytes and
fingerprint; reloads the artifact; rejects unknown schema and wrong hash;
asserts collector duplicate/late rejection and snapshot detachment; and probes
installed version sensitivity. Exact point/id/version accepts replay after an
unrelated fingerprint change and rejects a version change.

### Validation carriers

| Carrier | Result |
|:--|:--|
| `composer --working-dir=packages/modules lint:check` | passed |
| `composer --working-dir=packages/modules analyse` | 0 errors |
| `composer --working-dir=packages/modules test` | 62 tests / 265 assertions |
| `composer --working-dir=packages/workflow test` | 15 / 39 |
| `composer --working-dir=packages/wakeup test` | 21 / 91 |
| `composer --working-dir=packages/tasks-workflow test -- --exclude-group=pg` | 10 / 54 |
| `composer --working-dir=packages/notifications test` | 19 / 73 |
| `php scripts/extension-contract-gate.php` | 27 classified, both positive fingerprints stable |
| injected unclassified fixture | exit 1, names `SurpriseRegistry` |
| `composer lint:check` | passed after formatting a pre-existing wakeup scheduler change and this item's fixture |
| `composer analyse` | 0 errors |
| `composer test` | 540 / 1817 after repair of three inherited gate failures |
| `composer test:pg` | 211 / 910 on isolated `corex_test`; host `pg_isready` and DB existence passed |
| `BC_CHECK_ONLY=modules composer bc-check` | compatible against `BC_CHECK_BASE=9608b488bc1ab1fcf635cf08254b5f6687e0d60a` |

The full monorepo `composer bc-check` ran but stopped in the unrelated
`corex/tenancy` Roave checker with a syntax error. The affected `corex/modules`
lane completed independently and is compatible; this full-checker limitation
does not change the P11.8 package compatibility result.

The inherited root gate failures were `ValidateWakeupStamps` being treated as
undeclared public API and a stale provider-size ratchet after P11.5's new
handler binding. The command is now explicitly internal; the measured wakeup
ratchet is 4. This item's public testing helper was added to the declared
`corex/modules` public surface. Exact original gates were rerun green.
