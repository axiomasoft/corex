# P13-Q4 — HubSpot and Notion

**Tool:** Perplexity browser MCP. **Date:** 2026-09-20.

## Result used

- HubSpot's current generation uses date-versioned paths with migration/EOL
  notices; this is not adopted because D2 already selects long-lived majors for a
  self-hosted release train.
- Notion selects an API version by request header and documents additive fields
  across versions, reinforcing tolerant readers even under a pinned version.
- Notion uses response-provided cursors and machine error codes; message text is not
  treated as a stable contract.
- Neither platform's cadence or current date literal enters CoreX snapshots.

## Sources returned

- https://developers.hubspot.com/docs/api-reference/versioning
- https://developers.notion.com/reference/versioning
- https://developers.notion.com/reference/pagination
- https://developers.notion.com/reference/status-codes

