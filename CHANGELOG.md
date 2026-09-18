# Changelog

Все заметные изменения монорепозитория CoreX документируются здесь. Формат — [Keep a Changelog](https://keepachangelog.com/en/1.1.0/).

## [Unreleased]

### Fixed

- `corex/intl`: `IntlModule` приведён к контракту `CorexModule` — discovery модулей больше не падает на `InvalidManifestException`.
- `corex/core`: контракт `TenancyManager` больше не импортирует реализацию из `corex/tenancy`.

### Changed

- `corex/intl`: в `require` добавлен `corex/modules` (наследование `CorexModule`).
- `corex/tasks-workflow`: в `require` добавлен `corex/workflow` (фактические импорты `CoreX\Workflow\*`).
