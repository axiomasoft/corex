# corex/crm

CRM-модуль CoreX: **воронки → стадии → сделки** поверх `corex/core`. Демонстрирует и проверяет
доменные возможности ядра — ULID, мульти-тенантность (`TenantModel` + `TenantScope`), деньги в
minor units (`MoneyCast`, ADR-005), морф-карту через `EntityRegistry`.

## Состав

- **Модели**: `Pipeline`, `Stage` (порядок, вероятность, терминальные флаги won/lost), `Deal`
  (сумма как `Brick\Money\Money`, статус `DealStatus`).
- **Actions** (граница use-case = транзакция): `CreateDeal` (создаёт сделку на первой стадии воронки),
  `MoveDealToStage` (переносит и выводит статус из стадии).
- **Events**: `DealCreated`, `DealMovedToStage` (`ShouldDispatchAfterCommit`).
- **Enum**: `DealStatus` (open/won/lost).
- Регистрация сущностей в `EntityRegistry` (морф-алиасы `crm_pipeline`/`crm_stage`/`crm_deal`).

## Зависимости

Только `corex/core` (+ `illuminate/*`). Без AzGuard и без внешних тяжёлых зависимостей →
таргетит Laravel 13 и собирается в чистом CI. Авторизация — забота потребителя / `corex/auth`.

## Разработка

```bash
composer install
composer test       # Pest + Orchestra Testbench (sqlite :memory:)
composer analyse    # PHPStan (larastan) level 6
composer lint       # Pint
```
