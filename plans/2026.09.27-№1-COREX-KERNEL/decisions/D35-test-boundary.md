---
id: D35
date: 2026-09-28
status: accepted
item: P00.1
items: [P00.1, P02, P04]
supersedes: []
superseded_by: null
---
# D35 — test-boundary (accepted)

**Actor:** author-choice (claude-opus-5-5/high, Claude Code, 2026-09-28) по мандату владельца «есть идеальное решение — утверждай» (`brief/00-brief.md` B6); вывод из независимого ревью P00.1 (cursor/grok-4.7-high, R1).
**Evidence:** RAG:— `check-maps.py --dependencies` с тестовыми источниками: до решения — `internal-edge` (Messaging/Wakeup/Search/Invocation Internal из тестов других пакетов), `test-support-edge` (tools/webhooks → `CoreX\Api\Tests\*`), цикл operations↔tools↔webhooks через тесты; после — exit 0.

## Solution

Тест пакета импортирует из другого пакета только `#[Api]/#[Spi]`-символы и его тестовую поддержку `CoreX\<Pkg>\Testing\` / `CoreX\<Module>\Testing\` (src, автозагрузка, #[Api] в P04); `tests/` чужого пакета и `Internal` недоступны. P02 вводит: `CoreX\Operations\Testing\` (общие handlers/fixtures engine: 11 файлов `tests/Support` + `StubToolHandler`, карта `dest=packages/operations/src/Testing/`, фабрика `ResolvedContexts`), `CoreX\Messaging\Testing\` (registry/outbox/inbox в памяти), `CoreX\Wakeup\Testing\`, `CoreX\Search\Testing\`; `Tests\TestCase` (Testbench) у каждого пакета свой. Стабы монорепо `tests/Stubs`, нужные тестам ядра, копируются в `CoreX\Testing\Stubs`.

## Why

- Принцип «пакет самодостаточен» (`research/02` §1.3): `autoload-dev` зависимости не устанавливается, `tests/` чужого пакета после split недоступны.
- Прецедент `CoreX\Modules\Testing`; отдельный `corex-testkit` отклонён (D30).
- Отклонено: исключить тесты из графа — split прошёл бы проверку и сломал бы per-package `composer test`.

## Consequences

P02 создаёт `Testing`-модули и переписывает тесты по строкам `dependency-edges.tsv` (`move`/`remove`, owner_phase P02); P04 размечает `Testing` атрибутами.
