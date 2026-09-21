# P12-Q3 — impersonation invariants

**Tool:** Perplexity MCP. **Date:** 2026-09-20.

## Query

> Answer in at most 700 words using current official security or cloud-provider
> guidance only. What invariants should a support-user impersonation session and
> every audit record preserve? Cover original/effective actor, immutable initiator,
> mandatory reason, expiry, nested sessions, revocation, write policy and cleanup.
> Separate directly sourced facts from design inference.

## Result used

Perplexity marked the evidence strength explicitly:

- Direct: GCP service-account impersonation audit records can preserve both the
  calling identity and impersonated identity.
- Direct: AWS source identity is recorded with assumed-role activity and persists
  through chained sessions; temporary credentials are time-bounded and documented
  revocation controls act on temporary sessions.
- Source plus inference: privileged elevation needs justification and bounded
  duration; CoreX should carry the already-required grant reason into audit.
- Design inference: prohibit nested support sessions, keep the initiator immutable,
  preserve existing read-only write policy, and clean session/auth state after a
  failed target login.

The answer did not prove a provider-independent normative rule named “no nesting” or
the exact CoreX write policy. Those are local security decisions, not attributed to
AWS/GCP.

## Primary sources recovered by the follow-up

- https://cloud.google.com/iam/docs/service-account-impersonation
- https://cloud.google.com/iam/docs/create-short-lived-credentials-direct
- https://cloud.google.com/iam/docs/service-account-permissions
- https://docs.aws.amazon.com/STS/latest/APIReference/API_AssumeRoleWithWebIdentity.html
- https://docs.aws.amazon.com/IAM/latest/UserGuide/id_credentials_temp_control-access_disable-perms.html
