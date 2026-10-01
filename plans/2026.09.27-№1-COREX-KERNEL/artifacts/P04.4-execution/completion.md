# P04.4 — completion GREEN (2026-10-01)

Product anchor: `975cc3f0` (`feat(core): enforce source API and SPI contracts`).
Executor route: `codex/gpt-6.1-sol/high`, provider-thread-state attestation from the durable start.
Scope: P04.4 only. Owner execution economy: [D68](../../decisions/D68-owner-execution-economy.md).
Independent review and phase audit were not performed, as explicitly instructed by the owner.

## Delivered

Strict source attributes own PHP API/SPI membership. All 21 package/product Composer manifests retire `extra.corex.public-api`; root/CI require strict checking. The obsolete undeclared-surface snapshot and 1274-line namespace exception debt are removed. Graph R1–R5 retain independent negative controls; stale generated JSON cannot authorize imports.

20 generated package manifests and `docs/reference/public-api.md` describe 800 supported symbols (412 Api, 152 Spi Implement, 236 Spi Call), with zero diagnostics among 1305 owned declarations. All 1173 P04.2 classification rows reconcile to actual ownership and source layers. The P04.3 error registry remains GREEN. Final generation twice is byte-identical; see `final-surface.json` and `idempotence.json`.

Roave enumerates each revision's own Api/Spi membership while retaining Internal dependencies for reflection. Base deletions remain visible; callable Api additions and required Implement additions receive distinct handling. JSON result classification keeps skipped checks infrastructure RED, including experimental packages. Enum/new-initializer fallback checks changed values and unresolved dependencies. The unpublished baseline is explicitly bound to accepted P04.3 (`76f8bdde...`, D01/D63/D64); `BC_CHECK_BASE` can select a release revision.

API policy, constitution, actual package taxonomy, snapshot schema, SemVer matrix and all 20 changelogs describe source authority and layer contracts. No public release or hosted-CI evidence is claimed.

## Validation and bounded reuse

| Acceptance | Evidence and final outcome |
|:--|:--|
| Generation, strict surface, source reconciliation | `idempotence.json`, `final-surface.json`, `surface-golden-repair.log`: GREEN; 20 manifests + reference |
| Error registry | `gates-result.json` / `registry.log`: exit 0 |
| Graph R1–R5, stale manifest and scanner negatives | `graph-golden-repair.log`: exit 0; `focused-golden-repair.log`: 51 tests / 225 assertions pass |
| Composer manifests | `composer-validate-final.log`, `package-manifests-validation.json` (21/21), `manifests-final.log`: GREEN; no live namespace public-api metadata |
| BC | `bc-final.log`: all 20 package comparisons compatible; final shell epilogue failed after a concurrent script edit. `bc-wrapper-final.log`: corrected wrapper/JSON classification exits 0 on access. Unchanged 20 comparisons are reused, not claimed as a second full command run |
| BC negative controls | `tests-repaired-final.log`: 9 tests pass, including 6 isolated BC scenarios (Internal dependency, Api/Spi deletion, Implement addition, Api addition, missing dependency); `bc-classifier-controls.json`: 11 controls GREEN; `bc-defaults-final.log`: unchanged/changed enum and initializer + missing dependency RED |
| Module layout, docs, ownership, rename, ceilings | `module-layout.log`, `package-docs.log`, `ownership.log`, `rename-final.log`, `ceilings-final.log`: GREEN / zero residual; no ceiling increased |
| Static analysis | `analyse.log`: unchanged 20 other package/product configs pass; repaired core and root pass in `core-analysis-golden-repair.log` / `root-analysis-golden-repair.log`. No fresh baseline exceptions added; obsolete entries/counts reduced |
| Style | `lint-acceptance.log`: full lint exit 0; final three-file Golden correction formatted in `format-golden-repair.log` |
| Root tests + Arch | `root-tests-final.log`: 883/884 passed, sole obsolete namespace assertion repaired in `tests-repaired-final.log`. The new sixth BC scenario and 51 affected scanner/graph tests also pass. `root-workflow-final.log`: remaining 14 tests pass. Arch and filtered acceptance are covered by these runs; no repeated full root/Arch sweep |
| PostgreSQL | `pg-final.log`: 240 tests / 1149 assertions pass; isolated `corex_test`, container healthy and ready; `db-preflight.json`. Host port **5434**, the actual Docker published port, replaces the dossier's stale 5432 example. Existing isolation guard and RefreshDatabase remain active |
| Package cohort | `packages-final.log`: composer test:packages exits 0 for 20 packages + FlexCRM. `package-test-summary.json` retains per-package results and pre-existing skips |
| Golden | `golden-repaired.log`: 4/4 pass, 1333 assertions, without GOLDEN_UPDATE. `golden-identity.json`: every tracked Golden source/baseline byte matches the item preimage; routes unchanged |
| Anti-weakening / mutations | `anti-weakening-final.log`: GREEN, sqlite assertion count increases, pg unchanged; `mutation-final.log`: all 34 registered sabotages fail and are reverted |
| Release fixture metadata consumer | `distribution-tests-final2.log`: 10/10 pass |
| Independent review | Explicitly replaced by owner decision D68 and own changed-scope checks; no reviewer, audit or fabricated review execution receipt |

Historical RED logs are retained. Full repeated scanner reports are condensed to counts and SHA-256 summaries; generated manifests retain full signatures. Only checks whose inputs changed or which actually failed were repeated.

## Integration corrections

Source-based membership also updates naming, documentation, Attributes architecture and extension fixture consumers. Recurrence's missing core path repository is restored; the rename gate's recurrence adapter direction is aligned with accepted P04.2. Release fixture discovery includes its already-declared product exclusion. Root lock sync changes local package metadata/references only; dependency versions remain unchanged.

The Golden correction keeps `SurfaceReport`'s existing method surface intact and constructs artifact destinations in the CLI/graph consumers. Only its report rendering and typed Internal proof changed. Generated artifact bytes remained identical after the correction.

## Acceptance and continuation

`write-sites.json` records runtime pre_mutation allow. Raw pre_final requires E27's obsolete independent-review receipt; D68 explicitly waives that carrier, so effective pre_final is allow. Actual execution receipts exist for the ten substantive acceptance carriers. No independent-review execution receipt was invented.

Product/closure split is required by actual digest-input collisions (`scripts/bc-check.sh`, `scripts/package-graph-gate.php`, `tests/Arch/PackageGraphTest.php`), recorded in `anchor-mode.json`. The second commit changes closure/evidence only and does not trigger product test repetition.

After this item, close P04 inline through the GREEN phase gate and fresh closure. Next recommendation: `design-phase: task:plan-design 2026.09.27-№1-COREX-KERNEL P05`. P05 implementation is not authorized by this invocation.

Closure carrier repair: the authored P04 index lacked the canonical `Контракт фазы` block required by the generated closure writer. Added a semantic summary of its already-accepted scope and D68 method; material candidate fingerprint remained identical. This changed closure prose only.
