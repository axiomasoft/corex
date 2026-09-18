# P12 — разбор двух внешних аудитов и их сверка с кодом

**Schema:** p12-audit-reconciliation/1
**Ревизия сверки:** `bfc05d060e3961a5881640b7f828503440bb2902` (HEAD на момент сверки — ровно та ревизия, которую проверяли оба аудита)
**Дата сверки:** 2026-09-18
**Источники (non-normative):** `audits/2026-09-17-perplexity-1.md`, `audits/2026-09-17-perplexity-2.md`

## Статус документа

Это нормативный вход фазы P12. Он определяет, какие утверждения аудитов **подтверждены кодом**,
какие **опровергнуты**, и какая рекомендация принята, отклонена или отложена.

> **Аудиты — сырьё, а не спецификация.** Оба файла в `audits/` содержат фактические ошибки
> и невалидный PHP (см. §4). Исполнитель P12 берёт код **только** из
> `research/P12-rebaseline-design.md`. Копировать примеры из `audits/*` запрещено.

Термины: **RAG:—** — утверждение проверено по локальному коду; **RAG:✅** — внешний факт
подтверждён первичным источником (см. `research/P12-design-rag.md`).

## 1. Матрица проверенных утверждений

Все verdict'ы получены чтением кода на указанной ревизии. RAG:— для всей таблицы.

