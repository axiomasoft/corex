# Chatom principles — source snapshot

Source: `chatom:plans/2026.09.26-№1-CHATOM-STABLE-API/research/02-target-architecture.md` §1.
Source commit: `21a6cca5e29e52f22a0f837cbbb46b41e4315350`; captured 2026-09-27. RAG:—; local repository text.

## 1. Принципы (сквозные, проверяемые)

1. **Core ничего не знает о companions.** Ни import, ни имени таблицы, ни ключа config, ни строки
   в документации core о конкретном companion. Companion подключается только через SPI.
2. **Один пакет — один владелец.** FQCN, таблица, config-ключ, publish-tag, artisan-команда,
   npm-подпуть, wire `type` события однозначно определяют пакет-владельца (машинная проверка).
3. **Пакет самодостаточен:** `composer.json` + (опционально) `package.json` + `src/` + `js/` +
   `config/` + `database/` + `resources/` + `tests/` + `README.md`; `composer test` и `pnpm test`
   запускаются из каталога пакета и проходят без остальных companions.
4. **Публичное — только объявленное.** Каждый публичный тип классифицирован атрибутом:
   `#[Api]` (Application API), `#[Spi]` (Extension SPI), иначе internal. Wire — отдельный
   контракт (JSON Schema/OpenAPI), версионируется отдельно.
5. **Инварианты не конфигурируются.** Безопасность, транзакционность, outbox-запись,
   авторизация — не удаляются config-ом; расширение — через именованные якоря.
6. **Всё компилируется при boot/optimize, исполняется из immutable снимка.** Никакого
   `config()` в runtime-пути, никаких static mutable кэшей.
7. **Схема детерминирована.** Выпущенный шаг схемы не меняется никогда; новая форма = новый шаг.
8. **Стандарты вместо изобретений** там, где стандарт есть: Standard Webhooks, CloudEvents-
   совместимый envelope, OpenAPI 3.1, JSON Schema 2020-12, SemVer.
9. **Никаких алиасов/deprecated-мостов до 1.0** (нет потребителей). После 1.0 — строгий SemVer.

