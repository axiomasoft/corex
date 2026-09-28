# P02 design facts (2026-09-28, tree `d2e84a1` + map repair D44 п.7)

All commands run from the repository root. `PLAN=plans/2026.09.27-№1-COREX-KERNEL`, `D=$PLAN/artifacts/P02-design`.

| # | Fact | Reproduce | Result at design |
|:--|:--|:--|:--|
| F1 | P00 maps are valid on the current tree | `python3 $PLAN/artifacts/P00-design/scripts/check-maps.py`; `… --dependencies` | `maps OK: 558 prefixes, 21 packages`; `dependencies OK: acyclic, kernel-independent (1756 edges, 19 source packages)` (after the StubToolHandler `old_prefix` repair; before it: 1 `prefix-mismatch`) |
| F2 | Seams: 136 non-keep rows in `dependency-edges.tsv` | `seams.tsv` (this dir, column `item`) | P02.1 = 47 rows / 9 files (D27, D28, D32, D35 outside operations); P02.2 = 89 rows / 39 files, of which 18 rows `CoreX\Api\Tests\TestCase` are resolved by the P02.3 scaffold (own `TestCase` of tools/webhooks) |
| F3 | Seams still present in code | `python3 $D/scripts/p02-transfer.py seams` | `seams: 136 unresolved (18 scaffold-owned, 118 blocking)`, exit 1. Target after P02.1: 89 (18/71); after P02.2: 18 (18/0), exit 0 |
| F4 | Transfer plan | `python3 $D/scripts/p02-transfer.py plan` | `plan: 795 classes, 510 php moves, 744 namespace rewrites, 20 packages, 2 dir renames, 17 extra moves, 1 pending, 0 problems`; chains 0, duplicate targets 0, path collisions 0; pending = `packages/operations/config/corex-webhooks.php` (created by P02.2) |
| F5 | Design dry run without Rector | scratch `git worktree` at `d2e84a1` + placeholder `corex-webhooks.php`; `apply --no-rector --allow-seams`; `verify` | 15 s; literal pass 168 text + 138 php files; 744 namespaces; 510 php + 17 extra moves + 2 dirs; git status 178 R, 363 RM, 642 M, 6 D; `verify: 0 problems` (after the `flex/crm` self-name fix); edge-derived requires: operations→transfer, tenancy→wakeup, tasks-workflow→wakeup, access→tenancy, flex-crm→intl — the first three stay optional (`suggest`/`require-dev`, fixed in the tool), access→tenancy and flex-crm→intl become `require` (config file / src edges) |
| F6 | Old-name occurrences outside history | manifest `rename-completeness.preview.json` + `rename-completeness-gate.php` (copied to `scripts/`) | `FAIL: 8509` = classes 2444, namespaces 3197, packages 2350, paths 177, identifiers 341; target after P02.3: identifiers only; after P02.4: 0 |
| F7 | Identifier occurrences | `python3 $D/scripts/p02-identifiers.py check` | `identifiers check: 341 remaining` (top: `corex-modules` 45, `corex-api` 43, `corex-auth` 40) |
| F8 | Ownership inventory | `php scripts/ownership-gate.php` / `--report` | `ownership OK: 420 identifiers, 122 baselined` (O1 2, O2 namespace 43, O2 env 33, O2 tag 11, O2 command 9, O2 config 7, O3 17); target: `--strict` exit 0, baseline `[]` |
| F9 | Golden rename map | `python3 $PLAN/artifacts/P01-design/scripts/golden-rename-map.py --check` | `rename-map OK: 666 class, 16 package, 3 ambiguous, 0 manual` (class count grows by the classes P02.1/P02.2 create; regenerate after each seam item) |
| F10 | Golden delta checker | `python3 $D/scripts/golden-delta.py` | clean tree: `golden-delta OK: +0 -0 ~0 (allowed 0)`; negative control (fake type added to `php-surface.json`) → `php-surface added CoreX\Fake\Added outside the item diff`, exit 1 |
| F11 | Legacy P15 gate | `php scripts/p15-rename-completeness-gate.php` | 9 violations on `d2e84a1`; wired only as composer script, not in CI (D45 п.6: retire) |
| F12 | Hosted CI on `d2e84a1` | `gh run view 36445217833` | all jobs `failure` in 6 s: «recent account payments have failed or your spending limit needs to be increased» — billing, not code (O-3 open) |

Files here: `seams.tsv` (F2), `extra-moves.tsv` (non-class files of merged/split packages), `identifier-map.tsv` (D45 п.1, 62 rows), `new-map-rows.tsv` (rows seam items add), `rename-completeness.preview.json` (F6 manifest; P02.3 regenerates the real one before `apply`).
