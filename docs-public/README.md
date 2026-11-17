# docs-public — CoreX

## Установка

Сначала настройте канал зависимостей приложения по
[инструкции экосистемы](../docs/architecture/ecosystem-dependencies.md).
Текущая интеграция использует зафиксированные development-снимки; это не объявление
публикации пакетов на Packagist.

```bash
composer require 'axiomasoft/corex:^1.0@dev'
php artisan vendor:publish --tag=corex-config
php artisan vendor:publish --tag=packman-modules-config
php artisan packman:schema:migrate --scope=tenant --database=<tenant>
php artisan packman:schema:status --scope=tenant --database=<tenant>
```

Настройки ядра находятся в `config/corex.php`, модулей Packman — в `config/modules.php`
с переменными `PACKMAN_MODULES_*`. Миграции пакетов не публикуются. Наборы модулей
устанавливает `Packman\Modules\Contracts\Lifecycle\ModuleLifecycle`; `--modules`
добавляет их при подготовке шаблона. В production для автоматической миграции нужен `--force`.

Дополнительные пакеты используют собственные теги и конфиги из
[таблицы пакетов](../README.md#пакеты). Saved views предоставляет Packman Panels через
`axiomasoft/corex-panels`; его таблицы создаёт Packman Schema. Конфигурация access —
`corex-access`, env — `COREX_ACCESS_*`. Доменная конфигурация tenancy —
`corex-tenancy`, env — `COREX_TENANCY_*`; host отдельно собирает внешний Tenant runtime
и его физические адаптеры по [tenancy README](../packages/tenancy/README.md).

## PHP API и расширение

Поддерживаемые символы размечены `Api` или `Spi(Implement|Call)` в исходниках.
[Generated surface](../docs/reference/public-api.md) и per-package
`resources/contracts/php-api.json` описывают конкретные слои и сигнатуры.
Api разрешает вызов/чтение/catch; реализация контракта требует Spi Implement.
Internal не импортируется чужим пакетом. `composer php-api` проверяет source и
свежесть snapshots; [SemVer policy](../docs/architecture/semver-policy.md)
определяет совместимость. `since` не означает, что релиз уже опубликован.
