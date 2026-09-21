---
id: D15
date: 2026-09-19
status: accepted
item: P2.1
items: [P2, P2.1, P2.5, P2.6]
supersedes: []
superseded_by: null
---
# D15 — Измерение и суждение — разные артефакты

**Actor:** plan-designer/cursor-grok-4.6/high

**Evidence:** `RAG:✅` 2026-09-19 — P1-Q7 «generated output is evidence, not canonical
prose»; P2-Q5 fitness function называет свойство и измерение, средний балл запрещён
как KPI (захват `artifacts/P2-design-rag/q03-q05.md`). `RAG:—` эскиз
`research/02-shapes-platform.md` §2 клал `verdict` в ту же строку, что и
`config_reads`; A103 называет 21 колонку без вердикта.

## Решение

`artifacts/architecture/files.csv` несёт ровно 21 колонку измерения из
`research/P2-design.md` §2.1. Колонки `verdict` в нём нет.

Суждение живёт в `verdicts.csv` (P2.5) и в `scorecard.csv` (P2.6). Повторный прогон
сканера не имеет права перезаписать вердикт. Продуктовые Markdown-каталоги —
сгенерированная таблица плюс шапка со ссылкой на статьи конституции; они не вводят
новых правил.

Ненаблюдаемое поле каталога записывается как `unobserved`, а не угадывается.
Исключение: `upgrade_strategy=expand-migrate-contract` помечается `policy-default`,
потому что политика уже принята в P1.

## Почему

Смешение измерения и суждения в одном CSV делает инвентарь неидемпотентным:
пересчёт базы затирает интерпретацию. Эскиз отклоняется в этой точке; набор A103
принимается. Альтернатива «хранить вердикт в той же строке и не перезаписывать
колонку» отклонена: два прохода с разной семантикой обязаны иметь разных
владельцев файла, иначе исполнитель класса implementation сольёт их обратно.

## Consequences

P2.1 пишет `files.csv` / `symbols.csv` / `self-check.json` без вердикта.
`suggested_namespace` и `suggested_name` в P2.1 равны `—` и заполняются только в
`verdicts.csv`. P2.6 не считает среднее. P3 читает измерение и суждение как два
входа.
