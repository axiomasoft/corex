# Матрица вердиктов по двум внешним аудитам — 2026-09-19

Нормативный вход всех фаз PLAN2. Разобраны построчно:

- `audits/2026-09-19-perplexity-3.md` — архитектурный overlay второй итерации (пункты `A*`);
- `audits/corex-api-shopify-analysis.md` — API крупных платформ (пункты `S*`);
- собственные находки, отсутствующие в обоих аудитах (пункты `N*`).

Легенда вердиктов:

| Знак | Вердикт | Что означает |
|:--|:--|:--|
| ✅ | ПОДТВЕРЖДЕНО | Дефект/пробел воспроизведён чтением кода; предложение принимается как есть. |
| ☑️ | УЖЕ РЕАЛИЗОВАНО | Требование уже выполнено в дереве; остаётся только зафиксировать документом или гейтом. |
| ✎ | ПРИНЯТО С ИЗМЕНЕНИЕМ | Проблема реальна, но предложенная форма заменена — с указанием причины. |
| ✗ | ОПРОВЕРГНУТО | Предпосылка аудита ложна; работа не создаётся. |
| ⊘ | ОТКЛОНЕНО | Предпосылка верна, решение дороже пользы или вредно; занесено в `docs/rejected-*`. |
| ⏳ | ОТЛОЖЕНО | В `roadmap.md`, вне 1.0. |

Источники доказательств: `RAG:—` — чтение кода и `research/00-baseline-metrics.md`;
`RAG:✅` — внешнее первичное подтверждение: сводка в `research/perplexity-2026-09-19.md`, сырые
ответы с digest'ами — в `artifacts/rag-2026-09-19/`. Провенанс входов (пути и SHA256 аудитов) —
`inputs/sources.md`.

---

## 1. Приоритеты и нормативный слой (аудит A, раздел «Приоритеты второй итерации»)

| ID | Идея аудита | Проверка | Вердикт | Фаза |
|:--|:--|:--|:--|:--|
| A01 | P0: зафиксировать архитектурную конституцию `docs/architecture/constitution.md` | `RAG:—` `docs/architecture/` не существует; ближайшее — `docs/dev-doctrine.md` и `docs/03_Dev/Architecture_CoreX.md` | ✅ | P1 |
| A02 | P0: выбрать единую стратегию группировки больших пакетов | `RAG:—` `tenancy` 159 файлов смешивает 10 domain-first и 9 горизонтальных каталогов | ✅ | P1 (правила), P9/P16 (раскатка) |
| A03 | P0: зафиксировать public API каждого пакета | `RAG:—` `api/public.php` нет ни в одном пакете; `@api` не используется; `contract-guard.sh` — warn-прокси без BC-анализа | ✅ | P1 (политика), P3 (гейт) |
| A04 | P0: разделить `core` на внутренние bounded contexts | `RAG:—` в `core/src` девять посторонних возможностей: Audit, Departments, Features, Money, PubSub, Settings, Storage, Tenancy, Views | ✅ | P6 |
| A05 | P1: декомпозировать крупные service providers | `RAG:—` Tenancy 524 строки / 27.5 KB / 47 `config(`; Modules 374 строки / 17.6 KB | ✅ | P8 |
| A06 | P1: заменить runtime `config()` типизированными опциями | `RAG:—` 152 вызова `config(` в `packages/*/src`, 0 классов `*Options`. `RAG:✅` слоёвка config → Options DTO → сервисы подтверждена как практика | ✅ | P7 |
| A07 | P1: отвязать Attributes definitions от Modules | `RAG:—` `FieldCatalogue` собирается только при связанных `EntityRegistry` И `ModuleRegistry`, иначе `UnavailableAttributes` | ✅ | P10 |
| A08 | P1: нормализовать словарь имён | `RAG:—` `Dep`, `Data` (18 каталогов), `Support` (6), `Manager`/`Service`/`Resolver` с разной семантикой; но `Helper`/`Util`/`Processor` — 0 | ✎ Гид пишется, но список запрещённых имён сокращается до фактически встречающихся | P1 (гид), P15 (раскатка) |
| A09 | P1: формализовать bridge/integration policy | `RAG:—` политика мостов уже есть в `docs/dev-doctrine.md` §5 «Bridge-пакеты» и защищена правилом R2 гейта графа | ☑️ Перенести формулировку в конституцию без переписывания | P1 |
| A10 | P1: разделить transport, application и domain API | `RAG:—` в `packages/api` `Dispatcher` совмещает match/authorize/validate/invoke/envelope; контроллер тонкий | ✅ | P13 |
| A11 | P2: рассмотреть переименование пакетов (`api`, `panels`, `modules`, `wakeup`, `auth`) | `RAG:—` имена неоднозначны, но каждый пакет опубликован split-зеркалом | ✎ `modules` и `wakeup` оставить (доктрина D82 и определение durable-hint); `api`/`auth` — owner-решение Q1/Q2; `panels` — решение по пяти реальным примерам | P1 (Q1–Q3), P15 |
| A12 | P2: ввести package architecture scorecard | `RAG:—` измеримой оценки пакета нет | ✅ | P2 (базовый), P17 (финальный) |
| A13 | P2: добавить reference CMS consumer | `RAG:—` в дереве только FlexCRM-потребитель; `fixtures/` для внешних расширений нет | ✅ | P17 |
| A14 | P2: ввести extension compatibility tests | `RAG:—` contract-тесты есть, но внешних фикстур расширений нет | ✅ | P11 (contract suites), P17 (фикстуры) |
| A15 | P3: оптимизировать структуру после появления стабильных patterns | `RAG:—` — | ✅ Зафиксировано как D1: никаких переносов до P3 | P15–P16 |

## 2. Конституция, таксономия, структура (аудит A)

