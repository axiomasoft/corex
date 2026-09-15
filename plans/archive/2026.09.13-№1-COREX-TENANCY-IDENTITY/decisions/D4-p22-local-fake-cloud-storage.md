---
type: owner-decision
id: D4
date: 2026-09-13
status: accepted
item: P2.2
items: [P2.2]
supersedes: []
superseded_by: null
---
# D4 — P2.2 local fake-cloud storage adapter

**Date:** 2026-09-13  
**Status:** accepted  
**Actor:** CoreX owner, explicit `$ task:plan-exec … P2.2` instruction

## Решение

P2.2 may bind `CoreX\Contracts\TenantStorage` in `corex/tenancy` to a
context-scoped adapter using only the already-installed Laravel filesystem
surface. Its durable `local` and `fake-s3` modes are both local test backends:
`fake-s3` models object-prefix semantics under a trusted local root and is not
an S3 SDK, service, credential, or provider-acceptance claim. Ephemeral files
always use their own trusted local root.

The adapter must construct private scoped filesystems directly and must not
return a Stancl-bootstrapped named disk, use `Storage::disk()`, or add a second
tenant prefix. It captures the initialized account at handle acquisition and
the existing scoped filesystem must reject retained handles after A→B or
tenant→central transitions. Non-tenant central disks are outside this adapter.

No dependency, composer-lock, external operation, credential, real S3/IAM,
backup, archive, restore, move, or publication work is authorised. A real
cloud driver remains P6.25 work and requires its own exact dependency and
provider approval.

## Почему

The exact local-only fake backend preserves the tenant isolation contract
without asserting unverified provider semantics or widening dependencies.

## Consequences

P2.2 may add only the tenancy adapter, its private configuration/binding,
focused tests, narrow source-scan allowlist, package changelog, and its proof
receipt. This resolves D2 `FS-DRIVERS` and `DEPENDENCY-SCOPE` only for that
bounded no-new-dependency fake backend.
