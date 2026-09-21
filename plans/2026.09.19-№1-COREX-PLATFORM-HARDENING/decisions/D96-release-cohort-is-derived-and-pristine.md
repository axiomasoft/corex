---
id: D96
date: 2026-09-20
status: accepted
item: P17.1
items: [P17, P17.1, P17.2, P17.3, P17.4, P17.5, P17.6, P17.7]
supersedes: []
superseded_by: null
---
# D96 — Release cohort is derived once and all acceptance installs are pristine

**Actor:** plan-designer/codex-gpt-5.6-sol/high
**Evidence:** RAG:— D89/D90 may change package identities; existing distribution scripts already
export package-only archives; RAG:✅ Composer/Laravel behavior in `research/P17-design-rag.md`.

## Решение

P17 derives live CoreX package names, paths, dependencies and distribution channels after P15/P16,
then freezes them in one digest-qualified `corex-release-cohort/1` manifest. Every extension,
client, consumer, profile and upgrade proof installs only its exported dist archives into a fresh
temporary consumer with a fresh Composer home. Path repositories, root `vendor`, sibling source
paths and monorepo autoload are prohibited evidence.

## Почему

A hardcoded pre-P15 list can silently omit a renamed package. A green monorepo suite can silently
borrow root `autoload-dev`, unexported files or sibling path repositories. One frozen cohort and one
install boundary make all P17 proofs comparable.

## Consequences

`flex/crm` remains a consumer and `import-export` remains a tombstone. A private package stays
private. Unsupported PHP/Laravel combinations are excluded only by parsed constraints with a
recorded reason. Any workspace path in installed Composer metadata is red.
