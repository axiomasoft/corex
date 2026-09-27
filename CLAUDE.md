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
<!-- swissknifeman hub: stub — скиллы и task-команды подключаются нативно; индекс и правила приоритета здесь не дублируются. -->
<!-- swissknifeman:registry sha=5b8274432dfe skills=232 -->
<!-- Полный индекс: `skiller status` или generate-hub.sh --target <dir> --full-index. -->
<!-- swissknifeman:hub:end -->
<!-- maind:healthcheck hash=2bb4346a5dd4 (managed by maind — не редактировать вручную) -->
## ⚠ ПРИОРИТЕТ №1 — связь с mAInd (общая память + граф)

Перед началом работы убедись, что проект подключён к хабу **mAInd**. Это критично:
без связи изменения этой сессии **не попадут** в общую память и граф — в истории
разработки появятся невосстановимые пробелы, и дальнейшие связи будет не вывести.

1. Проверь связь: `maind health --cwd`
2. Если статус `partial` / `standalone` — почини: `maind health --cwd --fix`
   (поднимет демон памяти и допишет MCP-серверы хаба в этот проект).
3. Приступай к задаче только при статусе `connected`.

Память **общая** (демон mAInd), scope этого проекта — `project:corex`; факты всей экосистемы — в
scope `global`. Связи проекта с остальными: `maind graph --project corex`.
<!-- /maind:healthcheck -->
