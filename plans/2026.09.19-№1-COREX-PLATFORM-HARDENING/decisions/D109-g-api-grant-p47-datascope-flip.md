---
id: D109
date: 2026-09-21
status: accepted
item: P4.7
items: [P4, P4.7]
supersedes: []
superseded_by: null
owner_gate: G-API
branch: main
scope: packages/core/src/Support/Data/DataScope.php
---
# D109 — Owner-gate G-API на флип `DataScope` (P4.7)

**Actor:** owner/vostrikov (human)

**Evidence:** RAG:— сообщение владельца 2026-09-21: «Я за любые улучшения, зафиксируй»
в контексте ожидания `G-API` перед `task:plan-exec P4.7`. RAG:— принятые D6, D21;
миграция P4.1–P4.6 закрыта (receipt `findings/P4-receipts.md` §P4.6).

## Решение

Владелец **выдаёт** `[OWNER-GATE:G-API]` на исполнение **только** item `P4.7` в ветке
`main` в объёме, зафиксированном спецификацией `phases/P4/P4.7.md` и
`research/P4-design.md` §5:

- скалярный versioned конструктор `DataScope`, удаление `public TenantContext $context`;
- `implements CoreX\Contracts\DurablePayload`;
- ужесточение `DurablePayloadRule` (идентификатор `corex.durablePayload.untypedArrayProperty`);
- сопутствующие файлы из `Files` item'а P4.7 и breaking-строка в `CHANGELOG.md`.

Гейт **не** расширяет scope на P4.8+, другие контракты, FQCN-переносы (P15) или
полную декомпозицию `DataScope` шире D6.

Уведомление потребителей `shared-data-foundation/1`: владелец поручает подготовить
текст в receipt P4.7; фактическая рассылка FlexCRM/внешним потребителям — действие
владельца после просмотра receipt (в монорепо потребители уже на `fromTenantContext`).

## Почему

D6 и дизайн фазы заранее описали ломающий флип; без owner-grant исполнение P4.7
запрещено полем `Authority` item'а. Явная фиксация отделяет принятое улучшение
платформы от неограниченного «менять что угодно».

## Consequences

`task:plan-exec P4.7` допускается при зелёных проверках item'а. Исполнитель
перепрогоняет команды полноты из receipt §P4.6 перед мутацией. Статус «Уведомлены»
в `plan.md` §Contracts обновляется после receipt P4.7 (текст + отметка владельца).
