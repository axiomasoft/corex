---
id: D41
date: 2026-09-13
status: accepted
item: P6.5
items: [P6, P6.5, P6.17, P6.18, P6.25, P6.26]
supersedes: []
superseded_by: null
---

## Решение

В рамках запроса владельца `task:plan-design FLEX-2 P6` уточнили следующий
ограниченный срез после закрытого P6.4: отдельный новый design недостающих
пакетных контрактов B-33/B-34 в CoreX. Предлагаемый ID —
`2026.09.13-№1-COREX-TENANCY-IDENTITY`; целевой оператор проверяет уникальность
дневного номера перед созданием. Brief — `findings/P6.5-delegate-contract.md`.

Старый маршрут `task:plan-design COREX-CORE P3` заменили: активного каталога
COREX-CORE больше нет. COREX-SUCCESSOR и COREX-APPROVED-SPECS имеют другой scope
и не доказывают delivery B-33/B-34. Terminal items не реопеняются. Это derived
routing/ownership correction, не новое разрешение на DDL/public Contracts,
публикацию или provisioning.

P6.5 сохраняет пакетные контракты и owning adapters с проверками. Central
membership writer/switcher server принадлежат P6.26 по D28/D40; control-plane
saga и ops — P6.25. Их швы и negative acceptance квалифицирует новый design;
неподтверждённые outputs остаются pending. CRM не создаёт учётки или доступы.

P9–P13 подтверждают первый базовый контур, а не полный P6.10/P6.17/P6.18.
После package prerequisites идут core CRM, Documents, Tasks collaboration и
Calendar отдельными backend → Nuxt → E2E срезами. Ближайший successor — только
новый design в CoreX из отдельной сессии.

## Почему

RAG:—: P6.4/P13.2 findings, D23/D28/D38/D40 и свежая read-only сверка CoreX,
описанная в brief. Старый COREX-CORE P3 не разрешает отсутствующий путь и не
учитывает текущие ownership/authority gates. Немедленная реализация CRM без
package acceptance не подтверждена. Новый bounded design сохраняет полный
scope P6 и отделяет пакеты от central runtime.

## Consequences

P6.5 не закрыт; чужие plans/runtime не менялись. D14/D16 и schema approval
сохраняются; design не выдаёт audit-admission или release authority. Оператор
сверяет live hashes и фактическое покрытие до detailed implementation items.
Прежние GREEN evidence P6.1–P6.4 и P10–P13 сохраняются.
