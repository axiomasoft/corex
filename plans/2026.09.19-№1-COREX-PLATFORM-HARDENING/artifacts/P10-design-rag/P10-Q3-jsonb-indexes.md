# P10-Q3 — JSONB and ordered indexes

- Query: `site:postgresql.org/docs/current jsonb indexing expression indexes partial indexes order by btree`
- Accessed: 2026-09-20
- Transport: web search + direct primary-source open
- Sources:
  - https://www.postgresql.org/docs/current/datatype-json.html
  - https://www.postgresql.org/docs/current/indexes-expressional.html
  - https://www.postgresql.org/docs/current/indexes-partial.html
  - https://www.postgresql.org/docs/current/indexes-ordering.html

PostgreSQL documents GIN for JSONB key/value containment, targeted expression indexes
for specific paths, partial indexes only when query predicates imply the index
predicate, and B-tree as the only current index type that directly emits sorted output.
Expression indexes increase insertion/non-HOT update cost. CoreX inference: default
to no index; require a measured query shape for a typed expression/partial B-tree;
keep promoted typed columns outside attributes table ownership.
