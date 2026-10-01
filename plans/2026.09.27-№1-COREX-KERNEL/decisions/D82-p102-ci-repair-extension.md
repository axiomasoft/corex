---
id: D82
date: 2026-10-01
status: accepted
item: P10.2
items: [P10.2]
supersedes: []
superseded_by: null
---
# D82 — Продолжить ремонт CI P10.2

**Actor:** repository owner; запись executor/codex/GPT-6.
**Evidence:** на вопрос о ремонте 49 DATA-003 вне первоначального scope P10.2 владелец ответил: «CI пока делаем, нет лимитов на использование». Сигнал: hosted run 36900690930; `artifacts/P10.2-execution/DATA-003-base-repro.log` воспроизводит все 49 нарушений на base 428f47db.

## Решение

Продолжить необходимые bounded CI repairs в текущем P10.2, включая DATA-003, согласование каталога таблиц с фактическими владельцами миграций и изоляцию PG-подключений тестов. Не откладывать объявленные красные гейты и не снижать пороги. Лимиты использования не служат основанием прекращения работы. Уже зелёные checks повторять при изменении их входов; flaky failure сохранять, а не скрывать повтором.

Исходные Scope/Files расширены необходимыми CI-ремонтами: `scripts/table-write-ownership-gate.php`, его отрицательные/положительные fixtures/tests, `docs/architecture/table-ownership.md`, test-support teardown/preflight и соответствующие evidence/CHANGELOG. Новое продуктовое поведение вне выявленных CI-причин и автоматический запуск последующих solo items этим решением не разрешены. Ревью — D81.

## Необходимый ремонт фактического foreign write

После исправления разрешения Options осталось одно нарушение: `WebhookRetention` удалял строки core inbox напрямую. Для сохранения существующего поведения очистки эффект переносится в additive callable SPI владельца `CoreX\Messaging\Laravel\InboxRetention::forget(consumer, messageIds)`. Старые API/SPI signatures не меняются, новые permissions каталога и bypass DATA-003 не добавляются. Это необходимый ремонт выявленной CI-причины; manifests/CHANGELOG/Golden отражают один новый поддерживаемый callable SPI. Добавление ограничено удалением явно выбранных ID одного consumer; политика retention и tombstones webhooks прежняя.

## Почему

Декларируемая hosted приёмка P10.2 не закрывается с красным Layer gate. Владелец разрешил продолжить необходимые CI repairs, а сохранённый preimage доказывает происхождение находок; ослабление gates не требуется.
