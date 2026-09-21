---
id: D33
date: 2026-09-19
status: accepted
item: P6.5
items: [P6, P6.5, P16]
supersedes: []
superseded_by: null
---
# D33 — Вынос `CoreX\Testing` из production-autoload готовится в P6 и завершается в P16

**Actor:** plan-designer/cursor-grok-4.6/high
**Evidence:** RAG:✅ 2026-09-19 P6-Q3 Composer schema: `autoload-dev` и
`require-dev` — root-only; path-репозиторий не делает пакет вторым корнем.
Orchestra Testbench ставится `--dev`; Laravel package docs держат TestCase в
`tests/` + `autoload-dev`. RAG:— `packages/core/composer.json` мапит
`CoreX\\` → `src/`, поэтому `src/Testing` попадает в production-autoload.
`CoreXTestCase` extends `Orchestra\Testbench\TestCase` при `orchestra/testbench`
только в `require-dev`. Потребители в `src/`: `packages/crm/src/Testing`,
`packages/auth/src/Testing`, `packages/tasks/src/Testing`. Потребители в
`tests/`: intl, search-intl, tenancy, core, корневой `tests/`. D8 items уже
включают P16. Scope Excluded P6: структура остальных пакетов.

## Решение

1. P6 **не удаляет** `CoreX\Testing` из production `autoload.psr-4` пакета
   `corex/core`. Иначе `composer --working-dir=packages/<pkg> test` у
   потребителей, которые требуют `corex/core` как production-зависимость,
   не получит `autoload-dev` ядра и сломается — фазовая приёмка
   `composer test:packages` обязана быть зелёной.
2. P6.5 добавляет **канонический** `autoload-dev` `CoreX\\Testing\\` →
   `src/Testing/`, arch-гейт «новый файл в `packages/*/src` вне `*/Testing`
   не импортирует `CoreX\Testing`» и запись `exceptions/N09` с условием
   снятия.
3. Снятие production-autoload — P16, когда `src/Testing` в auth, tasks и crm
   уедет в `tests/` + `autoload-dev` этих пакетов, а пакетные сьюты intl,
   search-intl и tenancy пропишут тот же `autoload-dev` ядра либо перестанут
   импортировать `CoreX\Testing` из production-класса.

## Почему

D8 назвал Testing безусловным исключением из «сначала зоны». Композитор
опровергает наивный вынос: `autoload-dev` пакета-зависимости потребителю не
отдаётся. Полный вынос в одном item P6 потребовал бы правки composer.json
шести других пакетов и переноса их `src/Testing` — это P16 и нарушение
«один пакет на item». Staging сохраняет D8 как цель и не выдаёт красное
`test:packages` за зелёное N09.

## Consequences

N09 в матрице вердиктов после P6 носит статус «подготовлен, снятие — P16»,
а не «закрыт». `roadmap.md` §7 получает условие возврата. Item P6.5 не несёт
G-API.
