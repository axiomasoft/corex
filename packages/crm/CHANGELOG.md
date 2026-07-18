# Changelog

Все заметные изменения `flex/crm` документируются здесь. Формат — [Keep a Changelog](https://keepachangelog.com/en/1.1.0/).
Пакет pre-1.0 — breaking-изменения публичных контрактов легальны в MINOR до 1.0 (D17).

## [Unreleased]

### Changed

- **Breaking:** пакет и namespace переименованы `corex/crm`/`CoreX\Crm\` → `flex/crm`/`Flex\Crm\`
  (доктрина слоёв, D70/D72). Consumer-код обновляет `use`/`require` на новые имена (P2.0).
