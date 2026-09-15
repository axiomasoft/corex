---
id: D47
date: 2026-09-14
status: accepted
item: P15.1
items: [P15, P15.1]
supersedes: []
superseded_by: null
---

## Решение

По запросу владельца `task:plan-design FLEX-2 P15.1` создаём следующий
контракт после закрытого P14: вход в Core CRM через проверку совместимости
и owning prerequisites. Первый предполагаемый продуктовый срез — компании
и контакты. P15.1 готовит проверенную матрицу и точный пакет требований
владельцу `flex/crm`, а не подключает несовместимый пакет к приложению.

## Почему

Минимальные Tasks завершены. Очередь P6.18/roadmap уже ставит Core CRM следующим
доменом. В соседнем CoreX существует `packages/crm`, но его Composer-имя —
`flex/crm`, namespace — `Flex\Crm`; в FlexCRM он не установлен. Доступные
модели/миграции расходятся со схемой 0.2.2: workspace/actor, имена и каналы
контакта, M2M contact↔company. Наличие исходников и tests не означает
совместимость с утверждённым контрактом или разрешение на новый package release.

## Границы и последствия

P15.1 выполняется локально, single-agent, с read-only доступом к исходникам
соседнего CoreX. Запись ограничена собственными findings/acceptance checker и
очередью FLEX-2. Не меняются CoreX, Composer/pins, schema/approval, DDL, API/UI
или отраслевые приложения. D38/D39/D40/D43 сохраняют свои gates и ownership.

Отсутствие package parity не блокирует сбор evidence в P15.1: результат может
честно фиксировать `not-ready` и конкретные owning prerequisites. GREEN этого
пункта означает полноту проверенной матрицы, а не готовность CRM-кода.
Для реализации нужны совместимый owning ref, проверенные package receipts и
соответствие утверждённой БД; реальное изменение target DDL требует отдельной
schema delta и согласия владельца. P6.5 остаётся отдельным внешним ожиданием;
его общий статус не подменяет проверку конкретных зависимостей CRM.

Единственный текущий Next — `task:plan-run FLEX-2 P15.1`, `frontier/high`,
`manual`. Будущий item реализации не создаётся до результата этой проверки.
Design заканчивается передачей ТЗ; execution в этом вызове не начинается.

## Evidence и provenance

RAG:— — репозиторные источники: `findings/P15.1-design.md`,
`findings/P14.3-minimal-tasks-acceptance.md`, `schema/approval.md`, P6.18.
Author: `gpt-5.6-sol/xhigh`; текущий provider route превышает минимальный
`frontier/high` для plan-design. Решение фиксирует дизайн очереди по явному
запросу, не выдаёт разрешение на CoreX writes, release или schema delta.
