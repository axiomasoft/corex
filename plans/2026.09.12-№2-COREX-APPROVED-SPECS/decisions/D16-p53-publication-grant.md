---
type: workflow
id: D16
date: 2026-09-15
status: superseded
item: P5.3
items: [P5.3]
supersedes: []
superseded_by: D17
---

# D16 — Exact P5.3 publication grant

**Date:** 2026-09-15
**Status:** superseded for future dispatch by D17; originally accepted.
**Actor:** repository owner, recorded by Codex/gpt-5.6-terra.
**Evidence:** RAG:— `brief/p53-publication-owner-message.md#p53-publication-confirmation`; P5.2 dossier; authenticated live target preflight recorded in roadmap.
**Supersedes:** —
**Superseded By:** D17 for future dispatch only; original authority/action history retained.

## Решение

[OWNER-GRANT:PUBLICATION] branch=`publication-exact-result`; scope=`only axiomasoft/corex-core, axiomasoft/corex-modules, axiomasoft/corex-tasks, axiomasoft/corex-tenancy; exact dossier-bound create/push/tag/release/Packagist/secret actions`; owner-message=`brief/p53-publication-owner-message.md#p53-publication-confirmation`

## Почему

P5.2 completed the exact local artifact and proof dossier. Authenticated
preflight returned HTTP 404 for each proposed target; the owner explicitly
approved their creation and the bounded release operation.

Create only the four absent `axiomasoft` mirrors. Publish the immutable P5.2
source commit `9152dde68ccace20d111f044aecf132bb8d49861` using release version
`0.1.0-p4.3` and tag `v0.1.0-p4.3`, then create GitHub Releases with the
dossier-bound archive checksums. Packagist registration and token creation or
injection are excluded.

## Consequences

P5.3 still performs live ref reconciliation immediately before each mutation.
A collision, changed source/tree/checksum, failed proof or unknown write outcome
stops the affected resource without force-push, deletion or retry by assumption.
