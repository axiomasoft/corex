---
id: D37
date: 2026-09-10
status: accepted
item: ''
items: [P6.10, P6.17, P6.18, P9.1, P9.2, P9.3, P9.4, P9.5, P9.6]
supersedes: []
superseded_by: null
---

## Решение

> Уточнение D38: история выбора архитектуры ниже сохранена. Очередь расширена до P9–P13; P9.2 теперь подготовка полной БД, P9.3 — обязательное owner approval до любого кода, API перенесён в P10.1/P10.4. Construction/property-management пока пустые каркасы без daily reports. При конфликте старой нумерации действует D38/roadmap.

По директиве владельца Laravel API + Nuxt SPA и отраслевые Laravel-приложения находятся в существующем flexcrm. Принят ADR-002-nuxt-solution-applications; новая фаза P9 задаёт старт кодовой реализации. Корневое приложение + frontend/; solutions/<industry>/ — независимые composition roots; общие PHP-пакеты через Composer, UI через Nuxt layers. CoreX остаётся внешним authoring repo.

Частично заменены D30/A1, B-37/T1, C-07 app layout и E1/P6.17 о новой репе. Старые решения/закрытия P8 сохранены как история, при конфликте действует ADR-002. D29, tenancy/isolation, Actions, FROZEN schema и существующие пакетные владельцы сохраняются. Q88 решён на уровне маршрута реализации: corex/api + endpoints owning modules, обязательный gate P9.2; готовность кода ещё не заявлена.

Уточнение D39 заменяет только data topology этого решения. Нормальный режим solution — включённые локальные базовые модули в общей account DB: base CRM и solution runtime читают одни базовые строки, а отраслевые пакеты расширяют ту же DB своими таблицами. Межрешенческий remote API нужен только между разными account DB или внешними системами. Правила независимых composition roots, пакетов, Nuxt layers и immutable images остаются действующими.

## Почему

Владелец запросил десятки независимо развиваемых отраслей без отдельных Git-репозиториев. Наследование app-классов или общий live vendor делают такую независимость фиктивной. Пакетная композиция, contracts-only зависимости и immutable images дают явную границу обновления. Отчёт расширяет задачу отдельной сущностью и Actions, а не наследованием final Task.

## Основания и проверка

RAG: Perplexity + первичные Nuxt/Composer/Laravel/Docker docs, ссылки в ADR-002. Repo review: CoreX loader существует, Tasks ещё library; taskable ULID расходится с UUIDv7 default; legacy flexcrm использует отсутствующие классы CoreX. Поэтому P9.1 предшествует scaffold/миграциям. Артефакты этого изменения — документация и задания; кодовая реализация P9 остаётся Not started.
