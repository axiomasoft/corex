---
id: D41
date: 2026-09-18
status: accepted
item: P12
items: [P12, P12.1, P12.2, P12.3, P12.4, P12.5, P12.6, P12.7, P12.8, P12.9]
supersedes: []
superseded_by: null
---

# D41 — ребейзлайн границ пакетов по двум внешним аудитам

## Решение

Владелец поручил разобрать два независимых внешних аудита
(`audits/2026-09-17-perplexity-1.md`, `audits/2026-09-17-perplexity-2.md`),
перепроверить каждое утверждение самостоятельно и оформить корректирующую работу
исполнимым планом для модели-исполнителя Composer 2.5. Аудиты имеют статус
non-normative сырья: из 29 проверенных утверждений 23 подтверждены кодом, 3 частично,
4 опровергнуты, а один P0 не найден ни одним аудитом и обнаружен при сверке.
Нормативные документы — `research/P12-audit-reconciliation.md` (что подтверждено)
и `research/P12-rebaseline-design.md` (единственный источник кода фазы).

Работа оформляется **новой фазой P12** как блокирующим предшественником P8, а не
реопеном P1–P7. P2–P7 терминальны; протокол допускает реопен только при доказанном
контрактном конфликте с `phase-reentry-justification/v1`, и такого конфликта нет:
P12 не меняет принятые контракты, кроме одного пункта под G-API. Предложенная
аудитом №2 схема «P0R как предшественник P2–P9» в текущем состоянии плана неисполнима.

Разрешены ровно девять локальных изменений.

**P12.1 — три P0.** `packages/intl/src/IntlModule.php` не соответствует контракту
`CoreX\Modules\CorexModule`: не наследует базовый класс, объявляет
`static descriptor(): ModuleDescriptor` вместо `manifest(): Manifest` и ссылается
на класс `ModuleDescriptor`, которого нет ни в одном пакете репозитория.
`ModuleDiscovery` бросает `InvalidManifestException::notACorexModule`, то есть любое
приложение с установленным `corex/intl` падает на discovery. Класс переписывается
на `Manifest::make('corex/intl')` без `tablePrefix()` — таблицы пакета используют
префикс `corex_`, а `Manifest` допускает только 2–3 строчные буквы, поэтому любое
значение было бы ложью о схеме. Из `packages/core/src/Tenancy/Contracts/TenancyManager.php`
удаляется `use CoreX\Tenancy\StanclTenancyManager;` — контракт ядра ссылался на
реализацию из `corex/tenancy`; импорт использовался только в docblock и заменяется
текстовой формулировкой. Перенос класса в ядро и добавление `corex/tenancy`
в `require` ядра запрещены. Объявляются фактические зависимости: `corex/intl` →
`corex/modules`, `corex/tasks-workflow` → `corex/workflow`.

**P12.2 — контур тестов.** `test:packages` расширяется с 8 пакетов до 20;
`corex/core` и `corex/modules` получают собственные `test`/`test:coverage`/`analyse`/`lint:check`
по форме остальных 18 пакетов. Существующее разделение SQLite-лейна и pg-лейна
сохраняется. Тесты не выдумываются: отсутствие `tests/` у пакета раскрывается
в receipt как явная дыра покрытия.

**P12.3 — гейт графа пакетов.** Вводится `scripts/package-graph-gate.php` +
`tests/Arch/PackageGraphTest.php` с тремя правилами: undeclared cross-package
зависимость, импорт чужого `Internal`, владение неймспейсом. Второй движок анализа
не вводится: `qossmic/deptrac` заброшен, `deptrac/deptrac` и `phpat/phpat` отклонены
как новая dev-зависимость без пользы сверх существующего паттерна гейтов
(`scripts/contract-guard.sh`, `scripts/anti-weakening-gate.sh`, `tests/Arch/LayerTest.php`).
Гейт читает сырые `use`-строки, а не разрешённые классы, и обязан называть
файл, строку, импорт и пакет-владельца; вердикт без адреса нарушения не принимается.

