# P10-Q2 — online schema evolution

- Query: `site:docs.gitlab.com development database batched background migrations expand contract rename column dual write`
- Accessed: 2026-09-20
- Transport: web search + direct primary-source open
- Source: https://docs.gitlab.com/development/database/avoiding_downtime_in_migrations/

GitLab documents concurrent column rename/type change as multiple steps: create a
temporary representation and keep it in sync, migrate/cast data, and perform later
post-deployment cleanup. It also warns that large-table changes need bounded background
work. CoreX inference: an attribute rename/type change needs explicit expand,
dual-write, resumable backfill, verification and delayed contract.
