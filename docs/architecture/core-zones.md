# Внутренние зоны `axiomasoft/corex`

| Поле | Значение |
|:--|:--|
| Версия | 1.0.0 |
| Статус | Нормативный |
| Область | `packages/core/src` |
| Решения | D8, D32, D33, D34 |

Этот документ задаёт логические зоны пакета `axiomasoft/corex`, принадлежность каждого PHP-файла
и допустимое направление внутренних зависимостей. Зона не является Composer-пакетом и не
меняет публичный FQCN. Физические переносы и совместимость имён принадлежат P15.

## 1. Матрица зон

Стрелка означает «может зависеть от». Импорт внутри своей зоны разрешён всегда.

```text
Foundation        → (никого), кроме E-BASEMODEL → Audit
TenancyContracts  → Foundation
Audit             → Foundation, TenancyContracts
Settings          → Foundation, TenancyContracts
SavedViews        → Foundation, TenancyContracts
Storage           → Foundation, TenancyContracts
Messaging         → Foundation, TenancyContracts
Features          → Foundation, TenancyContracts
Departments       → Foundation, TenancyContracts
Money             → Foundation, TenancyContracts
```

`Composition` и `Testing` — специальные области, а не зоны. Composition root может
связывать зоны. Testing исключён из R-Z1 и отдельно контролируется R-T1.

## 2. Карта принадлежности

Контрольная сумма карты — 150 PHP-файлов, включая четыре Foundation-файла словаря ошибок P12.1. Исходный baseline P6 содержал 109
файлов; 37 файлов появились в уже выполненных P4/P5. Они классифицированы здесь, а не
исключены из карты.

### 2.1 Каталоги с однозначной принадлежностью

| Путь | Файлов | Зона / область |
|:--|--:|:--|
| `Audit/` | 11 | Audit |
| `Departments/` | 2 | Departments |
| `Features/` | 1 | Features |
| `Messaging/` | 29 | Messaging |
| `Money/` | 1 | Money |
| `Observability/` | 9 | Foundation |
| `PHPStan/` | 1 | Foundation |
| `Settings/` | 2 | Settings |
| `Storage/` | 6 | Storage |
| `Support/Data/` | 8 | Foundation |
| `Support/Schema/` | 1 | Foundation |
| `Support/Time/` | 3 | Foundation |
| `Tenancy/` | 18 | TenancyContracts |
| `Testing/` | 2 | Testing |
| `SavedViews/` | 13 | SavedViews |

### 2.2 Каталоги со смешанной принадлежностью

**`Contracts/` (19):**

| Файлы | Зона |
|:--|:--|
| `DataScopeAuthorizer.php`, `DataScopeCodec.php`, `DescribesError.php`, `DurablePayload.php`, `ModuleRegistrar.php`, `OperationObserver.php` | Foundation |
| `DepartmentRepository.php` | Departments |
| `FeatureFlagDefinition.php`, `FeatureFlags.php` | Features |
| `IdentityPasswordRehasher.php` | TenancyContracts |
| `PubSub.php`, `WorkerRuntime.php` | Messaging |
| `SavedViewRepository.php`, `ViewAccessPolicy.php`, `ViewSchemaProvider.php` | SavedViews |
| `SettingDefaultsProvider.php`, `SettingsRepository.php`, `SettingsScope.php` | Settings |
| `TenantStorage.php` | Storage |

**`Models/` (5):**

| Файлы | Зона |
|:--|:--|
| `BaseModel.php` | Foundation; E-BASEMODEL |
| `Department.php` | Departments |
| `FeatureFlag.php`, `FeatureOverride.php` | Features |
| `Setting.php` | Settings |

**`Concerns/` (1):**

| Файл | Зона |
|:--|:--|
| `HasAuditLog.php` | Audit |

**`Enums/` (8):**