| ID | Идея аудита | Проверка | Вердикт | Фаза |
|:--|:--|:--|:--|:--|
| A16 | Статьи «Границы пакета» (одна ответственность, kernel ↛ capability, объявленные зависимости, optional integration не ломает bootstrap) | `RAG:—` частично уже защищено: `LayerTest`, R1/R3 гейта графа, `bindIf`/`interface_exists` в провайдерах | ✎ Принять как статьи, пометив, какие уже исполняются гейтом | P1 |
| A17 | Статьи «Владение данными» (одна таблица — один владелец, cross-package write через команду) | `RAG:—` владение не документировано; префикс `sys_` разделён между четырьмя пакетами | ✅ | P1 (статья), P2 (`table-ownership.md`) |
| A18 | Статьи «Public API» (перечислен, не экспортирует internal DTO, `@internal` под статанализом) | `RAG:✅` PHPStan 2.1.13+ умеет enforcement `@internal`; Roave BC-check 8.22.0 (2026-09-16) даёт release-to-release дифф | ✅ | P1, P3 |
| A19 | Статьи «Runtime» (нет глобального mutable current tenant; payload сериализуем; closure/model/request не пересекают durable-границу) | `RAG:—` `DataScope` несёт `TenantContext`; `static $` = 0; `now()` = 151. `RAG:✅` Octane/queue: `scoped()` не сбрасывает статику и мутации конфига | ✅ | P4 |
| A20 | Статьи «Конфигурация» (`config()` только на границе composition; typed immutable options; невалидная конфигурация ловится при boot; секреты отдельно) | `RAG:—` 152 `config(`; `core/src/Support/Config` — статический аксессор на 31 ключ | ✅ | P1, P7 |
| A21 | Статьи «Расширение» (явный контракт/реестр, детерминированный порядок, дубликат = ошибка, lifecycle register→…→deactivate) | `RAG:—` `freeze` — 0 совпадений в `packages/modules`; compile есть. `RAG:✅` Symfony (compile-time validation), Drupal (definition cache), Shopware (install/activate/deactivate/uninstall) | ✅ | P1, P11 |
| A22 | Таксономия: semantic kind пакета (Foundation/Capability/Integration/Product/Tooling/Fixture) | `RAG:—` таксономии нет | ✅ | P1 |
| A23 | Метаданные `extra.corex`: `package-type`, `stability`, `public-api` вместо переноса каталогов | `RAG:—` `extra.corex` уже используется 9 пакетами, но только с ключом `module` | ✎ Расширять существующий ключ, а не вводить новый; заполнить во всех 20 живых пакетах (D3) | P1 |
| A24 | Физические каталоги `packages/capabilities/`, `packages/integrations/` | `RAG:—` 20 path-репозиториев в корневом `composer.json`, отдельные CI-джобы на путях | ⊘ Дороже пользы; сам аудит называет вариант необязательным | — |
| A25 | Три профиля структуры A (≤40 классов) / B (40–100) / C (>100) | `RAG:—` измеренное распределение даёт ровно эти три группы (см. `00-baseline-metrics.md` §1) | ✅ | P1 |
| A26 | Правила: один use case ≤2 верхних папок; >20 прямых детей — группировать; каталог из одного класса без границы — лишний | `RAG:—` `tenancy/src` имеет 26 прямых детей | ✅ | P1 |
| A27 | `Internal` внутри bounded context обычно лишний; публичность фиксируют `@api`/`@internal` и BC-checker | `RAG:—` `Membership/Internal` = 39 файлов = весь Membership; всего 19 каталогов `Internal`, 188 файлов; при этом R2 гейта графа уже запрещает импорт чужого `Internal` | ✎ `Internal` сохраняется как единица enforcement R2 на уровне пакета, но внутри bounded context не создаётся; Membership разворачивается | P1, P9, P16 |

## 3. Core (аудит A)

| ID | Идея аудита | Проверка | Вердикт | Фаза |
|:--|:--|:--|:--|:--|
| A28 | `core` фактически platform-common monolith | `RAG:—` подтверждено: 109 файлов, 17 верхних каталогов, девять посторонних возможностей | ✅ | P6 |
| A29 | Core Litmus Test (шесть условий) | `RAG:—` формализации нет | ✅ Становится исполняемым чек-листом arch-теста | P1, P3, P6 |
| A30 | Немедленно вынести `Audit`, `Departments`, `Features`, `Money`, `PubSub`, `Settings`, `Storage`, `Views`, `Testing` в отдельные пакеты | `RAG:—` `Money` = 1 файл/59 строк, `Features` = 1 файл/121 строка, `Departments` = 2 файла/205 строк | ⊘ Отдельный Composer-пакет на один файл дороже пользы (D8) | — |
| A31 | Сначала внутренние dependency-зоны (`Foundation`, `Messaging`, `Settings`, `Audit`, `SavedViews`) и arch-тесты | `RAG:—` зон нет | ✅ Принято вместо A30 | P6 |
| A32 | `Testing` — в отдельный dev-пакет либо `tests/Support` | `RAG:—` `core/src/Testing` = 2 файла (Testbench-харнесс), попадает в production-autoload `corex/core` | ✅ | P6 |
| A33 | `DataScope` → набор скаляров + версия | `RAG:—` подтверждено: `public TenantContext $context` | ✅ (D6) | P4 |
| A34 | Явный `DataScopeCodec` на границе persistence/transport | `RAG:—` codec'а нет; прецедент — `search/src/Internal/Query/SearchCursorCodec.php` | ✅ | P4 |
| A35 | `TenantContextResolver::resolve(DataScope)` как отдельная ответственность | `RAG:—` интерфейс существует, но с методом `current(): TenantContext` — это «текущий контекст процесса», другая ответственность | ✎ Вводится отдельный контракт восстановления; существующий `current()` не переопределяется | P4 |

## 4. Tenancy, Modules (аудит A)

