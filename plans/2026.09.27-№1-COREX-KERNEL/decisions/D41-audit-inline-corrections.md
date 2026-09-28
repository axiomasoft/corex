---
id: D41
date: 2026-09-28
status: accepted
item: P01.2
items: [P01.1, P01.2, P01.3]
supersedes: []
superseded_by: null
---
# D41 — audit-inline-corrections (accepted)

**Actor:** auditor/fixer/codex/gpt-6-sol/high, owner request 2026-09-28: «и если что найдешь сразу фикси,
больше дизайнов не будет. после тебя сразу даем на исполнение»; routine corrections under plan §2/B6.
**Evidence:** RAG:— `findings/design-audit-autofix-2026-09-28-check.py`; current source declarations,
`scripts/anti-weakening-gate.sh`, `WriteApiSnapshots::contracts`, `ContractSnapshotBuilder::documents`.

## Решение

1. Correct executable P01 examples inline, preserving P00 and package architecture; seal the final candidate
   with the existing finish operation. No extra design phase or successor execution is authorized.
2. Golden extracts declared source names with PHP tokens and a process-local Composer classmap, including
   historical classmap/multiple-declaration files. Root dev install includes access and its already approved
   AzGuard upstream/VCS, so the complete all-package surface has its interfaces available.
3. Preserve float constants/defaults in JSON; generation succeeds without test skips. Wire supplies the existing
   public-error fixture, checks command exit and four documents, and always cleans its own temporary directory.
4. D40 symmetric projection remains the comparison. Normalize Internal flags and interface ordering after
   name projection. Ignore only the derived hash of a known operation record, whose semantic input fields
   remain compared. No arbitrary API member/schema key is discarded.
5. Allow first creation of the ownership baseline when the base tree has no such file; thereafter reject
   set growth. Stage cleanup reconciles root and tenancy even after update failure; analyser never runs
   against a failed install. Ceilings start with empty maps and are measured by `--tighten`.
6. Keep independent review by another model as a completion requirement; the fixing root does not certify
   its own changes. Review is bounded to the current candidate and reuses unchanged P00 evidence.

## Почему

Path-derived FQCNs reproduce a redeclaration fatal before baseline generation; the old wire command omits
its mandatory fixture. Baseline creation also conflicted with existing anti-weakening rules. These are
executable design defects with deterministic local corrections, not new architectural alternatives.

## Consequences

P01 item boundaries and execution routes remain; corrections belong to their existing items. The independent
review and current-fingerprint gate must finish before recommending P01.1 execution. Product code, system
installation, PostgreSQL role creation, publication and push are outside this audit. O-1/O-2 preflight stays
inside P01.1 and O-3 remains the declared external boundary.
