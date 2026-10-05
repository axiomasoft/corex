# Glossary v1 — имена и границы CoreX 1.0

Статус: **норма** P00.1 (2026-09-28) для P02/P03 и финальной архитектуры P00.2 (`research/04-architecture.md`).
Решения — D25–D35; машинные карты — `artifacts/P00/{package-map,namespace-map,dependency-edges,zone-consumers}.tsv`;
проверка — `artifacts/P00-design/scripts/check-maps.py` (default, `--dependencies`, `--doctrine`). Таблицы разделов
`Packages`, `Kernel modules`, `Root symbols`, `Reserved segments`, `Dev roots` читает `check-maps.py`: формат строк не менять.

## Naming rules

| # | Правило | Пример |
|:--|:--|:--|
| N1 | Composer vendor — `axiomasoft` (D02). Ядро — `axiomasoft/corex`; пакет — `axiomasoft/corex-<kebab>`, `<kebab>` = назначение, не технология и не слой (PKG-001). | `axiomasoft/corex-webhooks` |
| N2 | Namespace-корень пакета = `CoreX\` + PascalCase всех kebab-частей без дефисов; ровно один PSR-4 корень `CoreX\<Pascal>\` → `src/` (D04). | `corex-search-meilisearch` → `CoreX\SearchMeilisearch\` |
| N3 | Ядро владеет `CoreX\` только через закрытый список §Kernel modules, §Root symbols и §Reserved segments; сегмент пакета в ядре запрещён и наоборот. | `CoreX\Context` — ядро; `CoreX\Tenancy` — пакет |
| N4 | Namespace = путь: FQCN-префикс каталога = корень пакета + относительный путь от `src/` (PSR-4 folding запрещён). Класс сохраняет basename при переносе. | `packages/tenancy/src/Account/Models/` → `CoreX\Tenancy\Account\Models\` |
| N5 | Внутри пакета — feature modules (D57, заменяет D05): корень `src/` пакета — его корневой модуль (`Contracts`, `Data`, `Enums`, `Events`, `Exceptions`, `Internal/`, `Laravel/`, `Testing/`, `PHPStan/`, provider(ы), `<Pkg>Module.php`), подкаталоги — feature modules с тем же набором; у ядра корневого модуля нет. Слоевые каталоги (`Support`, `Models`, `Commands`, …) и корзины ядра (`CoreX\Contracts`, `CoreX\Enums`) запрещены; гейт — `scripts/module-layout-gate.py`. | `CoreX\Settings\Contracts\SettingsRepository`, `CoreX\Search\Data\SearchQuery` |
| N6 | Module id = composer-имя (EXT-009, D02). | `axiomasoft/corex-tasks` |
| N7 | Идентификаторы пакета выводятся из `<kebab>`: config `corex-<kebab>`, publish-tags `corex-<kebab>-{config,migrations[-<scope>]}`, env `COREX_<UPPER_SNAKE>_*`, artisan `corex:<kebab>:<verb>`. Ядро: config `corex`, tags `corex-{config,migrations[-<scope>]}`, env `COREX_<MODULE>_*`, artisan `corex:<module-kebab>:<verb>` (D33). | `corex:webhooks:deliver` |
| N8 | Продукт (L2) вне правил N1–N7: сохраняет собственный vendor/корень (`flex/crm`, `Flex\Crm\`), живёт в `products/` (D15). | `products/flex-crm` |
| N9 | Тестовые корни: `<Root>\Tests\` → `tests/`; нестандартные — только §Dev roots. Файлы без namespace (Pest, анонимные миграции) переносятся по пути, FQCN им не выдумывается. | `CoreX\Tools\Tests\Feature\` |

## Packages

Релизный набор P09 — все строки, кроме `product` и неопубликованного `access-azguard`. `bridge` — integration-пакет, соединяющий два пакета через их `#[Api]/#[Spi]`.