| ID | Идея аудита | Проверка | Вердикт | Фаза |
|:--|:--|:--|:--|:--|
| A36 | Tenancy смешивает domain-first и horizontal buckets | `RAG:—` подтверждено поимённо: 10 доменных + 9 горизонтальных каталогов | ✅ | P9 |
| A37 | Целевая структура Tenancy (Account/Membership/Provisioning/Identity/Authentication/Impersonation/DomainTrust/Workspace/Runtime/Laravel) | `RAG:—` — | ✎ Принято как целевая карта; фактические каталоги `Central` (12) и `Database` (6) в карте аудита отсутствуют и должны получить владельца | P9 |
| A38 | OIDC — не bounded context, а протокольный адаптер → `Tenancy/Authentication/Oidc` | `RAG:—` `Oidc/` = 4 файла (discovery, JWKS, verifier, client), тяжёлых зависимостей нет | ✅ (отдельный пакет — не сейчас) | P9 |
| A39 | `Tenancy/Wakeup` — integration boundary → `Tenancy/Integration/Wakeup` | `RAG:—` 3 файла, использует `CoreX\Wakeup\Contracts\{WakeupHint,WakeupSweeper}`, связка уже в allowlist гейта графа | ✅ | P9 |
| A40 | Декомпозиция `TenancyServiceProvider` по composition modules | `RAG:—` 524 строки, 0 под-провайдеров | ✅ | P8 |
| A41 | Внутренний feature provider не обязан наследовать Laravel `ServiceProvider`; ввести `PackageRegistrar` | `RAG:✅` Symfony compiler-pass/tagged-services как эталон сборки до заморозки контейнера | ✅ | P8 |
| A42 | Переименования Modules: `Dep`→`ModuleDependency`, `$type`→`$kind`, `$target`→`$package` | `RAG:—` подтверждено: `Dep` имеет `$type`, `$target`, `$constraint` | ✅ | P15 |
| A43 | `CorexModule`→`ModuleDefinition` | `RAG:—` `CorexModule` наследуют все module-классы девяти пакетов и потребителей; контракт проверяется `ModuleDiscovery` | ✎ Переименование только вместе с миграцией потребителей и записью в CHANGELOG; до 1.0 без deprecated-алиаса | P15 |
| A44 | `Manifest`→`ModuleManifest`, `GateDecision`→`ModuleActivationDecision`, `SettingDefault`→`DefaultSettingDefinition`, `ModuleContext`→`ModuleRuntimeContext` | `RAG:—` все классы существуют в корне `modules/src` | ✅ | P15 |
| A45 | `Extend`→`Extension(s)`, `Gate`→`Activation`/`Eligibility` | `RAG:—` `Extend` — закрытый набор из 8 extenders, зафиксированный ADR-011/ADR-014 и `docs/dev-doctrine.md` §4 | ⊘ Косметика с высоким радиусом при доктринально закреплённом имени | — |
| A46 | `WhyCommand`→`ExplainModuleCommand` | `RAG:—` класс есть; сигнатура artisan — `corex:modules:why` (нейминг-канон `corex:modules:*`) | ✎ Переименовать класс, сигнатуру команды сохранить | P15 |
| A47 | Семантика Module / Extension / Plugin как три разных понятия | `RAG:—` в дереве смешения нет, но словарь не записан. `RAG:✅` Shopware/Drupal подтверждают разделение lifecycle и вклада | ✅ | P1 |

## 5. Attributes, Transfer, Search (аудит A)

| ID | Идея аудита | Проверка | Вердикт | Фаза |
|:--|:--|:--|:--|:--|
| A48 | Разделить подсистемы Attributes (Schema/Values/Query/Storage/Integration/Laravel) | `RAG:—` сейчас `Contracts`/`Data`/`Exceptions`/`Internal`/`Internal/Engine`/`Internal/Registry`/`Support`/`Support/Schema` | ✅ | P10, P16 |
| A49 | Ввести `AttributeDefinitionSource` как сменный источник определений | `RAG:—` порт уже существует — `Contracts\FieldCatalogue::snapshot(DataScope, string $entity): FieldSnapshot` | ✎ Новый интерфейс не вводится; реализуются `Static`/`Database`/`Composite`/`Cached` поверх существующего порта, а провайдер перестаёт требовать `ModuleRegistry` | P10 |
| A50 | `RegistryFieldCatalogue`→`ModuleFieldCatalogue` | `RAG:—` класс существует, источник определений — compiled `ModuleRegistry` | ✅ | P10/P15 |
| A51 | Composite source c формализованной conflict policy (дубликат ключа, дубликат stable id, конфликт типов, precedence, удаление) | `RAG:—` composite-источника нет | ✅ | P10 |
| A52 | Ввести `SubjectType`/`SubjectId`/`SubjectRef` и не равнять subject type классу модели/morph-алиасу | `RAG:—` `RecordRef(entity, id)` уже opaque с валидацией handle; равенства классу модели нет | ✗ Тройка VO избыточна; требование аудита уже выполнено `RecordRef` | — |
| A53 | Стабильный `AttributeId` отдельно от переименуемого `AttributeKey` | `RAG:—` поля идентифицируются только кодом (`code`); стабильного id нет. `RAG:✅` Akeneo/Pimcore: стабильная идентичность атрибута отдельно от кода | ✅ | P10 |
| A54 | Протокол эволюции схемы (draft/active/deprecated/migrating/retired; propose→dual-read→backfill→switch→retire) | `RAG:—` частично: `SchemaChange` kinds `add/change/retire/drop`, статусы операций `pending/verified/failed`, `expectedSchemaHash` против дрейфа | ✎ Достраивается lifecycle определения и dual-read/backfill; существующая очередь DDL-операций не переписывается | P10 |
| A55 | `AttributeStoreResolver::for(SubjectType)` вместо глобального выбора движка | `RAG:—` сейчас `storage_driver` — глобальный ключ конфига; драйверы jsonb/eav/ddl | ✅ | P10 |
| A56 | Гранулярность выбора движка — subject type или attribute set, не отдельное поле | `RAG:✅` PostgreSQL: GIN не отдаёт порядок для `ORDER BY`; практичен гибрид JSONB + B-tree expression/partial индексы, EAV — для field-level lifecycle | ✅ + добавлено третье состояние: продвижение «горячего» поля в типизированную колонку | P10 |
| A57 | Attributes не должен напрямую обновлять Meilisearch; цепочка mutation→event→outbox→projection→provider | `RAG:—` связи attributes↔search нет вообще (0 совпадений) | ☑️ Инвариант сохранить гейтом; цепочка проекции строится в P5/P10 | P3, P5, P10 |
| A58 | Целевые подсистемы Transfer (Definition/Source/Sink/Mapping/Validation/Execution/Checkpoint/Format/Reporting/Laravel) | `RAG:—` сейчас `Contracts`/`Data`/`Internal/{Codecs,Export,Transfer}`, 32 файла | ✎ Как карта роста; при 32 файлах применяется профиль A, полный набор каталогов не создаётся | P16 |
| A59 | Терминология Transfer (`TransferSource`, `TransferSink`, `RecordTransformer`, `TransferExecutor`, `BatchReceipt`, …) | `RAG:—` есть `TransferSource`, `ImportTarget`, `RecordMapper`, `TransferRunner`, `TransferReceipt` | ✎ Принять `TransferSink` (вместо `ImportTarget`) и `SqlTransferExecutor`; `RecordMapper` сохранить — он уже точен | P15 |
| A60 | Обязательные свойства Transfer: resumability, idempotent sink, per-record failure, dry-run, progress, cancellation, schema version, backpressure, audit trail | `RAG:—` есть: checkpoint+fence, dry-run, per-record failure, progress, schemaHash, per-row audit. Нет: **cancellation** в API runner'а; `TransferLimits.maxMemoryMiB/maxRuntimeSeconds` объявлены, но внутри runner'а не применяются | ✅ для cancellation и enforcement лимитов; остальное ☑️ | P14 |
| A61 | Разделить index document / query / provider / hydration | `RAG:—` уже разделено: `SearchProvider`, `SearchSchema`, `SearchDocuments`, `IndexDriver`, `SearchHydrator`; провайдеры не грузят Eloquent | ☑️ Зафиксировать contract-тестом | P3 |
| A62 | `MeilisearchQueryAdapter`→`MeilisearchSearchProvider` | `RAG:—` класс реализует весь `SearchProvider`, а не только преобразование запроса | ✅ | P15 |
| A63 | `UnsupportedSearchProvider`→`UnavailableSearchProvider` | `RAG:—` класс — null-драйвер по умолчанию (`driver => 'null'`) | ✅ | P15 |
| A64 | `search-intl` не должен владеть SQL-репозиторием saved views и общей моделью ревизий переводов | `RAG:—` он ими и не владеет: `SqlSavedViewLabelReader` делегирует в `Intl\Contracts\TranslationReader`, `SavedViewRepository` живёт в `corex/core`, `TranslationRevision` — хеш-хелпер | ✗ | — |
| A65 | Реализовать второй провайдер поиска как архитектурный тест абстракции | `RAG:—` `PostgresSearchProvider` уже существует, плюс null-драйвер | ☑️ Остаётся закрепить contract suite для внешнего провайдера | P17 |

