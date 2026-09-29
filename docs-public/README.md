# docs-public — CoreX

## Установка

```bash
composer require axiomasoft/corex
php artisan vendor:publish --tag=corex-config
php artisan vendor:publish --tag=corex-migrations-tenant
php artisan migrate --database=<tenant>
```

Настройки ядра находятся в `config/corex.php`; настройки модулей — во вложенном разделе `modules`
(`corex.modules`). Env подсистемы модулей сохраняют префикс `COREX_MODULES_`. Дополнительные пакеты
устанавливаются как `axiomasoft/corex-<name>` и используют собственные теги и конфиги из
[таблицы пакетов](../README.md#пакеты). Для SavedViews нужен `axiomasoft/corex-panels` и публикация
`corex-panels-migrations-tenant`; конфигурация access — `corex-access`, env — `COREX_ACCESS_*`.
Tenancy использует env `COREX_TENANCY_*`, а конфиг движка остаётся `tenancy.php`.

## Синхронизация документации

Двусторонний синк с `docs-public/` код-репозитория. Репозиторий ещё не создан:
после создания заполнить поле `repo:` во frontmatter `CoreX.md` и выполнить
первый `brain sync` (см. `docs/workflow/brain-cli.md`).
