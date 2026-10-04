# Session routing — pool, native launch и review runbook

Единственное конкретное расписание — [`roadmap.md`](roadmap.md) `execution-sheet/v1`. Адресные принятые решения владельца имеют приоритет над default selectors листа: D85 назначает Sonnet 5.5 для строки 18 (B05-A, P05.1/P05.2). Этот файл — pool, mapping и
runbooks (по образцу chatom PLAN2 `session-routing.md`).

## Пул и роли

Источник ролей — Task `packages/task/docs/reference/provider-model-roles.md` и `configs/command-routing.json`.

| Provider | Model | Role в PLAN3 | Effort |
|:--|:--|:--|:--|
| claude-code | `opus` (claude-opus-5-5) | автор плана: дизайн фаз, frontier-решения P00, ведение процесса (D21) | `high` |
| claude-code | `sonnet` (claude-sonnet-5-5) | исполнение и owning auto-fix P05.1/P05.2 (B05-A), owner D85 | `high` |
| codex | `gpt-6-sol` | стандартная implementation и owning auto-fix | из sheet |
| codex | `gpt-6.1-sol` | текущий независимый design audit после D79, owner override D80 | `xhigh` |
| codex | `gpt-6-astra` | frontier execution P00 и review/audit | `high`/`xhigh` |
| cursor | `grok-4.6` | bounded rename/tools/snapshots, ревью механических items | `high` |
| cursor | `grok-4.7` | frontier execution и API/SPI/transactions/security ревью | `high` |

`auto` и модели вне пула запрещены. Reviewer model всегда ≠ executor model.

## Native launch

Codex (implementation/high):

```bash
codex -C "$(git rev-parse --show-toplevel)" -m gpt-6-sol -c 'model_reasoning_effort="high"' -a never -s danger-full-access '$ task:plan-run 2026.09.27-№1-COREX-KERNEL <exact-items-from-sheet>'
```

Cursor (effort — нативным селектором до запуска):

```bash
agent --workspace "$(git rev-parse --show-toplevel)" --model grok-4.7 --force --sandbox disabled --trust --approve-mcps 'task:plan-run 2026.09.27-№1-COREX-KERNEL <exact-items-from-sheet>'
```

Claude Code для B05-A (D85): `claude --model claude-sonnet-5-5`, затем `task:plan-run 2026.09.27-№1-COREX-KERNEL P05.1 P05.2`.

Claude Code для opus: `claude --model opus`, затем `task:plan-run 2026.09.27-№1-COREX-KERNEL <items>`.

## Full review runbook

1. После зелёной Validation, до finalize — свежий actor/context на reviewer model/effort из той же строки sheet.
2. Вход: item-spec, diff (или артефакты), вывод Validation. Reviewer read-only, не чинит; prompt:
   `read-only full review for <admission>; do not repair or finalize`.
3. Findings → owning executor чинит в текущем Scope → affected checks → reviewer перепроверяет. ≤2 раунда, затем эскалация.
4. Строка `review: <provider>/<model> <GREEN|findings:N>` в Completion Notes.

## Эстафета

Любой агент завершает команду переписанным `handoff.md` с одним semantic Next и launch-block из строки execution-sheet.
Следующий агент стартует только по этому Next (`git pull --ff-only` из корня клона).

## Текущая сессия — 2026-09-27

По запросу владельца завершить readiness в этой сессии B00-A выполняется Codex gpt-6-astra/high,
независимый reviewer — Cursor grok-4.7/high. Семантические frontier/high и full сохранены.
Claude CLI недоступен из-за истёкшего OAuth; это не снижает класс/effort и не требует нового root.
Исполнение остаётся отдельным шагом после GREEN дизайна.
