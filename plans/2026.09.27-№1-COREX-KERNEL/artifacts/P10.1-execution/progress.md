# PLAN3.P10.1 — candidate checkpoint

Base: c8df0aae (clean worktree). Executor: codex/GPT-6; Task captured frontier/high.
Owner direction: SKM review checks skipped, no independent review verdict claimed.
Task runtime: immutable swissknifeman ba841d9a4a3b629e950db5b4dcfacbd102872189; its archived configs restored in the temporary runtime because provider-projection requires them.

Implemented temporary Composer --with constraints (10); all existing job IDs, action pins, permissions and step counts preserved. Constraint announcement steps retained to meet the item rule prohibiting step removal. Access installs added to quality and package aggregation. Tenancy installs PGDG client 18 and selects its bin path. All nine PG service health checks target corex/corex_test. BC source detection uses grep on PHP files and shares the result with both comparison manifests. Contract guard follows generated PHP snapshots. Arch assertions carry subprocess output. Added committed-candidate ci-local and unreachable-PG test:hermetic commands. Twenty package and one product changelogs describe actual P02–P04 surfaces.

Primary references verified 2026-10-01:
- https://www.postgresql.org/download/linux/ubuntu/ — PGDG signed repository and postgresql-client-18.
- https://getcomposer.org/doc/03-cli.md#update-u-upgrade — temporary update constraint.

Checks already observed: PHP 8.4.26 extensions complete; shell syntax; workflow invariant check; contract guard negative/positive/implementation-only controls; anti-weakening OK; package-docs gate cohort 20 OK. Full lint, BC without rg and affected tests are running. Hosted V1 diagnostic is temporary and must be removed after reading its evidence. GREEN completion and hosted run remain pending.
