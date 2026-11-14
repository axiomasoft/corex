# CoreX — правила разработки

## Границы пакетов

CoreX — монорепозиторий переиспользуемых Laravel-пакетов. `corex/*` должен быть полезен
проекту без CRM, CMS и продуктов Flex. Зависимости направлены от продукта к пакетам;
ядро `axiomasoft/corex` не зависит от других пакетов монорепозитория.

Код и тесты пакета находятся в `packages/<name>/`. Продукты находятся в `products/`.
Корневые тесты проверяют интеграцию, архитектуру и общее качество. Списки пакетов и
допустимые зависимости задаются Composer-манифестами и
`scripts/package-graph-gate.json`. Импорт чужого `Internal` запрещён.

AzGuard самостоятельно владеет своим движком ролей. Интеграционный пакет CoreX
декорирует контракты хостов и не становится владельцем их предметной области.

## Публичные контракты

Публичный API и SPI — поверхности SemVer. Изменения интерфейсов, service providers,
схем и экспортируемого поведения требуют проверки совместимости и записи в CHANGELOG
затронутого пакета. Правила описаны в [политике API](architecture/api-policy.md) и
[политике версий](architecture/semver-policy.md).

Снимки `resources/contracts/php-api.json` и Golden baseline — проверяемые контракты,
они хранятся в Git. Обновление снимка само по себе не доказывает совместимость.
Перед стабильной публикацией нужен независимый BC-дифф против утверждённой базы.

Публичные идентификаторы ядра используют `corex`: конфигурация `corex.modules`,
переменные `COREX_MODULES_*`, команды `corex:modules:*`, publish-тег `corex-config`.
`flex/crm` — имя продукта, а не устаревшее имя ядра.

## Модули и схема

Модуль — Composer-пакет `type: corex-module` с наследником `ModuleDefinition`.
Версия берётся из Composer metadata. Изменение имени модуля требует миграции данных.

Механизмы расширения: события, extenders, компоненты, capabilities и Contracts/DI.
Набор extenders закрыт: Entity, EntityField, Permissions, Api, Filament, Menu, Routes,
Workflow. Новый механизм требует отдельного архитектурного решения.

Схему объявляют `SchemaSet` через `SchemaSetProvider`; исполняет её `SchemaMigrator`.
Пакеты не публикуют миграции и не вызывают `loadMigrationsFrom`.
Используются `corex:schema:migrate` и `corex:schema:status` с явным scope;
в production для изменения схемы нужен `--force`.
См. [политику схемы](architecture/schema-policy.md) и
[каталог владения таблицами](architecture/table-ownership.md).

## Проверки

Стек разработки: PHP 8.4–8.5 и Laravel 13. Команды запускаются через `scripts/php84`:

```bash
scripts/php84 composer lint:check
scripts/php84 composer analyse
scripts/php84 composer test
scripts/php84 composer test:pg
```

Для затронутого пакета выполняются его проверки и соответствующие архитектурные,
API/schema-гейты. SQLite и PostgreSQL проверяют разные сценарии; успешный один лейн
не заменяет другой. PostgreSQL-тесты запускаются только на явно изолированной тестовой БД.
Root line-coverage floor — 80%, пакетные пороги могут быть строже.

Мутационные прогоны остаются отложенными до отдельного запроса владельца;
удаление старых планов не возобновляет Infection, probes или fixed mutation checks.
Текущие проверки и их CI-проводка находятся в `composer.json` и `.github/workflows/ci.yml`.

## Локальные инструменты

Планы, аудиты, временные отчёты и окружения AI-провайдеров не входят в исходники пакета.
Окружение устанавливается локально через swissknifeman. Сторонние skills подключаются
через коннектор, без копирования в проект. Машинные настройки и generated state
перечислены в `.gitignore`. Исторические решения и отчёты доступны в истории Git.
