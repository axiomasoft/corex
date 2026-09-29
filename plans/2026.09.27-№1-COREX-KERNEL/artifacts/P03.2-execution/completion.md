# PLAN3.P03.2 — execution evidence

Executor: codex/gpt-6-sol/xhigh, attested provider-thread-state. Durable run:
`fbd89d560f619302cce68ef7d1aae4594dee3d96437a4ede33ecac0d2c14133c`.
Input/specification anchor: `65a673101b111a69df7e3b40ece6011f7f2f5f3d`.

## Product and transfer

The approved tool moved 278 classes/files and rewrote 278 namespaces, 53 namespace tokens
and 53 vanished namespace records; plan and verify both reported zero problems.
The complete 1168-row TSV is unchanged; all 68 public-by-usage symbols and product paths remain.
Manifest now has 658 class and 110 namespace tokens. Golden received exactly 376 manual
identifier rules; generated/ambiguous fields and frozen baseline bytes remain unchanged versus
both the source anchor and `52446de`. Composer lock changes only the allowed local FQCN
metadata/path references, with unchanged dependency set and content hash. Six manifests match.

The tool preserved 12 implicit imports and projected eight NEON path files without rebaseline.
Fixture subjects and exact historical cross-zone edges follow PLAN3.D59. Five adapter hashes
were refreshed only after AST-equivalence checks. All 15 packages with moves received the
prescribed CHANGELOG note. Pint formatted the changed PHP files.

Scratch setup used an independent root vendor and 21 copied package vendors, no hardlinks
and no symlinks outside scratch; autoload was rebuilt for each. This proves the installed
local dependency set. It is not fresh-install or hosted-CI evidence. Push/hosted CI remain
deferred under PLAN3.D47. Commands/durations are in dry/setup-checks.json,
dry/transfer-checks.json and dry/stack/checks.json.

The main transfer repeated the same commands. Final path sets, diff stat and SHA-256 values
match all 702 candidate paths byte for byte (main/candidate-equality.json). Historical
P12 generated report bytes were restored after retaining current gate outputs in the logs.

## Validation

- Layout baseline equals the prediction byte for byte: 1112 files, 159 violations/baselined,
  zero new/stale; R0/R1/R2/R4 zero, R3 68, R5 91.
- Transfer regression suite: 3 tests; live layout negative suite: 8 tests. Lossless projection
  reconstructs all eight columns/1168 rows. Removing a row or altering its source digest
  fails; restoring the exact bytes returns GREEN (projection-negative.log).
- Root SQLite: 822 + 10 passed / 3503 + 54 assertions; PostgreSQL: 240 passed / 1149 assertions.
  Both counts equal the fresh before-transfer baseline. All 21 package suites passed:
  1349 tests in both trees. Previous P03.1 logs total 1338; no package count decreased.
  Per-package totals and the comparison are in package-counts.json.
- Golden 4 and architecture 92 passed; full lint, PHPStan, dependency analysis, ceilings,
  anti-weakening, package graph, strict ownership (553 identifiers, zero baselined), naming,
  package docs, API contract and contract guard all returned exit 0.
- Mutation cohort: all 34 sabotage cases turned RED and restored their sources, in both trees.
- Rename completeness: 859 tokens; GREEN after the fixture repair. Planting a retired FQCN
  in SourceScanner.php returned exit 1; restoring the exact original bytes returned exit 0.
  main/rename-negative.log records both sides. Final verify also returned zero problems.
- Plan-lint passed after the admission repair and on both completed candidates.

Exact commands, exit codes and times are in the dry/main check manifests and their logs.

## Bounded repairs

PLAN3.D61 fixes inherited read admission: a lossless 40420-byte projection replaces the
119306-token full TSV reading carrier. It has 9376 estimated tokens; complete transfer work,
the TSV/hash, thresholds and calibration remain unchanged. The new bundle is admitted.
The four inherited adapter helper formatter findings were repaired; its parsed AST is identical
to the old HEAD. Independent admission review is GREEN at fingerprint
`4d18ef3f2456e4983d21ca2864f067985d01c35ee71fef1d7a2b20750daf50d3`.
The initial repair record's source count is corrected append-only from five to six in
repair-decision-final.json/journal; generated bookkeeping remains excluded.

One manual residue: scripts/tests/test_module_layout_gate.py's R1 planted path/namespace
`Support` becomes `Unlisted`. The original main tree reproduced the retired namespace failure.
Both names take the same forbidden-kernel-root R1 branch; CONFIG, all rules/assertions and eight
tests remain unchanged. Exact rename carrier and affected Python tests were rerun GREEN.
residual-repair.json records the owning reversible repair; original RED logs are retained.
No map/transfer tool defect or modification occurred beyond the authorized helper formatting.

## Independent full review

review: cursor/grok-4.7/high GREEN (one product review round, fresh context).
The reviewer independently recomputed all 702 product hashes and sampled 20 moved files,
checked all 278 bodies under FQCN projection, short-name/resource references, all 68 retained
symbols, Golden/lock/baseline invariants and the retained negative control. No actionable finding.
review.json contains the exact verdict; main product bytes match its reviewed candidate.

Product and closure have separate anchors because declared helper/projection Files intersect
digest-bound Inputs. Closure evidence and the exact next recommendation are generated by Task.
