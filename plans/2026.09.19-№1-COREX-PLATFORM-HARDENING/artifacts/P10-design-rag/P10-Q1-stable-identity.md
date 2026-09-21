# P10-Q1 — stable attribute identity

- Query: `RFC 9562 UUID version 7 official`; `site:docs.pimcore.com data object classification store key id attribute definition`
- Accessed: 2026-09-20
- Transport: web search + direct primary-source open
- Sources:
  - https://www.rfc-editor.org/rfc/rfc9562.html
  - https://api.laravel.com/docs/12.x/Illuminate/Support/Str.html
  - https://docs.pimcore.com/platform/next/Pimcore/Objects/Object_Classes/Data_Types/Classification_Store/

RFC 9562 §5.7 defines UUIDv7 with a Unix-epoch time-ordered field. Laravel 12 exposes
`Str::uuid7()`. Pimcore models classification keys as definitions separate from
object values. CoreX inference: use explicit UUIDv7 identity independent from mutable
key; external sources do not prescribe CoreX conflict/evolution policy.
