# P12-Q1 — error contract

**Tool:** Perplexity MCP. **Date:** 2026-09-20.

## Query

> Answer in at most 700 words using current official standards and published
> API guidelines only. How should a public API combine stable machine-readable
> error codes with RFC 9457 Problem Details? Address category, field path,
> retryable, details, human message stability, HTTP status, and compatibility
> rules. Separate normative facts from design inference and include exact URLs.

## Result used

Perplexity separated standard facts from design inference:

- RFC 9457 defines `application/problem+json` and the standard members
  `type`, `title`, `status`, `detail`, `instance`; `type` is the primary problem
  identifier, while the HTTP response status remains authoritative.
- `detail` is human-readable and clients should not parse it for machine behavior.
- Extension members are permitted and unknown members must be tolerated. The RFC
  includes validation-error examples with pointers into request content.
- Retry timing belongs to HTTP semantics such as `Retry-After`; a boolean alone
  does not authorize an unsafe automatic retry.
- Design inference accepted for CoreX: retain a package-owned stable `code` beside
  category/path/retryability/details, keep message non-contractual, and let P13
  project the descriptor into both the existing envelope and Problem Details.

## Primary sources returned

- https://www.rfc-editor.org/rfc/rfc9457
- https://www.rfc-editor.org/rfc/rfc9110

The query did not establish CoreX-specific code names, PHP types or compatibility
snapshot mechanics; those remain D60–D61.
