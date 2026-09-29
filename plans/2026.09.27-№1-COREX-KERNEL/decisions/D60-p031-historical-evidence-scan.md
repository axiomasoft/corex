---
id: D60
date: 2026-09-29
status: accepted
item: P03.1
items: [P03.1]
supersedes: []
superseded_by: null
---
# D60 — P03.1 source scan preserves immutable audit evidence

**Actor:** author-choice executor/codex/gpt-6-sol/high under plan §2 B6 (routine repository-local choice).
**Evidence:** `artifacts/P03.1-execution/historical-references.log`; all remaining matches of
`git grep -n "tenancy-structure" -- ':!plans'` are in the committed P03 design audit's
`worktree-anchor.json`, `diagnostic-candidate.patch` and `dry-diff-stat.txt`. Those files record the pre-change tree;
D58 explicitly preserves the original audit artifacts. Active source references disappear
with the gate's retirement. No package, rule, baseline entry or test is waived.

## Решение

P03.1's retirement scan excludes both `plans/` and `audits/`:
`git grep -n "tenancy-structure" -- ':!plans' ':!audits'` must be empty (Git exit 1).
The deleted gate/fixture existence checks and documentation exception-link scan remain binding.
Historical audit references are retained; editing their bytes would destroy the evidence rather
than demonstrate retirement. P03.1 Validation and Inputs record this amendment explicitly.

The full-tree Pint observation is an inherited, non-gating foreign baseline for P03.1:
`artifacts/P03-design/scripts/p03-adapters.php` is unchanged from HEAD and is exclusively a
P03.2 transfer helper, absent from P03.1 Files and its gate copies. Its four formatter findings
remain visible in `artifacts/P03.1-execution/lint.log`. P03.1's declared Validation still all
must pass. P03.2 owns formatting that helper before its full lint check; no lint config or rule
is relaxed and no baseline/tool artifact is silently reformatted in this item.

## Почему

The retirement check proves the absence of live consumers. An immutable pre-change snapshot
necessarily names the retired files and is owned by the audit, so its historical mentions
cannot satisfy an empty repository-wide scan. Keeping the source boundary explicit preserves
both the retirement invariant and the original audit's reproducibility.

## Consequences

Only the scope of the obsolete-name source scan changes; active code, composer and CI references
remain forbidden. Independent full review covers this amendment and the resulting candidate.
P03.2 receives the exact inherited formatter findings as its owning continuation. All runtime
and module-layout checks remain unchanged.
