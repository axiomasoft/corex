# P13-Q2 — HTTP deprecation standards

**Tool:** Perplexity browser MCP. **Date:** 2026-09-20.

## Result used

- RFC 9745 is the Standards Track definition of `Deprecation`; its value is a
  Structured Field Date and describes the resource represented by the response.
- RFC 8594 defines `Sunset` as an HTTP-date hint about likely unavailability; it
  is not a guarantee of availability before or exact behavior after that date.
- RFC 5829 defines `successor-version` as a version-navigation link relation; P13
  uses it only when a successor is actually declared.

## Sources returned

- https://www.rfc-editor.org/rfc/rfc9745
- https://www.rfc-editor.org/rfc/rfc8594
- https://www.rfc-editor.org/rfc/rfc5829