| ID | Утверждение аудита | Verdict | Доказательство |
|:--|:--|:--|:--|
| A1 | `DataScope` импортирует `CoreX\Tenancy\TenantContext`, а `packages/core/composer.json` не требует `corex/tenancy` | ПОДТВЕРЖДЕНО, НО ВЫВОД НЕВЕРЕН | `packages/core/src/Support/Data/DataScope.php:8`; в `require` ядра нет ни одного `corex/*`. Однако сам класс лежит в ядре — см. A2 |
| A2 | `TenantContext` принадлежит пакету `corex/tenancy` | ОПРОВЕРГНУТО | `packages/core/src/Tenancy/TenantContext.php:5` — `namespace CoreX\Tenancy`. Класс физически в `corex/core` |
| A2a | Реальный дефект: неймспейс `CoreX\Tenancy\` расщеплён между двумя Composer-пакетами | ПОДТВЕРЖДЕНО (новая формулировка) | `core/composer.json` psr-4 `CoreX\` → `src/`; `tenancy/composer.json` psr-4 `CoreX\Tenancy\` → `src/`. 16 классов в ядре, 159 файлов в пакете |
| A2b | Реальная утечка core → tenancy существует, но в другом файле | ПОДТВЕРЖДЕНО (найдено при сверке) | `packages/core/src/Tenancy/Contracts/TenancyManager.php:9` — `use CoreX\Tenancy\StanclTenancyManager;`, класс есть только в `packages/tenancy/src/` |
| A3 | Есть другие undeclared cross-package зависимости | ПОДТВЕРЖДЕНО ЧАСТИЧНО | Реальные: `tasks-workflow` → `corex/workflow`, `corex/wakeup`; `tenancy` → `corex/wakeup` (только `suggest`). Ложные срабатывания аудита — импорты `CoreX\Tenancy\*`, живущие в ядре |
| NEW-1 | **Ни один аудит не нашёл:** `packages/intl/src/IntlModule.php` не соответствует контракту модуля | ПОДТВЕРЖДЕНО — P0 | Не наследует `CorexModule`, объявляет `static descriptor(): ModuleDescriptor` вместо `manifest(): Manifest`; класс `ModuleDescriptor` не существует нигде в репозитории. `ModuleDiscovery.php:40` бросает `InvalidManifestException::notACorexModule` |
| B1 | Attribute-хранилища жёстко кодируют имена таблиц | ПОДТВЕРЖДЕНО | `JsonbAttributeStore.php:155,160`; `EavAttributeStore.php:37,54,65,75,96`; `QueuedSchemaOperations.php:39`; `SchemaOperationWorker.php:30,36,49,118,155` |
| B2 | `corex/attributes` требует `corex/modules` в `require` | ПОДТВЕРЖДЕНО | `packages/attributes/composer.json:9` |
| B3 | Provider откатывается к null-объекту `UnavailableAttributes` | ПОДТВЕРЖДЕНО | `AttributesServiceProvider.php:68-78` |
| B4 | Конструктор принимает nullable `FieldCatalogue` и пропускает валидацию при `null` | ПОДТВЕРЖДЕНО | `JsonbAttributeStore.php:28-31` и `:189-192`; тот же паттерн `EavAttributeStore.php:27-30,156-159` |
| B5 | `ValuePatch`/`ValueBag` — плоские assoc-массивы, missing/null/remove не различимы | ПОДТВЕРЖДЕНО | `Support/ValuePatch.php:9-19`, `Support/ValueBag.php:7-14` |
| B7 | В `Contracts/` лежат DTO, а не только интерфейсы | ПОДТВЕРЖДЕНО ЧАСТИЧНО | `attributes/src/Contracts/` — 4 файла, все интерфейсы (чисто). `core/src/Contracts/` — 3 из 17 являются `final readonly class`: `AuditEntry`, `FeatureFlagDefinition`, `SettingsScope` |
| C1 | `SearchServiceProvider` возвращает `UnsupportedSearchProvider` при невалидном конфиге | ПОДТВЕРЖДЕНО | `SearchServiceProvider.php:23-36` |
| C2 | `search-meilisearch` перерегистрирует тот же биндинг `SearchProvider` | ПОДТВЕРЖДЕНО | `SearchServiceProvider.php:23` и `MeilisearchServiceProvider.php:30` — оба `scoped(SearchProvider::class)`; итог зависит от порядка загрузки providers |
| C3 | SDK Meilisearch запинен точной версией | ПОДТВЕРЖДЕНО | `search-meilisearch/composer.json:10` — `"meilisearch/meilisearch-php": "1.16.1"` |
| D1 | Пакет `corex/import-export`, неймспейс `CoreX\ImportExport` | ПОДТВЕРЖДЕНО | `packages/import-export/composer.json` |
| D2 | `DatabaseTransferRunner` делает всё в одной retryable-транзакции | ПОДТВЕРЖДЕНО | `DatabaseTransferRunner.php` — 231 строка / 11 107 байт; внешняя транзакция `:41` … `}, attempts: 3);` `:130`; вызов target `apply`/`lookup` во вложенной транзакции `:82-91` внутри внешней |
| D3 | `catch (Throwable)` превращает любую ошибку в `rowFailed` | ПОДТВЕРЖДЕНО | `DatabaseTransferRunner.php:92-97` |
| D4 | Dry-run не пишет checkpoint, но возвращает инкрементированный sequence | ПОДТВЕРЖДЕНО | `:108` инкремент, `:111-127` пропуск записи, `:129` возврат |
| D5 | Ключевые DTO лежат в `Support/` | ПОДТВЕРЖДЕНО | Все 9 названных файлов присутствуют в `import-export/src/Support/` |
| E1 | `intl-search` требует core + intl + search | ПОДТВЕРЖДЕНО | `intl-search/composer.json:6-11` |
| E2 | `intl-search` владеет собственной функциональностью и SQL-персистентностью | ОПРОВЕРГНУТО В ЧАСТИ SQL | Классы — тонкие декораторы. `SqlSavedViewLabelReader` не содержит SQL, делегирует `TranslationReader`. Собственный контракт только один: `Contracts/SavedViewLabelReader` |
| E3 | Saved views принадлежат `corex/panels`/`corex/views` | ОПРОВЕРГНУТО | Владелец — `corex/core`: `core/src/Contracts/SavedViewRepository.php`, `core/src/Views/Internal/SqlSavedViewRepository.php`, биндинг `CoreServiceProvider.php:76` |
| F1 | `test:packages` покрывает 8 из 20 пакетов | ПОДТВЕРЖДЕНО | `composer.json:67`. Не запускаются: `attributes`, `core`, `import-export`, `intl`, `intl-search`, `media`, `modules`, `panels`, `search`, `search-meilisearch`, `tasks-workflow`, `workflow` |
| F2 | У `core` и `modules` нет собственных скриптов `test`/`analyse`/`lint` | ПОДТВЕРЖДЕНО | Остальные 18 пакетов скрипты имеют |
| F4 | Архитектурные тесты уже существуют | ПОДТВЕРЖДЕНО | `tests/Arch/LayerTest.php` — 6 правил (запрет `use Flex\`, запрет `flex/*` в require, ядро без monorepo-зависимостей, запрет sibling-импортов в ядре, storage-адаптеры tenancy, product-литералы) |
| G1 | `config()` вызывается в domain/repository/engine-классах | ПОДТВЕРЖДЕНО | ≥20 мест, в т.ч. `core/src/Views/Internal/SqlSavedViewRepository.php:175,180,185`; `media/src/Internal/SqlMediaLibrary.php:54,78,85,94`; `tenancy/src/Lifecycle/AccountLifecycle.php:106,119,188`; `auth/src/Access/DefaultAccessChecker.php:197`; `crm/src/Support/RecordRules.php:102` |
| G2 | Мутабельное статическое состояние с tenant/user/locale | ОПРОВЕРГНУТО ПОЧТИ ПОЛНОСТЬЮ | Найдено только два булевых флага-гварда: `ImpersonatedModelWriteGuard.php:42`, `SystemRoleImmutable.php:20`. Ни одного `static $currentTenant/User/Locale` |
| G3 | Tenant-зависимые сервисы зарегистрированы как `singleton` | ПОДТВЕРЖДЕНО | `CoreServiceProvider.php:65-67,76`; `TenancyServiceProvider.php:191-192,219` |

**Итог:** из 29 проверенных пунктов 23 подтверждены полностью, 3 частично, 4 опровергнуты
(A2, E2 в части SQL, E3, G2). Один P0 найден при сверке и отсутствует в обоих аудитах (NEW-1).

## 2. Где аудиты противоречат друг другу и что принято

| Тема | Аудит №1 | Аудит №2 | Принято владельцем | Основание |
|:--|:--|:--|:--|:--|
| `search-meilisearch` | Влить в `corex/search` как optional driver, удалить пакет | Оставить отдельным пакетом-интеграцией, переименовать неймспейс | Оставить пакет, переименовать неймспейс в `CoreX\Search\Meilisearch`, ввести `SearchManager` | Разделение по SDK технически обосновано: приложение без Meilisearch не тянет SDK. Ломающая дельта меньше, а корневая причина — коллизия биндинга, а не факт разделения. **Следствие, которого нет ни в одном аудите:** рекомендованный неймспейс сам создаёт расщепление `CoreX\Search\` между двумя пакетами — то есть дефект класса A2a. Допустимо только по правилу host-reserved namespace (дизайн §2.3): хост объявляет резервацию и не определяет под ней ни одного класса |
| `intl-search` | Удалить пакет, распределить классы | Переименовать в `search-intl` либо влить | Переименовать в `corex/search-intl`, запретить владение контрактами | E2/E3 показали: классы — тонкие декораторы, saved views принадлежат ядру. Обоснование удаления у №1 и «владеет SQL» у №2 оба неверны. Пакет уже disabled-by-default и не входит в base lock |
| `corex/attributes` ↔ `corex/modules` | Оставить как есть | Разбить на три пакета | Один пакет, `modules` становится optional (`suggest`) с документированным fallback | Три пакета — комбинаторный рост без доказанного потребителя. Проблема в обязательности зависимости, а не в числе пакетов |
| Сокращение `corex/core` | Вынести Audit/Departments/Features/PubSub/Settings/Storage/Views | То же плюс Money и BaseModel | Отложено. Только фикс границ A2a/A2b | Извлечение 6–8 пакетов — отдельная программа с G-API/G-SCHEMA, вне выбранной глубины |
| `DataScope` | Разложить на контексты | Разложить на `AccessContext`/`StorageContext` | Сейчас убрать только `Closure $authorize`; полная декомпозиция — отдельным ADR | Closure в объекте, пересекающем queue/workflow, — доказанный дефект (RAG:✅). Остальная декомпозиция меняет `shared-data-foundation/1` шире, чем требуется |
| Механика правки плана | — | «P0R как предшественник P2–P9» | Новая фаза P12 как блокирующий предшественник P8 | P2–P7 терминальны; протокол разрешает реопен только при доказанном контрактном конфликте с `phase-reentry-justification/v1`. Схема аудита №2 неисполнима в текущем состоянии |

## 3. Принятые рекомендации, попадающие в P12

| Рекомендация | Источник | Item |
|:--|:--|:--|
| Починить контракт модуля `intl` | сверка (NEW-1) | P12.1 |
| Убрать импорт `StanclTenancyManager` из ядра | сверка (A2b) | P12.1 |
| Объявить недостающие cross-package зависимости | оба аудита (A3) | P12.1 |
| `test:packages` на все 20 пакетов, скрипты для `core`/`modules` | аудит №2 (F1/F2) | P12.2 |
| Гейт графа пакетов: undeclared deps, чужой `Internal`, расщеплённый неймспейс | оба аудита | P12.3 |
| `import-export` → `transfer`, `Support/` → `Data/` | оба аудита (D1/D5) | P12.4 |
| `SearchManager`, снятие коллизии биндинга, fail-closed конфиг, `null`-driver | аудит №1 (C1/C2) | P12.5 |
| `intl-search` → `search-intl`, bridge-политика | аудит №2 (E1) | P12.6 |
| Типизированный table-map, обязательная schema-валидация, optional `modules` | оба аудита (B1/B2/B4) | P12.7 |
| Убрать `Closure` из `DataScope` | оба аудита | P12.8 |

## 4. Дефекты самих аудитов — не переносить в код

| Дефект | Где | Почему важно |
|:--|:--|:--|
| `public scalar\|null $value` | аудит №1, Filter AST | В PHP нет типа `scalar`. Код не компилируется |
| `$this->connection()` у наследника `Manager` | аудит №1, `AttributeStoreManager` | `Illuminate\Support\Manager` не имеет метода `connection()` (проверено по `vendor/laravel/framework/src/Illuminate/Support/Manager.php`) |
| `qossmic/deptrac` | аудит №1, Internal | Пакет заброшен, переехал в `deptrac/deptrac` (RAG:✅). Второй движок анализа не вводится: гейт P12.3 реализуется repo-local скриптом по образцу существующих `scripts/contract-guard.sh` и `scripts/anti-weakening-gate.sh`. `deptrac/deptrac` и `phpat/phpat` отклонены как новая dev-зависимость без доказанной пользы сверх имеющегося паттерна гейтов |
| Матрица «Laravel 12, 13» как цель | оба аудита | В репозитории уже стоит `laravel/framework v13.32.0`; у Laravel 12 bug-fix закончился 13.08.2026 (RAG:✅) |
| «`corex/core` не устанавливается standalone из-за `DataScope`» | аудит №2, P0 | Ложно: класс в ядре. Настоящая причина другая (A2a/A2b) |
| «`SqlSavedViewLabelReader` содержит SQL-персистентность» | аудит №2, Intl/Search | Ложно: делегирует `TranslationReader` |
| «Saved views принадлежат panels/views» | оба аудита | Ложно: владелец — `corex/core` |
| `replace: {"corex/import-export": "self.version"}` как совместимость неймспейса | оба аудита | `replace` влияет только на solver Composer и не сохраняет PHP-неймспейс (RAG:✅). Для P12.4 выбран hard rename |

## 5. Отложено с фиксацией в ledger и ADR-предложениях (не входит в P12)

Подтверждено кодом, но вне выбранной глубины. P12.9 обязан внести эти пункты в
репозиторно-локальный ledger `docs/audit-2026-09-17-deferred.md` (по форме существующей
триады `docs/audit-2026-09-14*.md`) и сформулировать ADR-предложения для Brain-vault:
спека и ADR по правилам репозитория живут в vault, а не в дереве кода.

| Отложенная работа | Пункт | Куда |
|:--|:--|:--|
| Explicit `SetAttribute`/`RemoveAttribute`, presence-тип, различение missing/null/remove | B5 | ledger + ADR-предложение для vault |
| Разделение `DatabaseTransferRunner` на claim/read/apply/checkpoint, таксономия ошибок, вынос внешнего вызова из retryable-транзакции, снятие `catch (Throwable)` | D2/D3/D4 | ledger + ADR-предложение для vault |
| Полная декомпозиция `DataScope` на `AccessContext`/`StorageContext` | A1 | ledger + ADR-предложение для vault |
| Сокращение `corex/core` | — | ledger + ADR-предложение для vault |
| Вынос `config()` из domain/repository/engine в типизированные options-объекты | G1 | ledger + ADR-предложение для vault |
| Перевод tenant-зависимых `singleton` в `scoped`/resettable | G3 | ledger + ADR-предложение для vault |
| Перенос DTO из `core/src/Contracts/` в `core/src/Data/` | B7 | ledger, требует G-API |
| Публичный API census + snapshot (`roave/backward-compatibility-check`) | — | ledger + ADR-предложение для vault |
| Ослабление пина `meilisearch/meilisearch-php` | C3 | ledger, требует G-DEPENDENCY; актуальная ветка SDK — `2.0.0-beta.*` |

## 6. Что аудиты предлагают, а P12 сознательно отклоняет

| Отклонено | Почему |
|:--|:--|
| Каталоги `packages/kernel/`, `capabilities/`, `integrations/`, `products/` | Физическое перемещение 20 пакетов не даёт проверяемой пользы без закреплённых правил зависимостей; правила закрепляет гейт P12.3 при плоской структуре. Аудит №2 сам пишет «физическое перемещение можно отложить» |
| Freeze всей функциональности (Этап 0 аудита №1) | Противоречит действующему плану: P8–P11 остаются исполнимыми после P12 |
| `Deptrac` как второй движок анализа | Пакет заброшен; `phpat` переиспользует существующий прогон PHPStan |
| Отдельный пакет на каждый storage/search-провайдер | Запрещено доктриной (`docs/dev-doctrine.md`) и самими аудитами в разделах bridge-политики |
| Немедленный public-API freeze | Аудит №1 сам ставит условием два потребителя (CRM + CMS); второго потребителя нет |
