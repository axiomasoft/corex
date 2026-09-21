# P13-Q5 — GitHub and Contentful

**Tool:** Perplexity browser MCP. **Date:** 2026-09-20.

## Result used

- GitHub conditional GET with ETag/Last-Modified is a read optimization, not a
  global mutation idempotency promise.
- Contentful documents optimistic locking with `X-Contentful-Version`; this is a
  concurrency precondition, not replay deduplication.
- Both expose explicit version selection and published schema/OpenAPI material.
  P13 adopts pinned/diffed snapshots but keeps behavioral guarantees in
  `operations.json`.
- No global POST idempotency guarantee from either platform was accepted.

## Sources returned

- https://docs.github.com/en/rest/about-the-rest-api/api-versions
- https://docs.github.com/en/rest/using-the-rest-api/using-pagination-in-the-rest-api
- https://docs.github.com/en/rest/about-the-rest-api/about-the-openapi-description-for-the-rest-api
- https://www.contentful.com/developers/docs/references/content-management-api/
- https://github.com/contentful/open-api

