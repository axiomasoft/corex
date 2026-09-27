---
id: D128
date: 2026-09-27
status: accepted
item: P17.11
items: [P17.11]
supersedes: []
superseded_by: null
---
# D128 — Authorized producer remediation for release preflight

**Actor:** owner + plan-exec/sol/effort-unverified
**Evidence:** Exact owner follow-up `доделай` after the P17.11 NO-GO disclosure. The earlier explicit Sol authorization and model-verification waiver persist.

## Решение

Resume P17.11 in the current root. Authorize the narrow producer remediation needed to finish its unchanged release acceptance: README topics and public examples, BC harness dependency/autoload repair, candidate-aware extension/profile/upgrade tooling, local stability metadata and local source/evidence anchors. Preserve public contracts, database schema, package identity and distribution visibility. Do not weaken gates, skip supported lanes or replace a real result with fixture evidence. Preserve unrelated working-tree changes using an isolated local qualification checkout where necessary.

This bounded authorization supersedes P17.11's product-repair exclusion only for these reproduced release blockers; it does not authorize new runtime functionality or unrelated producer refactoring. P17.12 and all remote writes remain outside scope and require their exact G-RELEASE.

## Почему

The owner explicitly asks to finish after seeing the concrete DOD-22/24/25 blockers. Returning another routine scope question would contradict that instruction. The release remains fail-closed until every declared validation is current and green.

## Consequences

P17.11's first attempt remains blocked in immutable history. A second execution attempt resumes with D128 as its authorization source. No phase re-entry, new design audit or release grant is inferred.
