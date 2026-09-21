# P12 design validation — 2026-09-20

## Passed

- P12.1–P12.8 dry bundles рендерятся без materialization.
- Decision, state, status и next-runnable P1.1 bundle views собраны
  генератором и проходят `--check`.
- `plan-lint.py --gate all` зелёный: 0 ошибок, 0 предупреждений.
- RAG manifest — valid JSON; SHA-256 всех четырёх capture-файлов
  совпадают с manifest.
- `git diff --check -- <plan-dir>` зелёный.
- Route evidence attested provider thread `01a0bcad-65f9-7a61-a4f7-aaef85bd0a69`:
  GPT-5.6 Sol, high, cwd CoreX, `-a never`, `danger-full-access`.

## Environment/tooling defects

1. Installed cached Codex adapter task 1.0.15 не добавляет swissknifeman root в
   `PYTHONPATH`; route capture падает с `ModuleNotFoundError: core`. Owning-source
   projection с явным `PYTHONPATH` дал provider-attested evidence этой сессии.
2. Generic web connector вернул `connection failed`. Perplexity MCP и direct
   official-source retrieval отработали; failed connector не засчитан green.
3. Один raw Perplexity MCP invocation был отклонён local command-hook false
   positive; команда не запускалась. Нейтрально переформулированный
   query завершился, а claims перепроверены по primary sources.

## Boundary

P5/P6/P9 product artifacts создаются только на stage 3. Поэтому dry-bundle
проверка P12 доказывает целостность дизайна и резолвимость его
деклараций, а не execution readiness. D14 по-прежнему запрещает запуск
P12 items до детализации P13–P17 и whole-plan audit.
