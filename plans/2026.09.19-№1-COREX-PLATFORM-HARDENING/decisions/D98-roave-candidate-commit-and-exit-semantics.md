---
id: D98
date: 2026-09-20
status: accepted
item: P3.4
items: [P3, P3.4, P17.10]
supersedes: [D18]
superseded_by: null
---
# D98 — Roave compares synthetic per-package commits and only exit 3 means a BC break

**Actor:** plan-designer/codex-gpt-5.6-sol/high

**Evidence:** `RAG:✅` Roave 8.22.0 has no package positional argument, resolves the
repository and Composer configuration from CWD, accepts `--from`/`--to`, and returns
exactly 3 for detected BC changes. `RAG:—` DA07 reproduced the old wrapper as a CLI
failure hidden by the experimental report-only branch.

## Решение

P3.4 builds a disposable git repository per package with two commits. The base commit
contains the package subtree from `BC_CHECK_BASE`; the candidate commit contains the
current worktree snapshot, including tracked modifications, deletions and non-ignored
untracked files, plus a root comparison `composer.json` whose production autoload points
only at that package. Roave runs from that repository root with explicit commit ids.

Exit 0 is compatible, exit 3 is a semantic BC break, and every other exit or missing
result is an infrastructure/config/install/reflection failure. Only exit 3 is
report-only for `experimental`; every infrastructure failure is RED for every package.

## Почему

This makes package selection a property of the compared Composer roots, not an invented
CLI argument, and makes the force-stable planted deletion part of the actual candidate
revision instead of invisible worktree state beside `HEAD`.

## Consequences

P17 consumes the classified receipt and rejects skipped/unknown infrastructure results.
Temporary repositories and alternate indexes are removed in `trap`; the source index,
worktree and refs are byte-unchanged.