## 6. API, Panels, Wakeup, Auth, Intl, провайдеры, конфигурация, имена (аудит A)

| ID | Идея аудита | Проверка | Вердикт | Фаза |
|:--|:--|:--|:--|:--|
| A66 | `packages/api` — фактически operation invocation platform, а не HTTP API | `RAG:—` подтверждено: реестр операций + dispatcher + OpenAPI + MCP-инструменты; маршруты монтирует приложение | ✅ | P13 |
| A67 | Переименовать `api`→`operations` | `RAG:—` пакет опубликован split-зеркалом; ответственность ещё не заморожена | ✎ Owner-решение Q1 после P13 | P13/P15 |
| A68 | `Dispatcher`→`OperationDispatcher`, `ErrorBoundary`→`OperationErrorBoundary`, `RequestMetadata`→`InvocationMetadata`, `OpenApi`→`OpenApiDocumentGenerator` | `RAG:—` все четыре класса существуют с этими именами | ✅ | P15 |
| A69 | Перед переименованием Panels написать пять реальных примеров | `RAG:—` `panels` = 11 файлов, контракты `PanelCatalogue`/`PanelProvider` | ✅ Как research-item, имя не меняется без результата | P16 |
| A70 | Оставить имя `wakeup`, но переименовать общие классы (`DueSource`→`DueWakeupSource` и т. д.) | `RAG:—` контракты `DueSource`, `WakeupHandler`, `WakeupHint`, `WakeupScheduler`, `WakeupSweeper` | ✅ Имя пакета сохранить; классы переименовать | P15 |
| A71 | `RecordRules`→`RecordAccessPolicy` в `corex/auth` | `RAG:—` класса `RecordRules` не существует: есть `AccessChecker`, `ScopePredicate`, `DefaultScopeResolver`, `DirectGrantLookup` | ✗ | — |
| A72 | Возможное переименование `auth`→`access`/`authorization` | `RAG:—` пакет делает и authn (`PrincipalUserProvider`), и authz (`AccessChecker`), и матрицу ролей, и record-level | ✎ Owner-решение Q2 | P1/P15 |
| A73 | Cohesion audit `intl` и явные неймспейсы (Locale/Translation/Currency/Territory/TimeZone/Formatting) | `RAG:—` `intl` = 67 файлов, сейчас layer-first (`Contracts` 10, `Data` 26, `Internal` 22) → профиль B | ✅ | P16 |
| A74 | Признаки проблемного провайдера (>15–20 bindings, десятки ключей конфига, conditional по установленным пакетам, boot-время обращение к схеме) | `RAG:—` Tenancy: 30 регистраций, 47 ключей, условные ветки по `interface_exists` | ✅ Становится измеримым правилом гейта | P1, P3, P8 |
| A75 | Классификация конфигурации: deployment / product policy / runtime tenant settings / secrets | `RAG:—` классификации нет; `Settings` (System→Tenant→User) уже существует как runtime-слой | ✅ | P1, P7 |
| A76 | `Options::fromArray()`, `OptionsValidator`, config reference, dump-команда, redaction | `RAG:—` `corex:config:validate` не существует; `core/src/Support/Config` — статический аксессор | ✅ | P7 |
| A77 | Гид имён: суффиксы с точной семантикой (30 позиций) | `RAG:—` — | ✅ | P1 |
| A78 | Запрещённые публичные имена (`Dep`, `Info`, `Util`, `Helper`, `Common`, `Misc`, `BaseService`, `AbstractManager`, `DataManager`, `Processor`) | `RAG:—` в дереве есть только `Dep` и `CurrencyInfo`; `Helper`/`Util`/`Processor`/`Misc`/`Common` — 0 | ✎ Гейт на фактически встречающиеся плюс запрет на новые | P1, P3 |
| A79 | Сквозные переименования: `StructuralAttributeFilter`→`SchemaAttributeFilter`, `WriteResult`→`AttributeWriteResult`, `OperationKey`→`SchemaOperationId`, `AccountsResult`→`AvailableAccounts`, `CompletionCaptureObserver`→`CaptureTaskCompletion`, `DatabaseTransferRunner`→`SqlTransferExecutor` | `RAG:—` все классы существуют; `OperationKey` действительно коллидирует со словарём операций внешнего API | ✅ | P15 |
| A80 | `TasksWorkflowModule`→`TaskWorkflowIntegration` | `RAG:—` класс объявлен в `extra.corex.module`; module id = composer-имя, хранится в `mod_*` (D82) | ✎ Переименование класса допустимо, но требует проверки discovery и записи в CHANGELOG; module id не меняется | P15 |
| A81 | Каталог `Data` в крупных пакетах заменить на `ValueObjects`/папки домена | `RAG:—` 18 каталогов `Data`; крупнейшие — intl 26, transfer 19, notifications 18 | ✎ Только для пакетов профиля B/C; в компактных `Data` остаётся | P16 |
| A82 | Правила имён переменных, ID, булевых, коллекций | `RAG:—` — | ✅ Как раздел гида, гейт — только на публичные сигнатуры | P1 |
| A83 | `api/public.php` со списком публичных символов | `RAG:—` файла нет | ✎ Заменено на `extra.corex.public-api` + `@api`/`@internal` + BC-дифф (D4): рукописный список расходится с кодом | P1, P3 |
| A84 | Уровни стабильности `stable`/`experimental`/`internal`/`deprecated` | `RAG:—` — | ✅ | P1 |
| A85 | Межпакетное import-правило (только `Contracts`, `Data`, `Exceptions`, документированные entry points) | `RAG:—` R2 гейта графа уже запрещает чужой `Internal`; полного allowlist по неймспейсам нет | ✎ Расширение существующего гейта, а не новый инструмент | P3 |