| Каталог | Composer | Namespace | Тип | Назначение |
|:--|:--|:--|:--|:--|
| `packages/core` | `axiomasoft/corex` | `CoreX\` | kernel | Ядро платформы: система модулей, контекст исполнения, данные, ошибки, конфигурация, время, сообщения, настройки, флаги, отделы, аудит, хранилище и наблюдаемость для любого приложения. |
| `packages/access` | `axiomasoft/corex-access` | `CoreX\Access\` | package | Авторизация платформы: граница tenant, контекст сущности, scope и матрица ролей без зависимости от AzGuard. |
| `packages/access-azguard` | `axiomasoft/corex-access-azguard` | `CoreX\\AccessAzguard\\` | bridge | Неопубликованный мост access ↔ AzGuard; релиз после стабильного AzGuard и owner-gate Q2 (D14). |
| `packages/custom-fields` | `axiomasoft/corex-custom-fields` | `CoreX\CustomFields\` | package | Типизированные пользовательские поля сущностей модулей: схема, значения, запросы и хранение. |
| `packages/intl` | `axiomasoft/corex-intl` | `CoreX\Intl\` | package | Локали, переводы, валюты и деньги, форматирование и территории/адреса. |
| `packages/media` | `axiomasoft/corex-media` | `CoreX\Media\` | package | Жизненный цикл приватных медиафайлов tenant: загрузка, конверсии, очистка. |
| `packages/notifications` | `axiomasoft/corex-notifications` | `CoreX\Notifications\` | package | Надёжные tenant-уведомления: inbox, доставка, дайджесты и предпочтения получателя. |
| `packages/operations` | `axiomasoft/corex-operations` | `CoreX\Operations\` | package | Движок операций: определение, вызов, идемпотентность, версии, валидация, пагинация, стоимость, bulk, HTTP-доставка и OpenAPI-проекция. |
| `packages/tools` | `axiomasoft/corex-tools` | `CoreX\Tools\` | package | Runtime инструментов: защищённый вызов операций как tools со схемами, contract-hash и MCP-проекцией. |
| `packages/webhooks` | `axiomasoft/corex-webhooks` | `CoreX\Webhooks\` | package | Исходящие вебхуки: подписки, подписанная доставка Standard Webhooks, SSRF-политика, повторы и dead-letter. |
| `packages/panels` | `axiomasoft/corex-panels` | `CoreX\Panels\` | package | Headless-описания панелей, каталог доступа текущего контекста и сохранённые представления (SavedViews). |
| `packages/recurrence` | `axiomasoft/corex-recurrence` | `CoreX\Recurrence\` | package | Ограниченное раскрытие правил повторения RFC 5545. |
| `packages/search` | `axiomasoft/corex-search` | `CoreX\Search\` | package | Типизированная граница поиска с гидратацией через политику доступа и жизненным циклом индекса. |
| `packages/search-intl` | `axiomasoft/corex-search-intl` | `CoreX\SearchIntl\` | bridge | Мост search ↔ intl: локале-зависимый поиск. |
| `packages/search-meilisearch` | `axiomasoft/corex-search-meilisearch` | `CoreX\SearchMeilisearch\` | bridge | Мост search ↔ Meilisearch: драйвер индекса и кандидатов. |
| `packages/tasks` | `axiomasoft/corex-tasks` | `CoreX\Tasks\` | package | Задачи, проекты, спринты, планирование и учёт времени. |
| `packages/tasks-workflow` | `axiomasoft/corex-tasks-workflow` | `CoreX\TasksWorkflow\` | bridge | Мост tasks ↔ recurrence/wakeup/workflow: материализация повторяющихся задач. |
| `packages/tenancy` | `axiomasoft/corex-tenancy` | `CoreX\Tenancy\` | package | Физический DB-per-account движок: control plane, provisioning, identity, membership, impersonation, domain trust. |
| `packages/transfer` | `axiomasoft/corex-transfer` | `CoreX\Transfer\` | package | Ограниченные примитивы импорта и экспорта данных. |
| `packages/wakeup` | `axiomasoft/corex-wakeup` | `CoreX\Wakeup\` | package | Надёжные tenant-локальные запросы пробуждения модулей по времени. |
| `packages/workflow` | `axiomasoft/corex-workflow` | `CoreX\Workflow\` | package | Необязательная привязка надёжного workflow-движка к модулям. |
| `products/flex-crm` | `flex/crm` | `Flex\Crm\` | product | Продукт CRM (L2) — consumer-доказательство платформы вне релизного набора. |

Split (единственное повторение `old_dir` в package-map): `packages/operations` → operations, tools, webhooks (D31).
Merge: `packages/modules` → `packages/core` (`CoreX\Modules`, D25). Tombstone: `packages/import-export` удаляется (P02).

### Пакеты без исходного кода (вне карт переносов)

| Composer | Namespace | Тип | Фаза | Назначение / условие |
|:--|:--|:--|:--|:--|
| `axiomasoft/corex-full` | — | metapackage | P09 | Lockstep-набор всех релизных пакетов (D19). |

Не вводится: `corex-testkit` (нет независимого потребителя, pplx-02; фейки SPI — `CoreX\<Pkg>\Testing\`, D30), `corex-timers`
(F8), `corex-money` (F3), `corex-modules` (F1).

## Kernel modules

Закрытый список первых сегментов ядра (D29). Новый модуль — только через D#.

| Module | Назначение | Источник |
|:--|:--|:--|
| `CoreX\Attributes` | PHP-атрибуты публичной поверхности `#[Api]`, `#[Spi]`. | новый, P04 (F12) |
| `CoreX\Audit` | Журнал изменений: запись, актор, retention, trait `HasAuditLog`. | `core/src/Audit`, `Concerns/HasAuditLog`, `Enums/ActorType` |
| `CoreX\Config` | Типизированная конфигурация: options, `ViolationList`, `SecretRef`, отчёт `corex:config:validate`. | `core/src/Support/{Config,CoreOptions,InvalidConfiguration,SecretRef,ViolationList}`, `Support/Console` |
| `CoreX\Context` | Контекст исполнения (account, workspace, актор, impersonation) и SPI его жизненного цикла. | `core/src/Tenancy` (F2) + tenancy-исключения/enum/`IdentityPasswordRehasher` |
| `CoreX\Data` | Ссылки на данные и доступ: `DataScope`, `RecordRef`, `WriteToken`, кодек и авторизаторы scope. | `core/src/Support/Data`, `Contracts/DataScope*`, `Exceptions/InvalidScopePayload` |
| `CoreX\Database` | База Eloquent-моделей и ключей: `BaseModel`, стратегия id, колонки ключей. | `Models/BaseModel`, `Concerns/HasConfiguredId`, `Enums/IdStrategy`, `Support/Schema` |
| `CoreX\Departments` | Иерархия отделов (оргдерево) как уровень scope настроек и флагов. | `core/src/Departments` + model/events/exceptions (F5) |
| `CoreX\Errors` | Машинная форма ошибок: descriptor, категория, причина недоступности. | `Data/ErrorDescriptor`, `Contracts/DescribesError`, `Enums/{ErrorCategory,UnavailabilityReason}` |
| `CoreX\Features` | Флаги функций аккаунта с переопределениями по scope. | `core/src/Features` + contracts/models/events |
| `CoreX\Messaging` | Доменные события, outbox/inbox/relay, pub/sub и контракты сообщений. | `core/src/Messaging`, `Events/{DomainEvent,Cancellable}`, `Contracts/{PubSub,WorkerRuntime,DurablePayload}`, `PHPStan` |
| `CoreX\Modules` | Система модулей: манифесты, compiled registry, жизненный цикл, регистрация пакетов. | `packages/modules` (F1), `Contracts/ModuleRegistrar`, `Support/{PackageRegistrar,RegistersNothingOnBoot,ServiceProviderModuleRegistrar}` |
| `CoreX\Observability` | Наблюдение за операциями: observer SPI, события исхода, OpenTelemetry-мост. | `core/src/Observability`, `Contracts/OperationObserver` |
| `CoreX\Settings` | Иерархические настройки user → workspace → account → default. | `core/src/Settings` + contracts/model/events/exceptions |
| `CoreX\Storage` | Tenant-изолированное файловое хранилище и его capabilities. | `core/src/Storage`, `Contracts/TenantStorage` |
| `CoreX\Time` | Часы PSR-20: системные, Laravel, замороженные. | `core/src/Support/Time` |

