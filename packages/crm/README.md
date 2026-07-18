# flex/crm

CRM-модуль CoreX поверх `corex/core`: **воронка продаж** (pipelines → stages → deals) **+ CRM-ядро**
(контакты, компании-аккаунты, лиды, конверсия) **+ полиморфная timeline активностей**. Демонстрирует и
проверяет доменные возможности ядра — ULID, деньги в
minor units (`MoneyCast`, ADR-005), морф-карту через `EntityRegistry`. Изоляция тенантов — физическая
(DB-per-account, B-11), без row-level-скоупа на моделях.

## Состав

- **Воронка продаж**: `Pipeline`, `Stage` (порядок, вероятность, won/lost), `Deal` — сумма как
  `Brick\Money\Money` (`DealStatus`), связи `contact`/`company`, `custom_fields`.
- **CRM-ядро**: `Company` (аккаунт), `Contact`, `Lead`, полиморфная `Activity` (привязка к любой
  сущности через морф `subject`).
- **Actions** (граница use-case = `DB::transaction`, событие после commit):
  - сделки — `CreateDeal`, `MoveDealToStage`;
  - сущности — `CreateCompany`, `CreateContact`, `CreateLead`;
  - конверсия — `ConvertLead` (Lead → Contact [+Company] [+Deal в воронке]);
  - активности — `LogActivity` (полиморфно).
- **Events** (`ShouldDispatchAfterCommit`): `DealCreated`/`DealMovedToStage`,
  `CompanyCreated`/`ContactCreated`/`LeadCreated`, `LeadConverted`, `ActivityLogged`.
- **Enums**: `DealStatus`, `LeadSource`, `LeadStatus`, `ActivityType`.
- Сущности зарегистрированы в `EntityRegistry` (алиасы `crm_pipeline/stage/deal/company/contact/lead/activity`) —
  морфы (`Activity.subject`) резолвятся через единую морф-карту ядра.

## Пример

```php
$lead = app(CreateLead::class)->handle('Jane Doe', LeadSource::Website, companyName: 'Acme');
$contact = app(ConvertLead::class)->handle($lead, $pipeline, withCompany: true); // + Company + Deal
app(LogActivity::class)->handle($contact, ActivityType::Call, 'Intro call');
```

## Зависимости

Только `corex/core` (+ `illuminate/*`). Без AzGuard и тяжёлых зависимостей → таргетит Laravel 12/13.
Авторизация — забота потребителя / `corex/auth`.

## Тесты

Модуль тестируется **общим root-harness монорепо** (`tests/Crm/`). Из корня `packages/corex`:

```bash
composer test      # Pest + Orchestra Testbench (sqlite :memory:)
composer analyse   # PHPStan (larastan) level 6
composer lint      # Pint
```

> `custom_fields` — `jsonb` без GIN (sqlite-харнес); GIN-индекс добавляет Postgres-потребитель.