## 7. Расширения, сообщения, ошибки, observability, безопасность (аудит A)

| ID | Идея аудита | Проверка | Вердикт | Фаза |
|:--|:--|:--|:--|:--|
| A86 | Не делать God Registry; иметь типизированные реестры | `RAG:—` уже так: `ModuleRegistry`, `OperationRegistry`, `PanelCatalogue`, `SearchManager` drivers, `FieldCatalogue` | ☑️ Зафиксировать инвариантом | P1, P11 |
| A87 | Общие инварианты реестра (тип идентификатора, duplicate policy, момент заморозки, порядок, инвалидация кеша, tenant-awareness) | `RAG:—` не формализованы | ✅ | P11 |
| A88 | Фазы `collecting → compiled → frozen` и запрет runtime-модификации после заморозки | `RAG:—` `compiled` есть, `freeze` — 0 совпадений. `RAG:✅` Symfony: заморозка до обслуживания запросов; Drupal/Shopware: слои инвалидации | ✅ (D5) | P11 |
| A89 | Компиляция вкладов в immutable runtime catalogues | `RAG:—` частично (`CompiledRegistry`) | ✎ Достроить fingerprint + детерминированный порядок + duplicate = ошибка компиляции | P11 |
| A90 | Три класса сообщений (domain event / integration event / command) и запрет автоповышения Laravel-события в контракт | `RAG:—` политика не записана; `DomainEvent` в ядре есть | ✅ | P5 |
| A91 | `MessageEnvelope` (messageId, name, version, occurredAt, correlationId, causationId, scope, payload, metadata) | `RAG:—` envelope'а нет. `RAG:✅` outbox-практика: стабильный `event_id`, `event_type` ≠ имя PHP-класса, `event_version` | ✅ | P5 |
| A92 | Обязательные правила сообщений: версионированные имена, запрет сериализации моделей, PII-класс, лимит размера, retry-класс, dead-letter, идемпотентный consumer, outbox/inbox, correlation/causation, тест совместимости схемы | `RAG:—` outbox/inbox отсутствуют полностью | ✅ | P5 |
| A93 | Единая таксономия ошибок без одной гигантской иерархии в `core` | `RAG:—` в `core/src/Exceptions` 6 классов; пакетные исключения местами общие | ✅ | P12 |
| A94 | Правила result vs exception (business denial → typed result; конфликт → domain-specific; batch → report) | `RAG:—` частично соблюдается (`GateDecision`, `TransferReceipt`, `WriteResult`) | ✎ Записать правило, привести в соответствие точечно | P12 |
| A95 | Observability: operation id, correlation id, tenant/workspace, actor, duration, outcome, retry, redacted failure, ограниченная cardinality метрик | `RAG:—` в API есть `requestId`, отдельного correlation-id нет. `RAG:✅` OTel PHP стабилен для traces/metrics/logs, Laravel 6–13; tenant-id как неограниченный label запрещён | ✅ | P12 |
| A96 | `OperationObserver` и bridge к PSR-3/OpenTelemetry вместо собственной tracing-платформы | `RAG:✅` подтверждено | ✅ | P12 |
| A97 | Различать `CapabilityUnavailable` / `CapabilityDisabled` / `OperationUnsupported` / `AccessDenied` / `FeatureNotEntitled` | `RAG:—` сейчас есть общий `UnavailableAttributes`/`Unsupported*` | ✅ | P12, P13 |
| A98 | Инварианты impersonation (отдельный actor и effective actor, неизменяемый initiator, reason, expiry, write policy, запрет вложенности, revocation, cleanup, отражение в каждом audit-record) | `RAG:—` частично реализовано: `ImpersonationGuard`, `DatabaseImpersonationService`, write-guards, middleware `tenancy.impersonation.allow` | ✎ Item — сверка фактических инвариантов против списка, достройка отсутствующих | P12 |
| A99 | Запрет доверять tenant-id из клиентского payload | `RAG:—` уже так: `TrustedContextResolver` + `require_physical_tenancy` | ☑️ Закрепить contract-тестом | P13 |
| A100 | Восемь независимых проверок при массовом присвоении (field exists, may view, may write, operation may mutate, type valid, constraints valid, tenant owns subject, schema revision compatible) | `RAG:—` `onlyFields` объявляется в манифесте, но dispatcher их не применяет | ✅ | P10, P13 |
| A101 | Реестр внешних референсов (Laravel, Spatie, Symfony, Sylius, Shopware, Pimcore, Akeneo, Oro, Drupal, Gutenberg, October, Bagisto, Magento, Medusa, Temporal, EventSauce, Broadway, League, Doctrine, Scout) | `RAG:—` файла референсов нет | ✅ Как `docs/architecture/references.md`, со ссылкой на конкретный заимствуемый механизм | P1 |
| A102 | Package architecture scorecard (12 критериев × 0/1/2) | `RAG:—` — | ✅ | P2, P17 |
| A103 | Инвентаризация `artifacts/architecture/files.csv` (21 колонка) | `RAG:—` артефакта нет | ✅ | P2 |
| A104 | Пер-классовый вердикт KEEP/MOVE/RENAME/SPLIT/INLINE/DELETE/PUBLIC/INTERNAL | `RAG:—` — | ✅ | P2 |
| A105 | Набор автоматических поисков (`config(`, `app(`, фасады, `now(`, `static $`, `Internal\`, `dispatch(`, …) плюс AST-сканер | `RAG:—` частично выполнено этим документом; AST-сканера нет | ✅ | P2 |
| A106 | Три графа: composer, namespace-import, runtime integration | `RAG:—` гейт графа строит только composer+namespace | ✅ | P2 |
| A107 | `docs/architecture/table-ownership.md` (11 полей на таблицу) | `RAG:—` нет | ✅ | P2 |
| A108 | `docs/architecture/configuration.md` (9 полей на ключ) | `RAG:—` нет | ✅ | P2 |
| A109 | Фазы рефакторинга A–G | `RAG:—` — | ✎ Разложено в 17 фаз этого плана (соответствие — `roadmap.md` §4) | — |
| A110 | Список «что не делать» (17 пунктов) | `RAG:—` — | ✅ Становится разделом «Анти-цели» конституции | P1 |
| A111 | Definition of Done 1.0 (27 условий) | `RAG:—` — | ✅ Становится гейтом P17 | P17 |

## 8. API как продукт (аудит S — Shopify)

| ID | Идея аудита | Проверка | Вердикт | Фаза |
|:--|:--|:--|:--|:--|
| S01 | API — самостоятельный продукт со своим жизненным циклом, а не HTTP-проекция моделей | `RAG:—` дерево уже ближе к этому (операции из манифестов), но версия/deprecation/capabilities отсутствуют | ✅ | P13 |
| S02 | Transport-neutral слой операций, поверх которого REST/GraphQL/OpenAPI/MCP/CLI/bulk/webhooks | `RAG:—` реестр и handler'ы транспорт-нейтральны, dispatcher — HTTP-shaped | ✅ | P13 |
| S03 | Date-based версии `YYYY-MM` в endpoint как основной механизм | `RAG:✅` Shopify: `YYYY-MM`, квартальный релиз, ≥12 месяцев поддержки, `publicApiVersions.supported`. **Но** `RAG:✅` для self-hosted: GitLab держит `/api/v4` как долгоживущий мажор, Atlassian прямо пишет, что ответы не помечены версией API и клиент обязан запрашивать capability, Grafana использует группы + alpha/beta/GA | ⊘ Как основной механизм отклонено (D2): сервер и клиенты обновляются независимо, date-версия не отвечает на вопрос «установлен ли модуль» | — |
| S04 | `ApiVersion` VO со статусами stable/RC/unstable/deprecated/EOL | `RAG:—` сейчас только `^v[1-9][0-9]*$` | ✎ Принято в форме мажора + статуса + окна поддержки в релизных линиях, без даты в пути | P13 |
| S05 | Версия как immutable профиль (`ApiProfile`), компилируемый при bootstrap, а не ветки `if` в контроллерах | `RAG:—` профиля нет; версия — фильтр по строке в реестре. `RAG:✅` Symfony compile-then-freeze | ✅ | P13 |
| S06 | Правило additive-first внутри поддерживаемой версии + закрытый список запрещённых изменений | `RAG:✅` Atlassian REST API policy формулирует это буквально (новые параметры необязательны, дефолты сохраняют поведение) | ✅ | P1, P13 |
| S07 | `#[ApiDeprecated(since, replacement, removalVersion, reason)]` как источник для OpenAPI/MCP/доков/заголовков | `RAG:✅` Atlassian использует заголовки `Deprecation`, `Sunset`, `Link` | ✅ | P13 |
| S08 | Cost-based throttling вместо request-per-minute | `RAG:✅` Shopify: calculated cost, leaky bucket, restore rate по тарифу магазина, requested vs actual | ✎ Принято как **opt-in policy**: self-hosted установка не обязана включать SaaS-лимиты; по умолчанию — защитный предохранитель, а не биллинговый бюджет | P14 |
| S09 | Контракты `OperationCostEstimator` / `OperationBudget` / `BudgetReservation` / `ThrottleStatus` + возврат неиспользованной стоимости | `RAG:✅` подтверждено моделью Shopify | ✅ | P14 |
| S10 | Иерархия бюджета global → tenant → client application → actor; не включать operation id в ключ хранилища | `RAG:✅` изоляция по паре «приложение + магазин»; ограничение cardinality подтверждено и отдельным источником по метрикам | ✅ | P14 |
| S11 | Веса операций через атрибут и cost policy; оценка без запроса к БД | `RAG:—` — | ✅ | P14 |
| S12 | Bulk operations как отдельный жизненный цикл (submit/poll/result/cancel/failures) | `RAG:✅` AIP-151, MS Graph LRO, Salesforce Bulk API 2.0, Shopify bulkOperationRunQuery — единый набор: id, статусы, прогресс, результат, отмена, срок жизни | ✅ | P14 |
| S13 | `BulkOperationDefinition`/`BulkOperationStatus`/`BulkOperationStore` | `RAG:—` в дереве нет | ✅ | P14 |
| S14 | Граница Operations ↔ Transfer: внешний контракт и статус в operations, чтение/маппинг/checkpoint в transfer | `RAG:—` `transfer` уже несёт checkpoint/fence/per-row audit | ✅ (D9) | P14 |
| S15 | Контроллер — только transport adapter | `RAG:—` `ApiController` уже тонкий (14 строк); толстый именно `Dispatcher` | ✎ Работа переносится на разделение `Dispatcher` | P13 |
| S16 | Контракт `Operation::definition()` + `OperationDefinition` (name, version, input/result type, mode, scopes, idempotency, cost, deprecation) | `RAG:—` сейчас `Operation` — readonly DTO из манифеста + `OperationHandler::handle()` | ✎ Достраивается поверх существующей декларативной модели модулей, без перевода на статический метод класса | P13 |
| S17 | `OperationMode`: query / command / async_command / bulk_query / bulk_command | `RAG:—` режимов нет; есть `mode` декларации `entity/extendResource/operation/tool` — другое понятие | ✅ | P13 |
| S18 | Транспортные адаптеры Http/GraphQL/OpenApi/Tools/Console с чёткой границей ответственности | `RAG:—` Http/OpenApi/Tools есть, GraphQL нет | ✎ Разводятся существующие три адаптера; GraphQL не проектируется, задел — транспорт-нейтральность контракта (D7) | P13 |
| S19 | Async command возвращает acceptance (`202 Accepted`), а не `200 {"status":"queued"}` | `RAG:✅` MS Graph: 202 + Location/Operation-Location | ✅ | P13 |
| S20 | Конверт ошибок с `code`/`message`/`path`/`category`/`retryable`/`details` + `meta.requestId/correlationId/apiVersion` | `RAG:—` конверт `{data,error,meta}` уже есть, `X-Request-ID` есть; `category`/`retryable`/`path` нет | ✎ Расширение существующего конверта + контент-негоциация `application/problem+json` (RFC 9457) для транспортных ошибок: `RAG:✅` IETF/Zalando за problem+json, Microsoft/Google — за собственный конверт с иерархией кодов; берём оба слоя | P12, P13 |
| S21 | Категории ошибок (authentication/authorization/validation/conflict/not_found/rate_limit/unsupported/unavailable/internal) | `RAG:—` частично в `ErrorMapper` | ✅ | P12 |
| S22 | Стабильны `code`/`category`/`path`/`retryable`/документированные `details`; `message` не парсится клиентом | `RAG:✅` Microsoft API Guidelines: стабильная иерархия строковых кодов | ✅ | P12, P13 |
| S23 | Idempotency обязательна для создания, transfer-батчей, provisioning, внешних webhook-consume, платежей, workflow-сигналов, async-команд | `RAG:—` для HTTP-операций store'а нет; в `wakeup` уже есть `request_key`+`request_digest` с unique-индексом — готовый прецедент | ✅ | P13 |
| S24 | Повторный ключ с другим fingerprint → конфликт, а не старый результат | `RAG:✅` Stripe: тот же ключ + те же параметры → повтор сохранённого ответа; другой endpoint/параметры → `idempotency_error`; ключи вычищаются через ≥24 ч; при провале валидации до исполнения результат не сохраняется | ✅ Плюс явная политика retention и поведения при concurrent-исполнении | P13 |
| S25 | Cursor-based пагинация как основной публичный контракт | `RAG:—` в API нет; прецедент в поиске (`SearchCursorCodec`) есть | ✅ | P13 |
| S26 | Курсор кодирует ordering version, последние sort-значения, tie-breaker, fingerprint запроса, expiry, подпись | `RAG:✅` подтверждено детально: strict-предикат по кортежу, обязательный tie-breaker, HMAC-подпись, привязка к tenant/фильтрам, отдельные версии формата и порядка, генерический ответ при невалидном курсоре | ✅ Добавлено из источника: курсор не должен нести имена полей/направления — только значения; ordering-реестр на сервере | P13 |
| S27 | `ResourceId` с типовым префиксом вместо auto-increment | `RAG:—` внутри уже ULID; типизированного внешнего id нет | ✅ | P13 |
| S28 | Разделить client scope / actor permission / record policy / field policy / tenant boundary | `RAG:✅` Salesforce (scope ≠ FLS/sharing), GraphQL Foundation, GitLab: field-auth не заменяет object-auth | ✅ | P13 |
| S29 | Webhooks на контрактах операций/интеграционных событий, а не на Laravel-событиях; подписка фиксирует версию API | `RAG:—` webhook-поверхности нет вообще | ✅ | P14 |
| S30 | Обязательные свойства webhook: стабильный id доставки, подпись, идемпотентный consumer, лимит retry, история доставок, версионированная схема, ротация секретов, PII-класс, явный replay | `RAG:✅` Standard Webhooks: `webhook-id`/`webhook-timestamp`/`webhook-signature`, подпись над `id.timestamp.body`, мульти-подпись для zero-downtime ротации, backoff с jitter, история и ручной replay, payload обычно <20 KB | ✅ | P14 |
| S31 | Capability/discovery-эндпоинты (`/api`, `/api/versions`, `/api/<v>/capabilities`, `/api/<v>/openapi.json`) | `RAG:✅` для self-hosted это **главный** механизм: Atlassian требует запрашивать capability, а не выводить из версии | ✅ Приоритет повышен: это несущая часть D2, а не косметика | P13 |
| S32 | Публичный API сообщает capability, а не инфраструктуру (не раскрывать `driver: meilisearch`) | `RAG:—` сейчас discovery-эндпоинта нет, раскрывать нечего | ✅ Как инвариант с гейтом | P13 |
| S33 | Целевая структура пакета operations (Contract/Definition/Invocation/Versioning/Authorization/Validation/Idempotency/Throttling/Bulk/Error/Observability/Laravel/Http/GraphQL/OpenApi/Tools) | `RAG:—` сейчас 55 файлов в `Contracts`/`Data`/`Internal` + корень | ✎ Профиль B по измеренному объёму; GraphQL-каталог не создаётся | P13, P16 |
| S34 | Выделение `operations-http`/`operations-graphql`/`operations-tools` в отдельные пакеты | `RAG:—` аудит сам называет это преждевременным | ⊘ Пока нет измеренного dependency pressure | — |
| S35 | Snapshot'ы схемы на версию (`openapi.json`, `operations.json`, `errors.json`, `webhooks.json`) | `RAG:—` в репозитории нет ни одного committed snapshot'а; OpenAPI генерируется в тесте на детерминизм | ✅ | P13 |
| S36 | CI отклоняет удаление операции/поля, добавление required input, смену типа/nullability, удаление enum-значения, смену стабильного кода ошибки, удаление webhook-топика, смену контракта пагинации | `RAG:—` такого гейта нет | ✅ | P3, P13 |
| S37 | Внешние consumer-фикстуры (rest-client, graphql-client, mcp-client, webhook-consumer, bulk-export-client) | `RAG:—` `fixtures/` нет | ✎ Без graphql-client (D7) | P17 |
| S38 | Список contract-тестов (11 сценариев: replay, конфликт fingerprint, порядок курсора, бюджет, возврат стоимости, скрытые поля, deprecated до removal, стабильные коды, изоляция бюджетов, возобновление bulk, старые snapshot'ы против текущей реализации) | `RAG:—` — | ✅ Становится приёмкой P13/P14 | P13, P14 |
| S39 | «Что не копировать» (GraphQL не canonical, без квартального cadence, без SaaS rate limits в self-hosted, без генерации домена из схемы, без Eloquent из резолверов) | `RAG:—` — | ✅ В анти-цели конституции | P1 |
| S40 | Следующий сравнительный проход по 13 платформам | `RAG:✅` частично выполнен здесь: Stripe (idempotency), GitLab/Atlassian/Grafana (версионирование self-hosted), Salesforce (bulk, FLS), MS Graph (LRO), Google AIP (LRO) | ✎ Остаток (GitHub conditional requests, Slack, Twilio, HubSpot, Notion, Contentful) — research-item P13 | P13 |

## 9. Собственные находки (нет ни в одном аудите)

| ID | Находка | Доказательство | Вердикт | Фаза |
|:--|:--|:--|:--|:--|
| N01 | Нет инъектируемого Clock; время берётся `now()` в 151 месте (tenancy 51, media 31, search 16) | `RAG:—` в `core/src` нет ни `Clock`, ни `ClockInterface`; аудит называет clock допустимым примитивом ядра, но не замечает его отсутствия | ✅ Детерминизм durable-логики и тестов | P4 |
| N02 | ~~`composer test:packages` покрывает 17 пакетов из 21~~ | `RAG:—` **перепроверено 2026-09-19 при детализации P1**: цикл `scripts.test:packages` корневого `composer.json` перечисляет все 20 живых пакетов, включая `panels`, `recurrence` и `search-meilisearch`; `import-export` — тумбстоун без `composer.json` и включён быть не может. Исходная находка — ошибка чтения, а не изменение дерева | ✗ Работа снята с P3 (`research/P1-design.md` §12) | — |
| N03 | Корневой `phpstan.neon` анализирует 7 пакетов из 21 | `RAG:—` `phpstan.neon` paths | ✅ | P3 |
| N04 | `extra.corex` есть только в 9 пакетах и содержит единственный ключ `module` | `RAG:—` чтение 21 `composer.json` | ✅ Определяет форму D3 | P1 |
| N05 | Outbox/inbox отсутствуют полностью — цепочка «домен → событие → проекция» ничем не гарантирована | `RAG:—` 0 совпадений; `RAG:✅` at-least-once + inbox с unique-constraint в одной транзакции | ✅ | P5 |
| N06 | Webhook-поверхности нет вообще (только два комментария в `modules/src/Contracts`) | `RAG:—` | ✅ | P14 |
| N07 | `packages/api` не регистрирует свои маршруты — их монтирует приложение; для «API как продукта» это дырка в поставке | `RAG:—` `ApiServiceProvider` без `loadRoutesFrom`; README описывает ручное монтирование | ✅ | P13 |
| N08 | `onlyFields` компилируется в метаданные, но `Dispatcher` его не применяет | `RAG:—` `Extend/Api.php` vs `Dispatcher.php` | ✅ Потенциальная утечка поля | P13 |
| N09 | `core/src/Testing` (Testbench-харнесс) попадает в production-autoload `corex/core` | `RAG:—` `composer.json` psr-4 `CoreX\\: src/` | ✅ | P6 |
| N10 | Префикс `sys_` разделён между четырьмя пакетами (core, attributes, workflow, wakeup) | `RAG:—` `Schema::create` литералы | ✅ Требует владения по таблице, а не по префиксу | P2 |
| N11 | Конфиг-ключ `corex-intl-search` сохранён после ренейма пакета в `corex/search-intl` | `RAG:—` `SearchIntlServiceProvider.php:18-20` | ✎ Осознанное BC-решение; задокументировать в `configuration.md`, не менять | P2 |
| N12 | `core/src/Support/Config` — статический аксессор на 31 ключ, то есть скрытая глобальная связность внутри ядра | `RAG:—` 31 вызов `config(` в одном файле | ✅ Становится boot-адаптером, а не источником значений для сервисов | P7 |
| N13 | Единственная производственная точка конструирования `DataScope` — `tasks-workflow`; во всех прочих пакетах он только передаётся | `RAG:—` поиск по дереву | ☑️ Хорошая новость: радиус миграции на скаляры мал | P4 |
| N14 | В `packages/tenancy/src` есть каталоги `Central` (12 файлов) и `Database` (6), которых нет в целевой карте аудита | `RAG:—` листинг | ✅ Целевая структура обязана назвать им владельца | P9 |

## 10. Сводка

| Вердикт | Пунктов |
|:--|--:|
| ✅ ПОДТВЕРЖДЕНО | 115 |
| ✎ ПРИНЯТО С ИЗМЕНЕНИЕМ | 34 |
| ☑️ УЖЕ РЕАЛИЗОВАНО | 7 |
| ⊘ ОТКЛОНЕНО | 5 |
| ✗ ОПРОВЕРГНУТО | 4 |
| **Итого разобрано** | **165** (A: 111 · S: 40 · N: 14) |

> **Поправка 2026-09-19 (детализация P1).** N02 переведён из ✅ в ✗ после перепроверки
> корневого `composer.json`: контур `composer test:packages` полон. Счётчики выше уже
> учитывают эту поправку (было 116 ✅ / 3 ✗). Разбор и две другие поправки к измеренной
> базе — `../research/P1-design.md` §12.

Отдельной строкой ⏳: GraphQL-транспорт (внутри S18, решение D7). Он не проектируется и не
получает ни одного item'а; задел исчерпывается транспорт-нейтральностью контракта операции,
которая требуется P13 и без GraphQL.

Опровергнутые предпосылки (не создают работы): A52 (subject identity уже opaque),
A64 (`search-intl` не владеет saved views), A71 (`RecordRules` в `corex/auth` не существует —
класс с этим именем живёт в `packages/crm/src/Support/`), N02 (контур `test:packages` полон —
собственная находка плана, опровергнутая собственной же перепроверкой).
Отклонённые решения при верной предпосылке: A24, A30, A45, S03, S34 — и, как следствие S03,
квартальный cadence версий. Каждый отказ обязан попасть в `docs/rejected-optimizations.md`
в P1 с этой же аргументацией.
