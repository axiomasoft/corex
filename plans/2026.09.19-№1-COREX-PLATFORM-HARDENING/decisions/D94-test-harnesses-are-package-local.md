---
id: D94
date: 2026-09-20
status: accepted
item: P16.10
items: [P16, P16.3, P16.8, P16.10]
supersedes: []
superseded_by: null
---
# D94 — Testbench-харнесс является dev-поверхностью owning package

**Actor:** plan-designer/codex-gpt-5.6-sol/high
**Evidence:** RAG:✅ Composer `autoload-dev` применяется только к root package; RAG:— D33
и текущие импорты `CoreX\Testing`, `CoreX\Tasks\Testing`, `Flex\Crm\Testing`.

## Решение

Удалить test harness namespaces из production `src`. Каждый package suite получает
собственный Testbench base/trait под своим `tests/**` и `autoload-dev`; пакетный тест не
импортирует test-only класс другого Composer-пакета. Корневой монорепо-харнесс может
использовать `packages/core/tests/Support` через корневой `autoload-dev`, потому что корень
является владельцем этого dev graph. Новый runtime package и production autoload mapping не
создаются.

## Почему

`autoload-dev` зависимости не наследуется потребителем. Ссылка одного split-пакета на
test namespace другого работает случайно в монорепо и ломает самостоятельный package suite.
Локальный bootstrap немного повторяет Testbench wiring, но не дублирует предметную логику и
не загрязняет production surface.

## Consequences

P16.3 и P16.8 переносят owning Tasks/CRM harness, P16.10 атомарно завершает D33 для core,
auth, intl, search-intl, tenancy, tasks-workflow и корневых tests. Negative sweep запрещает
любой `*\Testing` import вне `tests/**` и production autoload.
