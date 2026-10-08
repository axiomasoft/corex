# P09.5 completion

Implemented the strict package-fragment Infection runner and exhaustive source map.
1476 source files map to 25 fragments; 354 exact files have current AST declaration
proofs. MSI thresholds remain 95/90/75; equivalents remain capped at 5% per fragment
and globally. No equivalents or declaration-only fragment approvals were added.

All declared acceptance checks are GREEN (completion.json with log hashes):
Composer audit, 33 parser/AST/process tests, self-test entrypoint, all 35 fixed
sabotage controls, Pint, complete PHPStan, exhaustive inventory and real locked
Infection probe. Each of the eight named Data/activation anchors was independently
omitted and downgraded through the actual CLI, and every Data source is checked
by the inventory negative controls. Zizmor returned zero findings. Composer
validation and source-quality also passed. Package source/API/schema bytes and
existing dependency pins are unchanged; composer.lock required no update.

Probe: real PHP8.4 / Pest5.2.1 / PHPUnit13.3.4 / Infection0.35.6, fresh SQLite and
PG initial coverage/JUnit, four real mutants killed by tests. This is bounded
runner proof, never full-fragment or zone MSI acceptance. The full aggregate
rejects missing receipts. The current recipe preserves source/coverage/process
hashes, actual exits and environment; unknown errors block, timeout remains
valid, invalid syntax/compile requires deterministic retained reproduction.

CI materializes 25 independent jobs (max-parallel=4, threads=1) with a separate
checkout, copied sandbox and corex_msi_<package>_<zone>_test database. Initial
suite timeout is 600s, mutants 10s, job 60 minutes. Every fragment enforces its
own threshold. Existing root coverage/fixed-mutation gates remain separate.
Hosted execution and complete MSI qualification belong to later phase work.

P09.12 owns security95 behavior improvements, P09.13 messaging90, P09.14 other75.
P09.8 independently reviews exceptions/ledgers and final quality/hosted CI.
No later item was started. Foreign P09.9 proposal artifacts and views were left
outside the scoped commit. Current Task runtime is plan.py; stale installed
plan-work projections are not the executable runtime.

Primary upstream pin capture: upload-artifact-pin.txt, read-only git ls-remote
https://github.com/actions/upload-artifact.git refs/tags/v7.0.2. Flag/report
semantics additionally verified against the installed locked Infection sources
and https://infection.github.io/guide/command-line-options.html.
