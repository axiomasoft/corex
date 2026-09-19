# P17.1 — installed backend/API receipt

Дата проверки: 2026-09-15.

## Admission и cohort

Исходный immutable CoreX receipt сохранён без изменений:
`delivery/crm-install-qualification-20260915/receipt.md`, SHA-256
`d2e6052a7b2ca87a3d04753597057282014f114674d116d9a81a232872bc334b`.
Его G1, G2 и G3 приняты как GREEN.

Для P17.1 добавлен локальный immutable cohort
`0.3.2-alpha.20260915` от CoreX ref
`6d23d6e3bf9cbe8f609ebf8b8eefb56216a5eb41`. Он включает общий platform
фикс: host resolver всегда читает `root_domains` и `root_accounts` через
`tenancy.central_connection`; это нужно FlexCRM для повторных physical API
запросов после переключения account database. CoreX остаётся product-agnostic.

Независимые сборки шести CoreX/Flex архивов совпали побайтно. Все их SHA-256,
путь candidate и SHA-256 consumer/solution locks записаны в
`source-inputs.json`.

## Installed evidence

Изолированный consumer установил exact ZIP set и выполнил полный physical HTTP
lifecycle на трёх named test databases: `flexcrm_p171_root_test`,
`flexcrm_p171_account_a_test`, `flexcrm_p171_account_b_test`.

- 14 HTTP exchanges: create/list/show Company и Contact, M2M association,
  duplicate/forged/foreign/disabled-module negatives.
- Compiled OpenAPI: SHA-256
  `3252e529683bac159b6b65f4dfb1637224cddc77767bc9cac1b40402224dd4fd`.
- `construction` и `property-management` независимо выполнили по пять
  exchanges со статусами `200, 200, 404, 201, 201` на общем account storage,
  с разными application keys и sessions.
- Harness fail-closed подтверждает host identification, physical auth context,
  central identity connection `pgsql`, tenant CRM connection и включённые CRM
  routes; после каждого запроса tenancy context завершён.

Три fresh `composer install --no-dev --no-scripts` production vendor-профиля
содержали все семь owned packages без source symlink, shipped `tests/` и
runtime test-autoload.

## Проверки

- CoreX host resolver regression: 11 tests, 65 assertions — PASS.
- Focused FlexCRM auth/CRM regressions: 38 tests, 148 assertions — PASS.
- Installed consumer lifecycle — PASS.
- Installed reuse: construction — PASS; property-management — PASS.
- Pint для `reuse.php` — PASS.
- Composer locks проверены и installed consumer dry-run вернул `Nothing to
  install, update or remove`.

## Исключения

Не выполнялись app DB migration/enable, production, publication, populated
cutover и frontend activation. Безопасные default-off конфигурации solutions
сохранены; physical profile включался только явными environment-параметрами
изоляционного harness.