| Файлы | Зона |
|:--|:--|
| `ActorType.php`, `ErrorCategory.php`, `UnavailabilityReason.php` | Foundation |
| `ImpersonationRestrictedAction.php` | TenancyContracts |
| `MessageClass.php`, `PiiClass.php`, `PubSubDriver.php` | Messaging |
| `SettingScope.php` | Settings |

**`Events/` (5):**

| Файлы | Зона |
|:--|:--|
| `Cancellable.php`, `DomainEvent.php` | Foundation |
| `DepartmentMoved.php` | Departments |
| `FeatureFlagToggled.php` | Features |
| `SettingChanged.php` | Settings |

**`Exceptions/` (13):**

| Файлы | Зона |
|:--|:--|
| `InvalidScopePayload.php` | Foundation |
| `CentralContextException.php`, `ImpersonationRestrictedException.php`, `ScopeContextMismatch.php` | TenancyContracts |
| `CrossWorkspaceMoveException.php`, `DepartmentCycleException.php` | Departments |
| `SettingValueTooLargeException.php`, `UnsupportedScopeException.php` | Settings |
| `InboxRefused.php`, `InvalidMessage.php`, `OutboxLeaseLost.php`, `OutboxRefused.php`, `UnknownMessageContract.php` | Messaging |

**Остальные пути (5):**

| Путь | Зона / область |
|:--|:--|
| `Support/Data/DataScopeAccessDecision.php`, `Data/ErrorDescriptor.php` | Foundation |
| `Support/Config.php` | Composition |
| `Support/ServiceProviderModuleRegistrar.php` | Composition |
| `CoreServiceProvider.php` | Composition |

Контрольная сумма зон и областей:

| Foundation | TenancyContracts | Audit | Settings | SavedViews | Storage |
|--:|--:|--:|--:|--:|--:|
| 28 | 22 | 12 | 10 | 16 | 7 |

| Messaging | Features | Departments | Money | Composition | Testing | Итого |
|--:|--:|--:|--:|--:|--:|--:|
| 36 | 6 | 7 | 1 | 3 | 2 | 150 |

## 3. Правила и именованные исключения

- **R-Z0:** каждый PHP-файл под `packages/core/src` принадлежит ровно одной зоне либо
  поимённо включён в Composition/Testing. Неизвестный файл считается нарушением.
- **R-Z1:** файл зоны A импортирует только свою зону или зоны из матрицы. Сообщение
  нарушения обязано назвать правило, файл и запрещённый символ.
- **R-Z2:** файл вне физического каталога зоны не импортирует её `Internal`. Для
  Messaging физический каталог — `Messaging`; для SavedViews — `SavedViews`.
- **R-L3:** Foundation не импортирует `Illuminate\Database\Eloquent`, `Illuminate\Http`
  или `Illuminate\Foundation\Application`, кроме закрытого списка LITMUS_ELOQUENT.
- **R-T1:** production-файл вне `*/Testing/` не вводит новый импорт `CoreX\Testing`.

**COMPOSITION:**

```text
packages/core/src/CoreServiceProvider.php
packages/core/src/Config/Internal/Config.php
packages/core/src/Modules/Internal/ServiceProviderModuleRegistrar.php
```

Запланированные exact-пути `Support/CoreOptions.php`, `Support/PackageRegistrar.php` и
`Support/RegistersNothingOnBoot.php` войдут в Composition только после появления в дереве.
Шаблон вида `*ServiceProvider.php` запрещён.

**E-BASEMODEL:** `packages/core/src/Database/Laravel/BaseModel.php` — единственный файл Foundation,
которому разрешён импорт Audit-типа `CoreX\Audit\Laravel\HasAuditLog`.

**E-ZONE-EDGE (D116):** шесть публичных импортов разрешены только парой
«файл + символ». Матрица зон от этого не расширяется.

```text
packages/core/src/Data/Data/DataScope.php
    CoreX\Context\Data\TenantContext
packages/core/src/Messaging/Events/DomainEvent.php
    CoreX\Context\Data\TenantContext
    CoreX\Context\Contracts\TenantContextResolver
packages/core/src/Features/Events/FeatureFlagToggled.php
    CoreX\Settings\Internal\Contracts\SettingsScope
packages/core/src/Features/Internal/DatabaseFeatureFlags.php
    CoreX\Settings\Internal\Contracts\SettingsScope
    CoreX\Settings\Enums\SettingScope
```

