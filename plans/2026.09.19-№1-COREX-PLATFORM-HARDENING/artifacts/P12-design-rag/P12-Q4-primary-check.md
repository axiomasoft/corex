# P12-Q4 — direct primary-source verification

**Date:** 2026-09-20. **Method:** direct reads of the official URLs returned by
Perplexity. The generic web connector failed with `connection failed`; official
pages were then fetched directly. This limitation is not counted as a successful
web-tool check.

## Verified

- OpenTelemetry messaging span and metric pages label the conventions
  **Development** and describe producer/consumer context propagation and links.
- The OTel trace API requires links at span creation.
- RFC 9457 separates machine problem identity from human-readable detail and permits
  extensions.
- GCP service-account impersonation documentation describes audit-log entries that
  include the calling and impersonated identities for most impersonation flows.
- AWS temporary-credential documentation describes expiry/revocation controls; AWS
  source identity is present in activity and persists across role chaining.

## Not elevated to external fact

- CoreX code/category names and retry semantics.
- The five-item metric allow-list and retry buckets.
- Prohibition of nested CoreX support sessions.
- Whether revocation interrupts an already-running PHP request. P12 promises only
  rejection before the next protected request proceeds.

One raw MCP invocation was blocked by a local command hook false positive because a
query token accidentally matched its process-kill pattern; no command ran. The query
was rephrased and Perplexity completed successfully.
