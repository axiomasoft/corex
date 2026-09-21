# Исключение: CFG-006 — конфиг-ключ `corex-intl-search`

**Нарушаемая статья.** CFG-006

**Затронутые пути.** `packages/search-intl/config/corex-intl-search.php`,
`packages/search-intl/src/SearchIntlServiceProvider.php` (ключ `corex-intl-search`, publish-тег
`corex-intl-search-config`).

**Владелец.** `corex/search-intl`

**Причина.** Осознанное BC-решение после ренейма пакета в `corex/search-intl`: переименование
ключа конфигурации сломало бы существующие установки и опубликованные файлы (`RAG:—`, N11).

**Компенсирующий контроль.** Документированное исключение в конституции; новые пакеты обязаны
следовать правилу `corex/*`-имён; ключ перечислен в README пакета.

**Условие снятия.** Мажорный релиз пакета `corex/search-intl` с объявленной миграцией ключа
в CHANGELOG пакета.
