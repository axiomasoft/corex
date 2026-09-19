---
id: D39
date: 2026-09-11
status: accepted
item: P10.5
items: [P10.5, P11.7, P12.4, P13.1]
supersedes: []
superseded_by: null
---

## Решение

По прямому уточнению владельца construction и property-management остаются отдельными Laravel/Nuxt composition roots и изолированными Docker runtime, но подключаются к той же account DB, что и базовая FlexCRM. Они читают общие таблицы установленных базовых модулей и в будущих schema amendments добавляют свои таблицы в ту же БД.

DB-per-account сохраняется: граница изоляции данных проходит между accounts, а не между приложениями одного account. Solution — модульная сборка и отдельный runtime, но не новый tenant и не отдельный operational dataset. Базовый контейнер не обязан быть запущен рядом; общей зависимостью остаётся account DB.

P10.5 создаёт только пустые каркасы и не добавляет отраслевых таблиц. Dev profiles используют существующий PostgreSQL service/credentials основной FlexCRM и разные app/vendor/storage/cache/session namespaces. Production images остаются раздельными и не содержат исходники соседнего solution.

## Почему

Владелец уточнил целевую модель как обычную модульную систему: solution расширяет установленную CRM и должно работать с теми же пользователями, задачами и другими базовыми сущностями без репликации и remote-интеграции между приложениями. Изоляция контейнером нужна для независимых runtime и сборок, а не для разделения данных одного account.

## Consequences

- D39 уточняет только data topology D37 и ADR-002: «собственная DB на каждое solution» и remote-integration по умолчанию отменены. Остальные правила D37 о независимых composition roots, пакетах, Nuxt layers и immutable images сохраняются.
- Общие Tasks/auth в P11–P12 работают над теми же строками и не копируются между приложениями.
- Владение таблицами и миграциями остаётся пакетным; solution добавляет только свои owner migrations после отдельной утверждённой schema delta.
- Изоляция Docker не считается границей изоляции данных или авторизации. Backend каждого solution повторно проверяет account/workspace/policy.

## Gate схемы

Физические таблицы и связи не меняются, но меняется утверждённый deployment profile `DATABASE_SCHEMA.md`. Поэтому версия 0.2.2-review требует отдельного подтверждения владельцем точного review/artifact SHA до продолжения кодового item P10.5.
