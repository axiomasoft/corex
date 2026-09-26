# CoreX reference register

Official sources used by executable compatibility, observability, and security
contracts. Product-specific choices remain in the linked plan decisions and
architecture policies.

| Source | Purpose in CoreX | Verified |
|:--|:--|:--|
| [RFC 6901 — JSON Pointer](https://www.rfc-editor.org/rfc/rfc6901) | Syntax of the stable error `path` field. | 2026-09-20 |
| [RFC 9457 — Problem Details for HTTP APIs](https://www.rfc-editor.org/rfc/rfc9457) | Machine error identity and the boundary between stable fields and human detail; HTTP projection remains owned by P13. | 2026-09-20 |
| [PSR-3 Logger Interface](https://www.php-fig.org/psr/psr-3/) | Static operation messages and structured, redacted context. | 2026-09-20 |
| [W3C Trace Context](https://www.w3.org/TR/trace-context/) | Validation and propagation of `traceparent` and `tracestate` without deriving trace identifiers from business correlation ids. | 2026-09-20 |
| [OpenTelemetry messaging semantic conventions](https://opentelemetry.io/docs/specs/semconv/messaging/messaging-spans/) | Producer-to-consumer context propagation and consumer span links; the adapter pins schema `1.32.0`. | 2026-09-20 |
| [OpenTelemetry metrics supplementary guidelines](https://opentelemetry.io/docs/specs/semconv/general/metrics/) | Bounded metric attribute sets and avoidance of identity dimensions. | 2026-09-20 |
| [OpenTelemetry logs data model](https://opentelemetry.io/docs/specs/otel/logs/data-model/) | Trace/log correlation fields without exporting raw exception payloads. | 2026-09-20 |
| [Google Cloud service account impersonation](https://cloud.google.com/iam/docs/service-account-impersonation) | Preserve caller and impersonated identity in audit provenance. | 2026-09-20 |
| [AWS temporary credential revocation](https://docs.aws.amazon.com/IAM/latest/UserGuide/id_credentials_temp_control-access_disable-perms.html) | Expiry and revocation requirements for temporary impersonation authority. | 2026-09-20 |
| [Standard Webhooks specification](https://github.com/standard-webhooks/standard-webhooks/blob/main/spec/standard-webhooks.md) | Raw signed bytes, attempt timestamps, base64 HMAC secrets and overlapping signatures. CoreX chooses 32-byte keys, a 300-second window and finite retry/replay defaults locally. | 2026-09-26 |
| [OWASP SSRF Prevention Cheat Sheet](https://cheatsheetseries.owasp.org/cheatsheets/Server_Side_Request_Forgery_Prevention_Cheat_Sheet.html) | Reject internal addresses across all DNS answers and disable redirects. Pinning before connect and production/test separation are CoreX controls. | 2026-09-26 |
| [Shopify calculated query cost](https://shopify.dev/docs/apps/build/apis/graphql-admin/rate-limits) | Requested admission and actual-cost refund pattern only. CoreX hierarchical SQL, conservative unknown settlement and defaults are local choices; GraphQL is excluded. | 2026-09-20 (pinned P14 research receipt) |
| [Google AIP-151 — Long-running operations](https://google.aip.dev/151) | Durable progress and separate execution result/error. CoreX cancellation preserves committed effects and unsealed export restarts. | 2026-09-26 |
