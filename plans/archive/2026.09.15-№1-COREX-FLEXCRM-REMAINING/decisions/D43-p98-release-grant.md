---
id: D43
date: 2026-09-18
status: accepted
item: P9.8
items: [P9.8]
supersedes: []
superseded_by: null
---

# D43 — G-RELEASE grant for remaining-cohort publication packet

**Date:** 2026-09-18
**Status:** accepted
**Actor:** repository owner; recorded by Cursor/Composer 2.5
**Evidence:** findings/P9-release-gate-request.md; delivery/remaining-cohort-20260915/publication-packet.json; owner message authorizing P9.8 publication boundary crossing (2026-09-18).
**Supersedes:** —
**Superseded By:** —

## Решение

[OWNER-GATE:G-RELEASE] branch=`remaining-cohort-publish`; scope=`P9.8:p9-publication-packet/1:exact-cohort-operations`; packet_sha256=`1fcf165edfe6df4f281b6690321513c79e81138431e1c602194ab199b55796fc`; owner-message=`owner session 2026-09-18: pass G-RELEASE boundary for sealed remaining cohort; digest rebound PLAN-CLOSE-CI after P9.9 branch-receipt refresh`

Owner grants exact finite cohort publication for all 18 public split repositories in the sealed packet: split tag `0.4.0-alpha.20260918` plus matching dist release asset per package; query-before-retry; no force-push/delete/admin; missing `axiomasoft/corex-*` repositories may be created public; historical E5 four-package refs reused only as verified no-op when bytes already match.

## Explicit limits

- No Packagist registration, no private auth publication, no monorepo tag mutation.
- Stale packet digest or changed operations require re-seal before any write.
- Partial publication remains ledger `pending` until full PUB-01..08 PASS.
