---
type: workflow
id: D17
date: 2026-09-15
status: accepted
item: P5.3
items: [P5.3]
supersedes: [D16]
superseded_by: null
---

# D17 — Bounded P5.3 remediation and accepted credential deviation

**Date:** 2026-09-15
**Status:** accepted
**Actor:** repository owner; recorded by Codex/gpt-5.6-sol.
**Evidence:** RAG:— brief/P5.3-remediation-owner-messages.md#p53-remediation-confirmation; findings/P5.3-remediation-proposal.md; findings/P4.1-source/source-map.json; findings/P5.3-publication-receipt.md.
**Supersedes:** D16 for future P5.3 dispatch only; original grant/actions remain historical.
**Superseded By:** —

## Решение

[OWNER-GRANT:PUBLICATION-REMEDIATION] branch=`publication-remediation`; scope=`axiomasoft/corex-core, axiomasoft/corex-modules, axiomasoft/corex-tasks, axiomasoft/corex-tenancy read-only P5.2 reconciliation; axiomasoft/stancl-jobpipeline, axiomasoft/stancl-tenancy exact P4.1 create/push/tag/release and Actions disable; one-use academici OAuth exception`; owner-message=`brief/P5.3-remediation-owner-messages.md#p53-remediation-confirmation`

## Exact bound results

Preserve all four P5.2 CoreX refs/releases/assets. Publish only sealed P4.1
jobpipeline candidate 683de91ca7f80eb77f24b6b0b97733eecf726ac7 / tree
cf4193e321e0a280de8c7cdbefc72f0094050e22 / tag v2.0.0-alpha1 / ZIP SHA256
ab05cafca18259875771d9ccd8321b8a1058d048c6b0c0261effc2a1923ac546,
then tenancy candidate 7d596a145ee1e8251a64785e66ff40cf24c92078 / tree
e85117c6967393b4e88f86fdd39b41065aebb050 / tag v4.0.0-alpha1 / ZIP SHA256
48b6d7a9dc15ebd5d98dcab42844226421709113ff7c1347c10b01f876045d99.
For each: create absent public mirror, disable Actions before first push,
push exact main/tag, create prerelease with one exact ZIP. Leave Actions disabled.

Owner explicitly permits the existing academici OAuth once for this exact
two-provider operation set and Actions-disable settings, not a narrow token
capability claim. No further resource, permission broadening or token provisioning.
The initial broad-OAuth dispatch remains a material historical deviation;
irreversible action history cannot be repaired by deleting or overwriting releases.

## Почему

The owner explicitly resolved the named authority boundary after actual
provider and workflow inspection; all candidate fingerprints stay sealed.

## Contract delta and validation

P5.3 current acceptance validates exact D17 operation authority and accepted
credential exception, preserving the initial RED receipt without reclassification.
The original spec SHA256 ec4513ac577bdeb7ff39895c90bb23e7aa456a0c7be9c47c828697a0520f93b1
is archived in findings/P5.3-original-spec.md. Public lower-provider resolution,
negative fixtures and integrated idempotency checks remain required; no green
carrier is waived. A delivered result closes with deviations only if current
validations are green and recoverable-validation-gap/v1 captures immutable
historical credential-policy failure.

## Consequences

No source/version/ZIP change. No public auth/crm/private graph, Packagist,
secrets, force-push/delete, DNS/TLS, org-wide settings, workflow dispatch,
Actions enablement or unrelated repository mutation. Real dispatch uses the
tested guard and exact bound evidence; read-only matching replays may verify
no-op without extra writes. Any collision/unknown mismatch fails closed.
Local scripts/tests and scoped findings/contract bookkeeping are permitted
to implement and verify this owning remediation. Same-session execution was
explicitly requested; no unnecessary fresh design-root launch.
