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
<!-- swissknifeman:registry sha=695f52f0312f skills=209 -->
<!-- Full index: `skiller status` or generate-hub.sh --target <dir> --full-index. -->
<!-- swissknifeman:hub:end -->
<!-- maind:healthcheck hash=469aef139a51 (managed by maind — do not edit manually) -->
## mAInd — temporary project context and graph

Memory defaults to this project's private scope `project:corex`. Read `ns:*` or `global`
only for an explicit cross-project request and when the scope is authorized.

Store only short useful temporary context: 48-hour default TTL, 72-hour maximum,
800 characters, at most 8 live entries per scope. Use one replaceable `active-handoff`
for continuity, then forget it when work finishes. Keep plans, decisions, progress,
commit history and durable rules in repository artifacts. Empty memory is normal.

Use `maind health --cwd` for connection symptoms or setup changes; repair with
`maind health --cwd --fix`. A memory outage does not block ordinary repository work.
Project connections: `maind graph --project corex`.
<!-- /maind:healthcheck -->
