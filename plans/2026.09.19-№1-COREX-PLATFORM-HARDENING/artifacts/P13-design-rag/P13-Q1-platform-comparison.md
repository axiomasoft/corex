# P13-Q1 — comparative platform pass

**Tool:** Perplexity browser MCP. **Date:** 2026-09-20.

## Query

> Compare current official documentation for GitHub REST, Slack Web API,
> Twilio, HubSpot, Notion and Contentful for versioning, discovery,
> pagination, idempotency, deprecation, errors and OpenAPI. Mark absent
> guarantees and finish with adopt/adapt/reject guidance for a self-hosted
> operation contract with HTTP and tool projections.

## Result used

- Retry/rate-limit guidance was consistently distinct from idempotency.
- Vendor cursors/page tokens were response-provided and endpoint-specific; none
  established a universal collection snapshot guarantee.
- GitHub/Notion/Contentful expose explicit version selectors in different transport
  locations; this reinforced keeping version selection out of handlers.
- GitHub/Twilio/Contentful publish schema/OpenAPI assets; the answer warned that
  schemas do not contain complete behavioral retry/lifecycle semantics.
- The broad answer omitted part of its expected source list. No omitted claim was
  promoted into a CoreX decision; P13-Q3–Q5 narrowed the evidence.

