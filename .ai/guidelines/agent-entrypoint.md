# CoreX

- Edit this context only in `.ai/guidelines/agent-entrypoint.md`, then run
  `bash scripts/build-agent-docs.sh`.
- This is a Laravel package monorepo. Keep `corex/*` useful without Flex products
  and follow the package boundaries and gates in `docs/dev-doctrine.md`.
- Put package code and its tests under `packages/<name>/`; the root test suite
  contains cross-package architecture and quality gates.
- Treat public contracts as SemVer surfaces. Check compatibility before changing
  interfaces, service providers, migrations, or exported behavior.
- For plan work, start at `plans/ACTIVE.md`; otherwise open only the documents
  needed for the task.
- Preserve unrelated worktree changes. Validate the affected scope with the
  relevant Composer scripts (`lint:check`, `analyse`, `test`, or package gates).
