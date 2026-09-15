---
type: workflow
id: D9
date: 2026-09-14
status: accepted
item: P4.1
items: [P4.1]
supersedes: []
superseded_by: null
---
# D9 — Delegated local fixed-source preparation

**Date:** 2026-09-14
**Status:** accepted
**Actor:** Dmitry Vostrikov · current owner delegation; technical choices recorded by plan-run/frontier/high · gpt-5.6-sol.
**Evidence:** RAG:— `brief/P4.1-owner-delegation.md#technical-choices`; exact P4.1 owner invocation; D20's unchanged upstream refs. RAG:✅ primary GitHub commit API and anonymous exact-commit Git fetch; official Composer schema/repositories/versions and Packagist version immutability, checked 2026-09-14.
**Supersedes:** —
**Superseded By:** —

## Решение

Qualify P4.1 as routine local preparation under the owner's delegated technical choices.
The proposed maintainer is Dmitry Vostrikov in his existing CoreX maintainer role; target
organization is the previously selected `axiomasoft`. Proposed repositories/package names
are `axiomasoft/stancl-tenancy` and `axiomasoft/stancl-jobpipeline`, with immutable local
candidate versions/tags `4.0.0-alpha1`/`v4.0.0-alpha1` and
`2.0.0-alpha1`/`v2.0.0-alpha1`. These are independent fork versions, not upstream releases.
No `replace`, `provide`, GA alias, new runtime requirement or PHP/Laravel constraint change.
Tenancy's only dependency-link change is the explicit exact owned jobpipeline provider.
The selected upstream commits remain exactly those in D20.

Historical D2 STANCL-OWNER/D3 observations remain unchanged. For P4.1 only, verified
public upstream snapshots and the delegated local map replace the missing preparation
inputs. P4.2 adoption still requires its own invocation and qualified available artifact/channel;
actual external creation/publication remains separately gated. No upstream ownership or
existing target repository is claimed. Upstream authorship and MIT notices remain intact.

The source approval record means this durable owner-request/delegation provenance plus
the digest-qualified local source map. No cryptographic owner signature or externally
approved release is claimed. Amend the P4.1 wording transparently to remove that ambiguity
while retaining exact source/code/license verification and all external boundaries.
Extend its Files only with the reproducible local source/archive/metadata evidence already
required by Scope Included; no library code or consuming manifest/lock is writable.

## Почему

The former unresolved prose prevented even routine preparation (`AUTHORITY_CONTRACT_GAP`).
The current owner explicitly delegates the technical choices. Separate owned identities and
tagged prereleases make provenance visible without silently satisfying arbitrary upstream
package requirements. Full Git snapshots retain export-ignored test/support files, unlike
the smaller codeload archive. Deterministic local bytes can be verified before external action.

## Consequences

Re-seal P4.1 and regenerate its bundle/state before execution admission. Preserve all
previous decisions, product bytes, original root/package locks and unrelated dirty work.
Commit the previously uncommitted execution input carriers before product execution;
then use one scoped product/closure commit. Local preparation GREEN is not cold consumer
install, current CI runner GREEN, external mirror authority, or P4.2 adoption approval.
