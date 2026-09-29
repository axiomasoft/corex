# PLAN3.P03.1 — execution evidence

Route: executor/codex/gpt-6-sol/high, provider-thread-state. Durable run:
2ec768587e727b70634f37f31b067b75634ce7fb03b8b6afe0852582c145829e.
The input checkout was clean; unrelated generated report timestamps caused by test runs were restored.

## Implementation

The module-layout gate, config, eight-test suite and current baseline were copied byte-for-byte
from the approved design. Composer and CI execute the gate and its test suite. The retired gate,
its two fixtures and the two obsolete STR exception records were removed. STR-001 and the volume
profiles were removed; STR-002/003/005/006 and the architecture index now describe the module
layout, direct public categories, module-owned Internal and the composition roots from D57/D58.
STR-004 remains unchanged. No package source, public API, package-graph rule or Golden baseline changed.

## Validation

- module-layout: 1112 files, 436 violations/baselined, zero new/stale;
  R0 0, R1 49, R2 0, R3 344, R4 0, R5 43. All four byte-copy checks passed.
- Python gate suite: eight tests passed, including R0–R5, PHP alias/reference resolution,
  composition-root boundaries and baseline new/stale controls.
- A planted real search Support/Planted.php produced new R1 and R3 with exit 1;
  after its deletion, the exact gate returned exit 0. No baseline was changed.
- Root SQLite 822 + 10 passed (3503 + 54 assertions), identical counts to the input;
  PostgreSQL 240 passed (1149 assertions), identical to input.
- All 21 package suites returned exit 0: 1338 passed; skipped/external suites are recorded
  in packages.log, and per-package counts in package-counts.json.
- Composer strict validation, full PHPStan/dependency analysis, quality ceilings,
  architecture, Golden (4), package docs, naming, graph, ownership, rename completeness,
  anti-weakening and plan-lint passed. Logs and exact commands are in checks.json.
- Mutation registry: 34/34 tests turned RED under sabotage, every source restored;
  mutation.log and no package-source diff prove restoration.
- Active retired-name scan, obsolete exception links, deleted file/directory existence and
  five retained STR article checks passed; acceptance-checks.json records exact results.

## Explicit baseline and specification amendment

D60 preserves immutable audit snapshots/patches and explicitly excludes audits/ from the
active-source retirement scan. historical-references.log retains the original literal scan.
The source scan still forbids all active composer, CI, script and documentation consumers.
D60 is linked in the current item Inputs and its amendment receives independent full review.

The full-tree Pint command returned exit 1 solely on the unchanged P03.2 helper
artifacts/P03-design/scripts/p03-adapters.php: no_multiline_whitespace_around_double_arrow,
unary_operator_spaces, not_operator_with_successor_space, blank_line_before_statement.
It is a foreign, non-gating baseline for P03.1's declared Validation and belongs to P03.2
per D60; no lint rule/config or helper bytes were changed in this item. This is explicitly
not a claim that the full-tree lint is GREEN. Hosted CI remains deferred under D47.

## Independent review

The sheet reviewer cursor/grok-4.7/high reviews the staged product diff, current item and
D60 in fresh context; its exact response and final verdict are stored in review.json.

Round 1: GREEN, no actionable finding. The reviewer explicitly accepted D60, preserved audit
history and the foreign Pint baseline; its full judgement is in review-round1.json.
Round 2 binds that verdict to the added Update Log provenance row, the corrected index label
and the third historical audit filename. These changes affect no rule, gate, baseline or test.

Round 2: GREEN on independently recomputed fingerprint
168118f4fe7433cf8ae017ac6b446904aed2e2dbb9a57478e7ea81f59c256532.
review: cursor/grok-4.7/high GREEN (2 rounds).

Task contract: pre_mutation allow, pre_final allow. Terminal evidence binds every declared
Validation carrier to the current specification. Item terminal event is closed-green;
Task finalize converged current state, decision/status views, next P03.2 bundle and receipt.
The phase remains open for P03.2. Details are in write-sites.json, validation-receipts.json,
terminal-reconciliation.json and continuity.json.

## Newly visible successor admission defect

After the terminal event, Task materialized P03.2's formerly producer-dependent bundle.
Post-close plan-lint reports two errors for that successor only: module-map.tsv is
260131 bytes / 119306 estimated tokens (file token limit 25000); P03.2 aggregate is
184713 (item limit 100000). The unchanged map was authored before P03.1 and is outside
P03.1 Files. This does not falsify the earlier successful plan-lint or P03.1's exact declared
Validation, which has no changed/red carrier. The current item stays GREEN; the future
carrier's admission remains visibly RED. No threshold, calibration, map or future item spec
was changed to manufacture admission. Its owning continuation is P03.2 on implementation/xhigh:
produce a faithful compact execution projection while preserving all transfer work/tool
validation, refresh its bundle/design evidence, and pass read-budget before product writes.
See plan-lint-final.log and the exact condition in handoff.md. The readable Next recommendation
identifies that owning continuation and does not claim that P03.2 can begin product writes now.
