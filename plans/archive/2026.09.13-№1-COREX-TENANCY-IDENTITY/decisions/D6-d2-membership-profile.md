---
type: owner-decision
id: D6
date: 2026-09-13
status: accepted
item: P4.2
items: [P4.1, P4.2, P4.3, P5.1, P6.2]
supersedes: []
superseded_by: null
---
# D6 — D2 membership profile accepted for design; implementation withheld

**Date:** 2026-09-13
**Status:** accepted (design profile only)
**Actor:** CoreX owner; recorded by Codex
**Evidence:** Explicit owner task:design instruction in this thread, 2026-09-13; [profile](../findings/D2-membership-profile.md); [discovery](../findings/D2-source-discovery.md); [primary sources](../findings/D2-reference.md).
**Supersedes:** — (D2 remains the binding prerequisite ledger; this append-only record refines its membership rows)
**Superseded By:** —

## Решение

Approve the design profile: central membership ledger sole writer; OAuth client_credentials with mTLS and certificate-bound token; certificate registration scoped to one account; acting_identity audit-only; signed event only triggers authoritative pull; tenant-local users.is_active and database sessions for revoke. Approve RS256, access TTL300s/no refresh, JWT skew30s, envelope TTL300s, signing rotation90d, JWKS cache300s/prepublication300s/public-key retention600s after last signature, certificate validity30d/replacement day20/overlap10min. Authoritative reads include commits completed before read start; refresh/apply serialize per identity/account. Delivery monthly p99.9≤10s, retry24h, reconciliation≤30s, pull5s/apply5s; positive freshness lease45s/fail closed; new authorization decisions deny after central revoke commit+60s until authoritative regrant. Session cleanup≤60s has an explicit local fault envelope; every entrance checks active state, invalidates remember credentials and prevents pre-revoke credential resurrection after regrant.

**D2 is NOT released for implementation.** No code, runtime configuration, dependency/schema change, central registration/deployment or commit is authorized. Accepted profile is not P4 proof, deployment qualification, public API approval or production SLO receipt. Do not repeat the architecture/default interview.

## Почему

The owner settled the architecture/default choices and authorized source discovery/documentation only. The design's timing and credential lifecycle are necessary to make the selected revoke bound reviewable: leases begin before authoritative reading, JWT skew does not extend them, rapid revoke/regrant requires a durable revoke-generation watermark, and final session/remember writes require a durable credential fence. Exact mechanisms/DDL/public signatures remain unapproved inputs.

## Consequences

[Profile](../findings/D2-membership-profile.md) is the controlling self-contained design continuation for P4. It records FE-60 (45s lease +≤5s local dispatch/fence admission +≤10s DB recovery/cleanup commit) as a proposed operational qualification envelope, not measured capability. Central/network/event outage may remain unbounded for the fail-closed lease path; unbounded local DB outage cannot satisfy physical cleanup60.

Discovery recovers the owning Flex D13 chosen issuer https://flexl.ru and D33 rename FLEXID→FLEX; no verified deployment metadata/JWKS/resource/certificate registration was found. Planned endpoints are separate from effective values. DI-01…DI-34 list exact unresolved deployment/API/DDL/operational inputs and evidence owners. PKI registration and credential generation/local epoch mechanisms are design recommendations, not discoveries or additional owner-approved defaults.

D2 CENTRAL-TRUST/PROJECTION-OWNER/SESSION-BINDING/SWITCHER-AUTHORITY remain unqualified for runtime dispatch. PUBLIC-API, DEPENDENCY-SCOPE, source sealing, schema and release gates retain their own scope. Existing dirty candidate implementation is preserved and cannot be treated as approved by this record. Historical D2/P1.2 remain evidence; conflicting membership proposals give way to this profile. Current Next is another task:design qualification session, not task:plan-exec P4.
