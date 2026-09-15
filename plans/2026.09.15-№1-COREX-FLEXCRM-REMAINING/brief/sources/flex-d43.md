---
id: D43
date: 2026-09-13
status: accepted
item: P6.18
items: [P6, P6.5, P6.17, P6.18]
supersedes: []
superseded_by: null
---

## Решение

По прямому запросу владельца продолжаем значительный объём локальной разработки
FlexCRM, пока CoreX исполняется в отдельной сессии. Ожидание D42 относится к
приёмке P6.5 и потребителям ещё неготовых контрактов, а не ко всем изменениям
приложения. Указание «один item — остановка в чате» не требует новой команды
после каждого обратимого шага этой авторизованной работы.

Независимый app-срез выполняется в текущей репе поверх уже принятых пакетов и
схемы 0.2.2. Источники требований — P6.18 и принятый P12. Это прямое развитие
приложения, без попытки `plan-exec` программного майлстоуна P6.18 и без
переоткрытия закрытого P12. Evidence записывается отдельно; полные milestones
сохраняют свои acceptance и статусы.

Первый срез: Tasks workbench — поиск, сортировка, подборки, сроки, URL-состояние,
доска текущей страницы, начало/оценка времени и возврат из карточки. Реализация
использует имеющиеся `corex/tasks`, `flexcrm/tasks` и общий Nuxt layer.

## Граница зависимости

| Можно выполнять сейчас | Нужно принять отдельно в конце соответствующего среза |
|:--|:--|
| App queries/API и Nuxt поверх существующих auth/Tasks contracts; UX, negative tests, reuse в solutions | Новые CoreX tenancy/identity contracts, owning DI/AP evidence и package release P6.5 |
| Формы для полей, уже поддержанных package commands и утверждённой DDL | Новый домен corex/crm, collaboration, calendar, workflow/attributes — после owning package readiness |
| Сборки, изоляция, API→TS и браузерная проверка независимого app-кода | Центральные writer/switcher/provisioning — P6.26/P6.25; конечная cloud-интеграция |

Нельзя переносить интеграцию «на конец» путём дублирования движков CoreX,
сочинения будущих контрактов или добавления неутверждённой схемы. Код CoreX,
его планы, source/dependency pins и release gates остаются в соседней сессии.

## Почему

В установленном `vendor/corex/` есть core/modules/tasks; локальный Tasks API
уже проходит owner Commands и workspace scope. Поиск/сортировки — read-side
FlexCRM; полям starts_at/estimate_minutes уже соответствуют Create/UpdateTask
контракты. Поэтому этот срез не зависит от новых B-33/B-34 receipts.

D41/D42 сохраняют scope/ownership CoreX; D43 уточняет только глобальное
ожидание и порядок локального app-кода. Schema SHA-256 остаётся
`1ef24ff57f3480829499ab621a9499adc9a96cbc5f618555d977a6f9f625e98f`.
Результат и проверки: `findings/P6.18-tasks-workbench.md`.