## Root symbols

| FQCN | Владелец |
|:--|:--|
| `CoreX\CoreServiceProvider` | `axiomasoft/corex` |

## Reserved segments

| Namespace | Владелец | Назначение |
|:--|:--|:--|
| `CoreX\Testing` | `axiomasoft/corex` | Dev-only харнесс ядра (`autoload-dev`); `src/` не импортирует (R-T1). |

Сегменты пакетов из §Packages зарезервированы за пакетами и запрещены в ядре; `CoreX\Tenancy`, `CoreX\Api`, `CoreX\Auth`,
`CoreX\Search\Meilisearch`, `CoreX\Money`, `CoreX\SavedViews`, `CoreX\Contracts`, `CoreX\Enums`, `CoreX\Events`,
`CoreX\Exceptions`, `CoreX\Models`, `CoreX\Concerns`, `CoreX\Support`, `CoreX\PHPStan` после P02 не существуют.

## Dev roots

Нестандартные корни (стандарт: `<Root>\` → `src/`, `<Root>\Tests\` → `tests/`).

| Namespace | Каталог | Назначение |
|:--|:--|:--|
| `CoreX\Testing\` | `packages/core/tests/Support/` | PSR-4 dev-харнесс ядра; тестовая поддержка modules — `CoreX\Testing\Modules\`. |
| `CoreX\Testing\Fixtures\` | `packages/core/tests/Fixtures/` | classmap-фикстуры ядра. |
| `CoreX\Access\Database\Seeders\` | `packages/access/database/seeders/` | Laravel-конвенция `database/seeders` (lower-case каталог). |

Строка карты с `dest=<каталог/>` в `note` задаёт целевой каталог явно (тесты/миграции при merge/split, PHPStan-фикстуры,
namespace которых — предмет проверки правила, тестовая поддержка → `src/Testing/`). Файл с несколькими namespace несёт
`ns:<Old>=<New>` для каждого вторичного блока (`WebhookTransportTest`: `curl_*`-шимы остаются рядом с транспортом).

## Attributes

| FQCN | Параметры | Семантика |
|:--|:--|:--|
| `CoreX\Attributes\Api` | `since: string` | Публичный контракт для вызова потребителем; приложение не реализует `#[Api]`-интерфейсы. |
| `CoreX\Attributes\Spi` | `kind: Implement\|Call`, `since: string` | Точка расширения: `Implement` — реализует потребитель (интерфейс не расширяется в minor), `Call` — вызывает. |

Та же семантика, что `Chatom\Attributes\{Api,Spi}` (D22); общий пакет атрибутов экосистемы не вводится. Реализация и гейт — P04 (D06).
`CoreX\Attributes` свободен только после переименования EAV-пакета в `corex-custom-fields` в том же P02 (F7).

## Identifiers per package

Цель P02 (D33). «Сейчас» — фактические идентификаторы (RAG:—, grep `mergeConfigFrom`/`publishes`/`$signature`/`env(`).
`<scope>` миграций (`tenant`, `central`, …) — окончательный набор в P08 (D20).

| Пакет | config | publish-tags | env | artisan | Сейчас → меняется |
|:--|:--|:--|:--|:--|:--|
| `axiomasoft/corex` | `corex` | `corex-config`, `corex-migrations[-<scope>]` | `COREX_<MODULE>_*` | `corex:<module>:<verb>` | config `corex-modules` → раздел `corex.modules`; tags `corex-modules-*` → `corex-*`; `corex:pubsub:prune` → `corex:messaging:prune-pubsub` |
| `axiomasoft/corex-access` | `corex-access` | `corex-access-config`, `corex-access-migrations[-<scope>]` | `COREX_ACCESS_*` | `corex:access:<verb>` | `corex-auth*`, `COREX_AUTH_*` |
| `axiomasoft/corex-custom-fields` | `corex-custom-fields` | `corex-custom-fields-{config,migrations}` | `COREX_CUSTOM_FIELDS_*` | `corex:custom-fields:<verb>` | `corex-attributes` |
| `axiomasoft/corex-intl` | `corex-intl` | `corex-intl-{config,migrations}` | `COREX_INTL_*` | `corex:intl:<verb>` | — |
| `axiomasoft/corex-media` | `corex-media` | `corex-media-{config,migrations}` | `COREX_MEDIA_*` | `corex:media:<verb>` | — |
| `axiomasoft/corex-notifications` | `corex-notifications` | `corex-notifications-{config,migrations}` | `COREX_NOTIFICATIONS_*` | `corex:notifications:<verb>` | — |
| `axiomasoft/corex-operations` | `corex-operations` | `corex-operations-config`, `corex-operations-migrations[-<scope>]` | `COREX_OPERATIONS_*` | `corex:operations:<verb>` | `corex-api*`; `corex:api:prune` → `corex:operations:prune`; `corex:api-snapshots` → `corex:operations:snapshot`; `corex:bulk-work` → `corex:operations:bulk-work` |
| `axiomasoft/corex-tools` | `corex-tools` | `corex-tools-config` | `COREX_TOOLS_*` | `corex:tools:<verb>` | раздел tools из `corex-api` |
| `axiomasoft/corex-webhooks` | `corex-webhooks` | `corex-webhooks-config`, `corex-webhooks-migrations[-<scope>]` | `COREX_WEBHOOKS_*` | `corex:webhooks:<verb>` | `corex-api.webhooks`; новая `corex:webhooks:prune` (D31) |
| `axiomasoft/corex-panels` | `corex-panels` | `corex-panels-{config,migrations}` | `COREX_PANELS_*` | `corex:panels:<verb>` | + таблицы SavedViews (F4) |
| `axiomasoft/corex-recurrence` | — | — | — | — | библиотека без provider |
| `axiomasoft/corex-search` | `corex-search` | `corex-search-{config,migrations}` | `COREX_SEARCH_*` | `corex:search:<verb>` | — |
| `axiomasoft/corex-search-intl` | `corex-search-intl` | `corex-search-intl-config` | `COREX_SEARCH_INTL_*` | — | `corex-intl-search*` |
| `axiomasoft/corex-search-meilisearch` | `corex-search-meilisearch` | `corex-search-meilisearch-config` | `COREX_SEARCH_MEILISEARCH_*` | — | — |
| `axiomasoft/corex-tasks` | `corex-tasks` | `corex-tasks-migrations` | `COREX_TASKS_*` | `corex:tasks:<verb>` | — |
| `axiomasoft/corex-tasks-workflow` | `corex-tasks-workflow` | `corex-tasks-workflow-{config,migrations}` | `COREX_TASKS_WORKFLOW_*` | `corex:tasks-workflow:<verb>` | `corex-task-recurrence` |
| `axiomasoft/corex-tenancy` | `corex-tenancy` | `corex-tenancy-config`, `corex-tenancy-migrations[-<scope>]` | `COREX_TENANCY_*` | `corex:tenancy:<verb>` | `tenancy-*` tags, `TENANCY_*` env; `tenants:{migrate,pending-create,pending-clear}`, `identities:reconcile` → `corex:tenancy:*`; upstream `tenancy.php` — адаптер движка, P07 (D13) |
| `axiomasoft/corex-transfer` | `corex-transfer` | `corex-transfer-{config,migrations}` | `COREX_TRANSFER_*` | `corex:transfer:<verb>` | — |
| `axiomasoft/corex-wakeup` | `corex-wakeup` | `corex-wakeup-{config,migrations}` | `COREX_WAKEUP_*` | `corex:wakeup:<verb>` | — |
| `axiomasoft/corex-workflow` | `corex-workflow` | `corex-workflow-{config,migrations}` | `COREX_WORKFLOW_*` | `corex:workflow:<verb>` | — |
| `flex/crm` | `flex-crm` | `flex-crm-*` | `FLEX_CRM_*` | `flex:crm:<verb>` | продукт, N8 |

## Kernel zones — вердикты

Колонка 5 `artifacts/P00/zone-consumers.tsv`; зоны без внешних потребителей — по карте ядра (§5 п.5 досье).

| Зона (use-scan) | Вердикт | Обоснование одной фразой |
|:--|:--|:--|
| Audit | keep → `CoreX\Audit` | Механизм надёжности платформы, 3 потребителя. |
| Concerns | keep, растворяется | `HasAuditLog` → Audit, `HasConfiguredId` → Database: слоевая корзина запрещена (N5). |
| Data, Support\Data | keep → `CoreX\Data` | 9 потребителей; единая семантика ссылок на данные. |
| Enums, Events, Exceptions, Models, Support, Root | keep, растворяются по модулям | Слоевые корзины: каждый файл назначен модулю в namespace-map (D29). |
| Errors | keep → `CoreX\Errors` | Механизм надёжности (коды ошибок D10). |
| Features | keep → `CoreX\Features` | Платформенный сервис (F6), вплетён в scope Settings. |
| Identity | keep → `CoreX\Context` | `IdentityPasswordRehasher` — SPI контекста, реализует tenancy. |
| Messaging | keep → `CoreX\Messaging` | Механизм надёжности (outbox/inbox). |
| Money | move → `axiomasoft/corex-intl` | 2 потребителя по 1 импорту, ядро теряет `brick/money`; intl уже владеет деньгами (F3). |
| SavedViews | move → `axiomasoft/corex-panels` | Единственный потребитель panels; UI-состояние (F4). |
| Settings | keep → `CoreX\Settings` | Платформенный сервис (F6). |
| Storage | keep → `CoreX\Storage` | 3 потребителя, изоляция tenant-файлов. |
| Support\Schema | keep → `CoreX\Database` | Колонки ключей следуют стратегии id ядра. |
| Support\Time | keep → `CoreX\Time` | PSR-20, 4 потребителя. |
| Tenancy | keep → `CoreX\Context` | 18 потребителей; переименование освобождает `CoreX\Tenancy` (F2). |
| Departments (0 внешних) | keep → `CoreX\Departments` | Вынос создал бы ребро ядро→пакет из Settings/Features (F5). |
| Observability (0 внешних в use-scan) | keep → `CoreX\Observability` | Механизм наблюдаемости операций всех пакетов (F6). |

## Forks F1–F12 — вердикты

| # | Вердикт | Обоснование одной фразой | D# |
|:--|:--|:--|:--|
| F1 | `corex/modules` → модуль ядра `CoreX\Modules` | 12 из 19 пакетов требуют пару core+modules, modules зависит только от core: независимого потребителя нет. | D25 |
| F2 | Tenancy-зона ядра → `CoreX\Context` | FQCN должен указывать владельца; «контекст исполнения» — смысл зоны, `Tenancy` остаётся пакету. | D26 |
| F3 | Money → `corex-intl` (`CoreX\Intl\Money\MoneyCast`) + SPI `FieldCastRegistry` | Ядро теряет `brick/money` и знание конкретного cast. | D27 |
| F4 | SavedViews → `corex-panels` (`CoreX\Panels\SavedViews\`) | Единственный потребитель, UI-состояние. | D28 |
| F5 | Departments остаётся в ядре | Scope-иерархия Settings/Features ядра. | D29 |
| F6 | Features, Settings, Audit, Storage, Messaging, Observability, Data, Time, Errors, Config — модули ядра | Платформенные сервисы и механизмы надёжности каждого приложения. | D29 |
| F7 | `corex/attributes` → `corex-custom-fields`, `CoreX\CustomFields` | Имя = назначение; освобождает `CoreX\Attributes`. | D30 |
| F8 | `corex/wakeup` → `corex-wakeup`, `CoreX\Wakeup` (без переименования) | Термин точен: durable wakeup requests; «timers» шире смысла. | D30 |
| F9 | Split: `corex-operations`, `corex-tools`, `corex-webhooks` по каталогам `Tools/`, `Webhooks/` | Три независимых потребителя; швы разобраны в `dependency-edges.tsv`. | D31 |
| F10 | `corex/access` → `corex-access`, `CoreX\Access` | Имя пакета = namespace (сейчас `CoreX\Auth`). | D30 |
| F11 | `corex-search-meilisearch` → `CoreX\SearchMeilisearch` | Один корень на пакет, `CoreX\Search` принадлежит только search. | D30 |
| F12 | `CoreX\Attributes\{Api,Spi}` | Та же семантика, что chatom; сегмент свободен после F7. | D30 |

## Terms

| Термин | Значение |
|:--|:--|
| Ядро (kernel) | `axiomasoft/corex`: модули §Kernel modules; не зависит ни от одного пакета монорепо. |
| Модуль ядра | Первый сегмент `CoreX\<Module>` из закрытого списка; не Composer-пакет. |
| Пакет (capability) | `axiomasoft/corex-<kebab>` с независимым потребителем или швом интеграции (pplx-02). |
| Bridge | Integration-пакет двух пакетов; зависит от обоих, они — не от него. |
| Продукт | L2-потребитель платформы (`products/*`), вне релизного набора. |
| Feature module | Подкаталог пакета/модуля ядра с публичными категориями и `Internal/` (D05). |
| Корзина (bucket) | Каталог-слой (`Contracts/`, `Enums/` …) на уровне пакета/ядра; запрещена (N5). |
| Шов (seam) | Ребро целевого графа, требующее решения до split; строка `dependency-edges.tsv` с disposition ≠ keep. |
| Disposition | `keep` — ребро остаётся; `remove` — исчезает (ответственность уходит к владельцу); `invert` — направление через SPI; `move` — источник/цель заменяется публичным символом другого владельца. |
| Dev root | Namespace-корень вне `src/` (тесты, фикстуры, сидеры) с явным каталогом. |
| Testing-модуль | `CoreX\<Pkg>\Testing\` / `CoreX\<Module>\Testing\` в `src/`: тестовая поддержка, доступная тестам других пакетов; `tests/` чужого пакета недоступен (D35). |
| Карта переносов | `namespace-map.tsv`: ключ — каталог (`/` на конце) или файл; longest match; `packages/<pkg>/src/` запрещён. |