Расщепление неймспейса между Composer-пакетами допускается **только** при трёх
одновременных условиях: хост объявляет резервацию, хост определяет ноль классов
под префиксом, адаптер требует хост в `require`. `CoreX\Search\Meilisearch\`
условиям соответствует. `CoreX\Tenancy\` их нарушает — ядро определяет там 16 классов, —
поэтому вносится как именованное просроченное legacy-исключение с ADR и условием
снятия и печатается в отчёте на каждом прогоне. Развод `CoreX\Tenancy\` в P12
не выполняется: он потребовал бы перемещения классов ядра и сломал бы публичный API
во всех пакетах.

**P12.4 — `corex/import-export` → `corex/transfer`,** PSR-4 `CoreX\ImportExport\` →
`CoreX\Transfer\`, `src/Support/` → `src/Data/`, конфиг и его ключ. Только жёсткий rename
через `git mv`, без `replace` и без `class_alias`: `replace` влияет исключительно на
solver Composer и не сохраняет ни один PHP-неймспейс, а мост нужен лишь для неймспейса,
уже используемого извне. Изменение поведения в этом item запрещено: разделение
`DatabaseTransferRunner`, таксономия ошибок, вынос внешнего вызова из retryable-транзакции
и снятие `catch (Throwable)` отложены в ADR.

**P12.5 — консолидация search.** `corex/search` и `corex/search-meilisearch` сейчас оба
регистрируют биндинг `SearchProvider`, и результат зависит от порядка загрузки провайдеров.
Вводится `SearchManager extends Illuminate\Support\Manager` с драйверами `postgres` и `null`;
`SearchProvider` резолвится через менеджер. Адаптер Meilisearch больше не биндит
`SearchProvider`, а регистрирует драйвер через `extend()` в `boot()`, и его валидация
конфига переносится внутрь фабрики драйвера — сейчас решение принимается в `register()`,
до того как приложение успело переопределить конфиг. Молчаливый возврат
`UnsupportedSearchProvider` при выбранном, но сломанном драйвере заменяется исключением;
класс сохраняется в роли явного драйвера `null`, и дефолт конфига меняется с
`'unsupported'` на `'null'`. Ключ `enabled` адаптера удаляется: два независимых
переключателя дают неопределимое состояние. Охрана `class_exists(\Meilisearch\Client::class)`
обязательна. Пин `meilisearch/meilisearch-php` `1.16.1` из D40 остаётся в силе:
смена ограничения требует G-DEPENDENCY и отложена в ADR.

**P12.6 — `corex/intl-search` → `corex/search-intl`,** PSR-4 `CoreX\IntlSearch\` →
`CoreX\SearchIntl\`. Неймспейс плоский, а не `CoreX\Search\Intl\`: вторая резервация
в `CoreX\Search\` ради пакета-декоратора из нескольких классов не оправдана.
Фиксируется bridge-политика: пакет-мост декорирует контракты своих хостов и не владеет
предметными контрактами. Оба аудита в этом разделе опираются на ложные предпосылки —
saved views принадлежат `corex/core`, а не `panels`/`views`, и `SqlSavedViewLabelReader`
не содержит SQL, а делегирует `TranslationReader`; поэтому ни удаление пакета (аудит №1),
ни довод «владеет SQL-персистентностью» (аудит №2) не приняты.

**P12.7 — `corex/attributes`.** Имена таблиц выносятся из четырёх классов
(`JsonbAttributeStore`, `EavAttributeStore`, `QueuedSchemaOperations`,
`SchemaOperationWorker`) в иммутабельный `AttributeTables`, собираемый один раз
в провайдере и внедряемый через конструктор, с валидацией идентификатора и уникальности —
имя таблицы в query builder не является bound-параметром. Значения по умолчанию равны
текущим захардкоженным строкам символ в символ; итоговый SQL не меняется, каталог
миграций неизменен, DB-контракт `FROZEN v1.0` не затрагивается. `config()` внутри
store-классов не появляется. Nullable `FieldCatalogue` становится обязательным
и не-nullable, ветки пропуска валидации по схеме удаляются. `corex/modules` переносится
из `require` в `suggest` с внесением пары в allowlist гейта и указанием файла-охранника;
существующий fallback на `UnavailableAttributes` признан корректным. Разбиение пакета
на три (предложение аудита №2) не выполняется: корневая причина — обязательность
зависимости, а не число пакетов.

**P12.8 — `Closure` из `DataScope` [OWNER-GATE:G-API].** `public Closure $authorize`
удаляется из `DataScope`; проверка доступа объявляется интерфейсом в
`packages/core/src/Contracts/`, резолвится на исполняющей стороне из контейнера
и возвращает `readonly`-решение с кодом причины, пригодным для аудита. `DataScope`
пересекает границу очереди, а PHP не сериализует `Closure`; дополнительно closure
захватывает лексическое окружение, поэтому решение о доступе, принятое при постановке
задачи, переиспользуется при исполнении — разрыв time-of-check/time-of-use, не
диагностируемый на `QUEUE_CONNECTION=sync`. `laravel/serializable-closure` отклонён:
он делает код транспортируемым, но транспортируемость поведения не даёт семантики
авторизации — подписанный payload доказывает неизменность данных, а не право актора
выполнить операцию сейчас. Полная декомпозиция на `AccessContext`/`StorageContext` (предложение обоих аудитов)
не выполняется: она меняет `shared-data-foundation/1` шире необходимого. Item не
закрывается без теста, сериализующего и восстанавливающего `DataScope`.

**P12.9 — печать фазы** и оформление отложенного в ADR: presence-семантика
`ValuePatch`/`ValueBag`, декомпозиция `DatabaseTransferRunner`, полная декомпозиция
`DataScope`, сокращение `corex/core`, вынос `config()` из domain/repository/engine,
перевод tenant-зависимых `singleton` в `scoped`, перенос DTO из `core/src/Contracts/`
в `core/src/Data/`, public-API census со snapshot, ослабление пина SDK Meilisearch.

Не разрешено: создавать или удалять Composer-пакеты; менять
`packages/*/database/migrations/**`; добавлять runtime- или dev-зависимости сверх
перечисленных; ослаблять существующие гейты; вводить каталоги
`packages/kernel|capabilities|integrations` и `products/`; замораживать публичный API;
копировать PHP из `audits/*`.

## Почему

Оба аудита содержат фактические ошибки, поэтому исполнять их как спецификацию нельзя.
Ключевые опровержения: `TenantContext` объявлен в `packages/core/src/Tenancy/TenantContext.php`
с `namespace CoreX\Tenancy`, то есть заявленный аудитом №2 P0 «ядро не ставится standalone
из-за зависимости на `corex/tenancy`» неверен по причине, хотя рядом существует настоящая
утечка в `TenancyManager`; saved views принадлежат ядру; мутабельного статического
tenant/user/locale-состояния в дереве нет — найдены только два булевых флага-гварда.
Одновременно ни один аудит не заметил дефекта, который ломает discovery модулей.
Отсюда дисциплина фазы: код берётся из `research/P12-rebaseline-design.md`, а не из аудитов.

Аудиты также предлагают невалидный PHP: тип `scalar`, которого в языке нет, и
метод `connection()` у наследника `Illuminate\Support\Manager`, которого в классе нет
(проверено по `vendor/laravel/framework/src/Illuminate/Support/Manager.php`).
Соединение в `SearchManager` берётся через `$this->container->make('db')->connection()`.

Внешние предпосылки проверены по первичным источникам; полный разбор с альтернативами,
режимами отказа и их смягчением — в `research/P12-design-rag.md`, сохранённые ответы
с SHA256 — в `artifacts/P12-design/` (`integrity.json`). Запросы нейтральны по D6.
Подтверждено: `replace` меняет только информацию для dependency solver Composer и не
сохраняет `Old\Namespace\Class`: схема Composer описывает `replace` ровно как «map of
packages that are replaced by this package». `suggest` описан как «suggested packages
that can enhance or work well with this package» — то есть корректное место для
опциональной зависимости. `class_alias` сохраняет class-like символы, а жёсткий rename
уместен при отсутствии внешних потребителей. PHP прямо документирует «Closures cannot be
serialized» с фатальной ошибкой `Serialization of 'Closure' is not allowed`, а
`dispatchSync` обрабатывает задачу в текущем процессе и потому скрывает дефект.
`mergeConfigFrom` документирован как вызов внутри `register()` — это и есть граница
композиции, где читается конфиг. Octane документирует, что singleton, разрешённый на
этапе загрузки, переиспользуется между запросами, что и делает tenant-зависимый singleton
опасным. Источники (сырые байты, коды и SHA256 — в
`artifacts/P12-design/primary-source-retrieval.json`):

- https://getcomposer.org/doc/04-schema.md
- https://www.php.net/manual/en/function.serialize.php
- https://laravel.com/docs/13.x/queues
- https://laravel.com/docs/13.x/packages
- https://laravel.com/docs/13.x/octane
- https://raw.githubusercontent.com/laravel/serializable-closure/master/README.md

Одно утверждение RAG-ответа прямую проверку **не прошло** и в качестве доказательства
не используется: README `laravel/serializable-closure` не содержит слова `readonly`
и перечисляет только два caveat — неподдерживаемую сериализацию в REPL и неразличимость
нескольких замыканий с одинаковой сигнатурой на одной строке исходника. Обоснование
P12.8 от этого утверждения не зависит.

Отдельно проверено состояние инструментов: `qossmic/deptrac` заброшен и переехал
в `deptrac/deptrac`; целевая ветка Laravel — 13.x, у 12.x bug-fix завершён 13.08.2026,
а в репозитории уже стоит `laravel/framework v13.32.0`, поэтому матрица «Laravel 12, 13»
как цель из аудитов устарела и новых ограничений версий P12 не вводит.

## Consequences

P8–P11 блокируются до закрытия P12: имена `corex/transfer` и `corex/search-intl`
и резолв `SearchProvider` через `SearchManager` меняются в P12, и работа поверх старых
имён породила бы переделку. P1–P7 не реопенятся.

Этот decision — matching owner receipt только для одного защищённого изменения:
удаления `Closure $authorize` из `DataScope` (G-API, `shared-data-foundation/1`).
Он **не** выдаёт G-SCHEMA, G-STORAGE, G-DEPENDENCY и не разрешает никакое иное
изменение принятых контрактов. Ослабление пина SDK Meilisearch, перенос DTO из
`core/src/Contracts/` и сокращение `corex/core` требуют отдельных receipt'ов.

`CoreX\Tenancy\` остаётся расщеплённым неймспейсом как объявленное просроченное
исключение: дефект зафиксирован, назван в отчёте гейта на каждом прогоне и снимается
при сокращении `corex/core`. Молчаливое прохождение запрещено; пропущенная или
отключённая проверка зелёной не считается. Пакеты без каталога `tests/` раскрываются
в receipt P12.2 как явные дыры покрытия, а не закрываются выдуманными тестами.

Спека и ADR по правилам репозитория живут в Brain-vault, а не в дереве кода, поэтому
P12.9 создаёт репозиторно-локальный ledger отложенного по форме существующей триады
`docs/audit-2026-09-14*.md` и формулирует ADR-предложения для vault, не подменяя
vault-owned ADR файлами в репозитории. Ledger фиксирует подтверждённые, но не
исправленные дефекты:
неразличимость missing/null/remove в `ValuePatch`/`ValueBag`, единая retryable-транзакция
и `catch (Throwable)` в `DatabaseTransferRunner`, `config()` в доменном слое,
tenant-зависимые `singleton`. Они остаются известными и не считаются решёнными.
