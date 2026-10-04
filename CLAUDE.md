<!-- generated from .ai/guidelines/agent-entrypoint.md by scripts/build-agent-docs.sh -->

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
- Keep mAInd manual: recall only relevant prior decisions/handoffs; store one
  source-linked `active-handoff`, while progress, test logs and plan prose stay in the repo.
- Preserve unrelated worktree changes. Validate the affected scope with the
  relevant Composer scripts (`lint:check`, `analyse`, `test`, or package gates).

<!-- swissknifeman:hub:start -->
<!-- swissknifeman hub: stub — skills and task-commands are connected natively; index and priority rules are not duplicated here. -->
<!-- swissknifeman:registry sha=00a04505cea0 skills=232 -->
<!-- Full index: `skiller status` or generate-hub.sh --target <dir> --full-index. -->
<!-- swissknifeman:hub:end -->
<!-- maind:healthcheck hash=a24e86ddc054 (managed by maind — do not edit manually) -->
## ⚠ PRIORITY №1 — connection with mAInd (shared memory + graph)

Before starting work, make sure that the project is connected to the hub **mAInd**. This is critical:
without communication changes this session **will not hit** to shared memory and the graph to history
development, irreparable gaps will appear, and further connections will not be drawn.

1. Check connection: `maind health --cwd`
2. If the status `partial` / `standalone` — fix: `maind health --cwd --fix`
   (will raise the memory daemon and add MCP-hub servers to this project).
3. Proceed with the task only when the status `connected`.

Memory **general** (demon mAInd), scope of this project - `project:corex`; facts about the entire ecosystem - in
scope `global`. Project connections with others: `maind graph --project corex`.
<!-- /maind:healthcheck -->