**LITMUS_ELOQUENT:**

```text
packages/core/src/Database/Laravel/BaseModel.php
```

Список не расширяется без решения в Decision Log.

**R-T1 (D33 закрыт в P16.10):** `packages/*/src` не импортирует `CoreX\Testing`,
`CoreX\Access\Testing`, `CoreX\Tasks\Testing` и `Flex\Crm\Testing`. Харнесс ядра лежит в
`packages/core/tests/Support` и подключается только через `autoload-dev`.

## 4. Почему `Support/` и `Data/` остаются

`Data/` и `Support/Data/` выражают Foundation: `DataScope`, `RecordRef`, `WriteToken`,
кодек, authorizers и решение доступа имеют одинаковую семантику для всех потребителей.
`DataScopeAccessDecision` с P15.10 лежит в `Support/Data/` и остаётся публичным
return type `DataScopeAuthorizer`.

`Support/Config` и `Support/ServiceProviderModuleRegistrar` являются boot-адаптерами
Composition. Первый связывает конфигурацию нескольких зон, второй работает с Laravel
Application. Остальные Support-типы не получают неявного исключения: каждый новый файл
должен быть поимённо классифицирован R-Z0.

## 5. Целевые FQCN для P15

P15.10 применил D88. Текущие имена:

| Было | Стало |
|:--|:--|
| `CoreX\PubSub\*` | `CoreX\Messaging\Internal\*` |
| `CoreX\Audit\Contracts*`, `CoreX\Audit\Contracts\Auditable` | `CoreX\Audit\Contracts\*` |
| `CoreX\Audit\Internal\AuditLog` | `CoreX\Audit\Internal\AuditLog` |
| `CoreX\Views\*` | `CoreX\Panels\SavedViews\*` |
| `CoreX\Data\Data\DataScopeAccessDecision` | `CoreX\Data\Data\DataScopeAccessDecision` |

Публичные типы `final`, поэтому forwarding subclass неприменим. `class_alias` не вводится:
он не даёт полного BC-сигнала Roave/PHPStan и запрещён D32.

## 6. Кандидаты на другой уровень

| Зона | Почему не Foundation | Кандидат на пакет | Кандидат уровня 1 |
|:--|:--|:--|:--|
| Features | Отдельная capability и Eloquent-реализация | Нет: мала и платформенна | Нет |
| Departments | Оргдерево, cycle/workspace-семантика | Нет | Да, при расхождении продуктов |
| Money | Eloquent cast и тяжёлая `brick/money` | Да, при независимом потребителе | Нет |

Выделение `corex/money` требует потребителя, которому нужен `axiomasoft/corex` без
`brick/money`, и оплаченной стоимости отдельного release lifecycle. Departments остаётся
в ядре до появления второго продукта без оргдерева либо расхождения значения «отдел».

## 7. Чего документ не обещает

- Зоны не становятся отдельными Composer-пакетами в релизе 1.0.
- Существующие публичные FQCN не меняются (D32); P15 владеет переносом и G-API.
- `CoreX\Testing` не удаляется из production-autoload в P6 (D33); завершение принадлежит P16.
- Карта не разрешает `class_alias` и не ослабляет `@internal`-границы.
- DATA-003 — ORM над таблицей другого владельца — остаётся работой P9/D19.
- Семантика Settings, Audit, SavedViews, Features, Departments и Money не меняется.

## 8. Enforcement

Исполняемые R-Z0/R-Z1/R-Z2 добавляет P6.2, R-L3 — P6.3, R-T1 — P6.5.
Нормативные посадки должны краснеть с именем файла и символа. Основная точка запуска:
`composer test` через `tests/Arch/CoreZoneTest.php` и `TestingAutoloadTest.php`.
