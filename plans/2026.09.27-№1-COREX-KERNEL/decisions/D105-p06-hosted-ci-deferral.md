---
id: D105
date: 2026-10-04
status: accepted
item: P06
items: [P06]
supersedes: []
superseded_by: null
---
# D105 — p06-hosted-ci-deferral (accepted)

**Actor:** repository owner, explicit current-conversation instruction; recorder: Codex.
**Evidence:** GitHub Actions run `37204345391` for `4137d2412497aa9348cf19322b26f1d074b5ad2c`; each required job has duration `0 ms` and GitHub reports that account payments or the spending limit prevent it from starting. Owner instruction: «забей на CI там нет лимитов, пока пропускаем».

## Decision

For P06 only, waive the D73/D91 hosted-CI acceptance gate for the current final commit. Close P06 as `🟠 Done with deviations`; do not present run `37204345391` as a successful CI result.

The previous P06.5 model-route waiver and the absent hosted-CI execution remain separate known deviations. Before a release, a P07 GREEN prerequisite, or any claim of hosted acceptance, restore GitHub Actions capacity and obtain a successful `CI` run on the applicable final commit.

## Rationale

The phase's product validation and P06.5 technical verdict are green, while all hosted jobs were prevented from starting by a GitHub account-level capacity failure. The owner explicitly elects to defer that external acceptance gate rather than characterize it as product failure.

## Consequences

`phases/P06/P06.closed.md` records a deviation close and links the failed hosted run. This does not supersede D73 generally and does not relax P07's independently stated requirement for P06 closed GREEN on hosted CI (D94).
