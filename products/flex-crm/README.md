# flex/crm

CRM-модуль CoreX поверх `axiomasoft/corex`: **воронка продаж** (pipelines → stages → deals) **+ CRM-ядро**
(контакты, компании-аккаунты, лиды, конверсия) **+ полиморфная timeline активностей**. Демонстрирует и
проверяет доменные возможности ядра — настраиваемые ID (по умолчанию UUIDv7), деньги в
minor units (`MoneyCast`, ADR-005), морф-карту через `EntityRegistry`. Изоляция тенантов — физическая
(DB-per-account, B-11); компании, контакты и связи дополнительно ограничены доверенным workspace-контекстом.

Company/contact/M2M public API, context binding and extension guarantees:
[COMPANY_CONTACT_API.md](COMPANY_CONTACT_API.md). Breaking changes and safe upgrade boundaries:
[UPGRADE.md](UPGRADE.md).

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

`composer require flex/crm` устанавливает CRM и компиляцию деклараций для Laravel 13.
Для прав и фильтрации записей приложение может подключить `axiomasoft/corex-access`.
Для HTTP-исполнения объявленных операций подключите `axiomasoft/corex-operations`
и зарегистрируйте его `OperationsServiceProvider`.

## Установка и lifecycle

Composer обнаруживает `flex/crm` как `packman-module` через `CrmModule`.
После `modules:compile` включите его через `ModuleLifecycle::enable('flex/crm')`
в целевой tenant DB. Lifecycle выполняет миграции и учитывает владение схемой;
provider больше не запускает CRM-миграции при общей команде `migrate`.
Disable сохраняет данные, повторный enable идемпотентен.

Для существующей установки, мигрировавшей CRM до появления manifest, сначала
сделайте резервную копию и выполните обновление CRM-миграций явным `--path`.
Не выполняйте purge: старые ownership claims автоматически не восстанавливаются.
Согласование исторической схемы и claims — отдельная процедура миграции потребителя.

## Тесты

Тесты находятся в `products/flex-crm/tests/`. Из корня монорепо:

```bash
composer --working-dir=products/flex-crm test       # SQLite
composer --working-dir=products/flex-crm test:pg    # только изолированная corex_test
composer --working-dir=products/flex-crm analyse
```

Company/contact fields follow approved storage, including JSON channels and PostgreSQL GIN indexes;
legacy `custom_fields` on those two models are not part of that contract.

## Контекст операций

Привяжите `Flex\Crm\Context\Contracts\CrmExecutionContext` к реализации хоста.
Для каждого вызова `current()` она подтверждает актёра и допуск к текущему аккаунту,
затем возвращает `Flex\Crm\Context\Data\ExecutionContext` с tenant-контекстом,
именем соединения, `Actor` и идентификатором действующего пользователя. Для доступа
ко всему аккаунту требуется явно подтверждённое разрешение. При отсутствии допуска
реализация отклоняет вызов.

## Хранение данных

До миграции CRM подготовьте tenant-соединение. Владельцем схемы пользователей,
рабочих пространств и членства может быть `axiomasoft/corex-tenancy` или приложение
с эквивалентной схемой. CRM использует UUID в `users.id` и `wsp_workspaces.id`,
проверяет активное членство через `wsp_workspace_members` и создаёт внешние ключи к
уже существующим таблицам.

1. Выполните миграции владельца таблиц пользователей, пространств и членства в целевой tenant-БД.
2. Выполните `php artisan modules:compile` и включите `flex/crm` через `ModuleLifecycle::enable()`.
   Для подготовки шаблона выполните `php artisan packman:schema:migrate --scope=tenant --modules --database=tenant`.
3. Проверьте журнал через `php artisan packman:schema:status`.
