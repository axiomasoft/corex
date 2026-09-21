# P11 design validation — 2026-09-20

## Passed

- P11.1–P11.8 dry bundles рендерятся без materialization.
- Decision, state и status views собраны генератором и проходят `--check`.
- RAG manifest — valid JSON; digests проверяются отдельно.

## Environment/tooling defects

1. Installed cached Codex adapter task 1.0.15 не добавляет swissknifeman root в
   `PYTHONPATH`; route capture падает с `ModuleNotFoundError: core`. Owning-source
   projection имеет исправление и дал provider-attested evidence этой сессии.
2. `verify-plan-citations.py --strict <CoreX-plan>` выводит repo root из checkout
   swissknifeman, а не из переданного внешнего плана. Он сообщает 8
   `file not found` для фактически существующих CoreX paths. Проверка не
   засчитана green; owner ремонта — Task citation verifier.
3. Длинный Perplexity query трижды превысил 10 s browser wait.
   Сокращённый query успешен; его ответ без links принят только
   после отдельной проверки official Symfony/Laravel pages.
