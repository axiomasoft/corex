---
id: D49
date: 2026-09-14
status: accepted
item: P6.18
items: [P6, P6.17, P6.18, P10, P11, P12, P14, P16]
supersedes: []
superseded_by: null
---

## Решение

По прямому уточнению владельца текущий фокус FLEX-2 — backend. Продуктовый frontend и его дизайн находятся вне рамок этого плана. Новое frontend-проектирование и реализация экранов начинаются только после стабилизации backend и отдельного задания владельца с требованиями к дизайну; снятие package/install gates само по себе этого не разрешает.

Максимально допустимая frontend-основа здесь:

- запускаемая заглушка;
- авторизация и минимальные необходимые состояния доступа;
- пустой дашборд после входа, без бизнес-виджетов, метрик и продуктовых экранов.

Текущие результаты backend: типизированные domain/API contracts, Actions, validation/authorization, read queries, tenancy/identity/module composition, approved schema/install/dependency qualification, API/integration/security tests и воспроизводимая backend-поставка. Проверки frontend допускаются для boot/auth/пустого dashboard и необходимой регрессии существующей основы, не как заказ нового UX.

## Почему

Backend-контракты, интеграция и поставка должны сначала стабилизироваться. Владелец отдельно задаст требования к продуктовому интерфейсу и дизайну; прежние требования сквозных API/UI-срезов сейчас создают работу за пределами согласованного приоритета.

## Приоритет и границы

D49 отменяет для будущей работы прежний порядок «backend → Nuxt → E2E» каждого продуктового среза и frontend/UX-разрешение D43. D38 schema approval, D37 topology/стек, D39 shared account DB, owning package/contracts/production gates сохраняются. `supersedes: []` означает, что эти решения не отменяются целиком; заменена только указанная часть scope/порядка.

В открытых пунктах P6 frontend-экраны, boards/forms/cards, UI collaboration/calendar/Studio/attendance/MCP и визуальная product acceptance не исполняются и не являются условием готовности backend-результата. Backend/API seams этих доменов остаются по их contracts и gates. Backend acceptance не объявляет готовым полный продуктовый интерфейс или production.

Закрытые P10/P11/P12/P14 и их evidence не переписываются и не переоткрываются. Уже созданные Tasks UI и изолированный CRM Nuxt prototype сохраняются как историческая техническая работа, без дальнейшего развития и подключения нового CRM layer в FLEX-2. Их mock walkthrough не является финальным UX/design approval и не переносит требования на будущий frontend-план. Не удалять продуктовый код по этому scope-уточнению.

## Текущий следующий шаг

Остаётся backend-очередь: новый owning CoreX receipt → delta qualification G1–G3 и prerequisites G4 → после admission exact dependency selection и installed backend/API integration на isolated account test DB. CoreX в этой сессии не редактировать; нужные изменения передаются отдельным промптом через владельца. App DB, approved schema и production не менять без соответствующего admission/approval.

## Evidence и проверка

RAG:— — прямое сообщение владельца 2026-09-14: «основной упор — это на бэкендовую часть»; frontend максимум auth/пустой dashboard, продуктовый дизайн — вне текущего плана. Противоречащие будущие требования найдены в roadmap.md и P6.18; scope обновлён в master/brief/roadmap/handoff/P6 contracts и текущем CRM composition design.

Проверка: `python3 plans/FLEX-2/workflows/check-backend-scope.py`; plan-lint относительно зафиксированного локального baseline; decision/status/affected bundles генерируются владельцем видов. Исторические статусы не изменяются.
