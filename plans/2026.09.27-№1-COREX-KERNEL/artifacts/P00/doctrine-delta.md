# Doctrine delta — PLAN3 (P00.2)

Статус: **норма** P00.2 (2026-09-28). Что меняется в `docs/architecture/*` и какая фаза PLAN3 это делает. Правка `docs/**` —
только фазой-владельцем (P00 документы не меняет). Имена — `research/03-glossary.md`; архитектура — `research/04-architecture.md`.
Формат строки — `artifacts/P00-design/examples.md` §5; проверка — `check-maps.py --doctrine` (ключ `(документ, ID)`;
`§N` — N-й раздел `## ` документа; `NEW-<n>` — новое правило, только `add`; `NON-<n>` — анти-цели таблицы конституции, не входят в 58 статей).

Отмена статьи (`remove`) сохраняет ID незанятым (конституция: «не переиспользуется»). Поле «Гейт» называет машинную
проверку, которую фаза вводит или переписывает; `ревью` допустим только там, где D17/D18 не требуют машины.

| Документ | ID | Действие | Новое правило (одно предложение) | Фаза | Гейт |
|:--|:--|:--|:--|:--|:--|
| constitution.md | PKG-001 | keep | — | — | — |
| constitution.md | PKG-002 | rewrite | Зависимости идут вниз по уровням: `axiomasoft/corex*` не зависит от продуктов (`flex/*`, `products/*`). | P02 | `tests/Arch/LayerTest` + package-graph-gate |
| constitution.md | PKG-003 | rewrite | `axiomasoft/corex` не зависит ни от одного пакета монорепо и не называет их типы, таблицы, config-ключи и команды; пакет подключается к ядру только через `#[Spi]` и реестры модулей. | P02 | `check-maps.py --dependencies` → ArchitectureTest kernel-deps |
| constitution.md | PKG-004 | keep | — | — | — |
| constitution.md | PKG-005 | rewrite | Необязательная интеграция — отдельный bridge-пакет либо `suggest` + регистрация за проверкой наличия; жёсткое ребро capability→capability допустимо только если без него пакет бессмыслен. | P07 | package-graph-gate + composer-dependency-analyser |
| constitution.md | PKG-006 | keep | — | — | — |
| constitution.md | PKG-007 | rewrite | В `packages/*` нет литералов продуктов (`flex`, `Flex\`), кроме явного allow-list. | P02 | `tests/Arch/LayerTest` |
| constitution.md | PKG-008 | rewrite | Каждый пакет `packages/*` самодостаточен: `composer test` проходит из его каталога с ядром и объявленными зависимостями, и у него есть CI-джоб. | P09 | `composer test:packages` per-package vendor |
| constitution.md | PKG-009 | remove | — | P02 | package-map check (`import-export` отсутствует) |
| constitution.md | STR-001 | remove | — | P03 | feature-modules gate |
| constitution.md | STR-002 | rewrite | Один use case живёт в одном feature module пакета (`<Module>/`), не размазываясь по слоевым каталогам. | P03 | feature-modules gate |
| constitution.md | STR-003 | rewrite | Корень `src/` пакета содержит только публичный provider, feature modules, `Laravel/` и `Testing/` (D35). | P03 | feature-modules gate |
| constitution.md | STR-004 | keep | — | — | — |
| constitution.md | STR-005 | rewrite | `Internal/` живёт внутри feature module (`<Module>/Internal/`) и закрыт для других пакетов. | P03 | package-graph-gate R2 + ownership-гейт |
| constitution.md | STR-006 | rewrite | Публичные категории модуля — только `Contracts`, `Data`, `Enums`, `Events`, `Exceptions`; слоевые корзины уровня пакета запрещены. | P03 | feature-modules gate |
| constitution.md | API-001 | rewrite | Публичная поверхность — символы с `#[Api]`/`#[Spi]` вне `Internal`, перечисленные в `resources/contracts/php-api.json`; `extra.corex.public-api` удаляется. | P04 | api-attributes gate |
| constitution.md | API-002 | keep | — | — | — |
| constitution.md | API-003 | rewrite | Межпакетный импорт допустим только символа с `#[Api]` или `#[Spi]`. | P04 | api-attributes gate |
| constitution.md | API-004 | keep | — | — | — |
| constitution.md | API-005 | keep | — | — | — |
| constitution.md | API-006 | keep | — | — | — |
| constitution.md | API-007 | rewrite | Удаление публичного символа или операции внешнего API — только в мажоре после объявленной депрекации, начиная с `1.0.0-rc.1`; до него действует D01 (без депрекаций и мостов). | P09 | Roave BC gate + OpenAPI snapshot diff |
| constitution.md | API-008 | keep | — | — | — |
| constitution.md | API-009 | rewrite | Контракт — интерфейс в `<Module>/Contracts/` с `#[Api]` (вызывает потребитель) или `#[Spi]` (реализует/вызывает расширение); `#[Api]`-сигнатура не ссылается на internal-тип, Eloquent-модель и `Collection`. | P04 | api-attributes gate |
| constitution.md | DATA-001 | keep | — | — | — |
| constitution.md | DATA-002 | keep | — | — | — |
| constitution.md | DATA-003 | keep | — | — | — |
| constitution.md | DATA-004 | keep | — | — | — |
| constitution.md | DATA-005 | rewrite | Выпущенная миграция неизменна начиная с baseline v1 (`1.0.0-rc.1`); до него схема сквошится в baseline P08. | P08 | migration-diff gate от тега rc |
| constitution.md | DATA-006 | keep | — | — | — |
| constitution.md | RUN-001 | rewrite | Глобального изменяемого «текущего тенанта» нет: контекст передаётся явно или через порт `CoreX\Context`, резолвится на границе запроса/джоба. | P05 | Octane-сценарий «два запроса — разные tenant» |
| constitution.md | RUN-002 | keep | — | — | — |
| constitution.md | RUN-003 | keep | — | — | — |
| constitution.md | RUN-004 | rewrite | Текущее время берётся из инъектируемого PSR-20 `ClockInterface` (`CoreX\Time`). | P05 | PHPStan-правило запрета `now()`/`time()` в src |
| constitution.md | RUN-005 | rewrite | Состояние не переживает границу запроса/джоба: static mutable state и runtime-кэши вне immutable-снимков запрещены. | P05 | PHPStan-правило static state + Octane-сценарий |
| constitution.md | CFG-001 | keep | — | — | — |
| constitution.md | CFG-002 | rewrite | `config()`/`env()` вызываются только в service providers и командах конфигурации; runtime-путь получает Options конструктором и не перечитывает config. | P05 | PHPStan-правило `config()` вне providers |
| constitution.md | CFG-003 | rewrite | Каждый пакет строит immutable `<Name>Options::fromArray()` с `ViolationList::failIfAny()`; static `Config` отсутствует. | P05 | typed-config gate |
| constitution.md | CFG-004 | keep | — | — | — |
| constitution.md | CFG-005 | keep | — | — | — |
| constitution.md | CFG-006 | rewrite | Идентификаторы пакета выводятся из его имени: config `corex-<kebab>`, tags `corex-<kebab>-*`, env `COREX_<UPPER_SNAKE>_*`, artisan `corex:<kebab>:<verb>` (ядро — `corex`, `COREX_<MODULE>_*`, `corex:<module>:<verb>`). | P02 | ownership-гейт идентификаторов |
| constitution.md | MSG-001 | keep | — | — | — |
| constitution.md | MSG-002 | keep | — | — | — |
| constitution.md | MSG-003 | rewrite | Опубликованное сообщение — CloudEvents 1.0 structured JSON с `type: corex.<package>.<event>` и extension-атрибутами `corexaccount`, `corexactor`, `corextrace`. | P06 | wire-contract tests |
| constitution.md | MSG-004 | rewrite | Имя опубликованного события — `corex.<package>.<event>`, breaking payload — суффикс `.v2` и новый `dataschema`. | P06 | wire-contract tests |
| constitution.md | MSG-005 | keep | — | — | — |
| constitution.md | MSG-006 | keep | — | — | — |
| constitution.md | MSG-007 | keep | — | — | — |
| constitution.md | MSG-008 | keep | — | — | — |
| constitution.md | EXT-001 | rewrite | Набор механизмов расширения закрыт: compiled registries модулей, `#[Spi(Implement)]`-порты с `bindIf`, декорация `extend`, опубликованные сообщения; новый механизм — только через D#. | P04 | api-attributes gate + ревью |
| constitution.md | EXT-002 | keep | — | — | — |
| constitution.md | EXT-003 | keep | — | — | — |
| constitution.md | EXT-004 | keep | — | — | — |
| constitution.md | EXT-005 | keep | — | — | — |
| constitution.md | EXT-006 | keep | — | — | — |
| constitution.md | EXT-007 | keep | — | — | — |
| constitution.md | EXT-008 | keep | — | — | — |
| constitution.md | EXT-009 | rewrite | Идентификатор модуля равен composer-имени (`axiomasoft/corex*`) и хранится в `mod_*`; смена до 1.0 выполняется сквошем схемы P08, после — миграцией данных. | P02 | module-id check в package-map |
| constitution.md | NEW-1 | add | Ядро владеет `CoreX\` только через glossary §Kernel modules, §Root symbols и §Reserved segments, пакет — ровно одним корнем `CoreX\<Pascal>\` → `src/`; FQCN определяет пакет. | P02 | ownership-гейт + `check-maps.py` |
| constitution.md | NEW-2 | add | Тест пакета импортирует из другого пакета только `#[Api]/#[Spi]` и его `Testing`-модуль; `tests/` и `Internal` чужого пакета недоступны. | P02 | `check-maps.py --dependencies` (test-support-edge, internal-edge) → package-graph-gate |
| constitution.md | NON-003 | rewrite | В отдельные Composer-пакеты не выделяются модули ядра Features, Departments, Settings, Audit, Messaging, Storage; Money и SavedViews переносятся в существующие пакеты intl и panels (D27/D28). | P02 | package-map check |
| constitution.md | NON-008 | rewrite | Транспортный подпакет HTTP (`operations-http`) не выделяется; `corex-tools` и `corex-webhooks` — пакеты по D12/D31, HTTP-доставка остаётся в `corex-operations`. | P02 | package-map check |
| constitution.md | NEW-3 | add | Безопасность, транзакционность, outbox-запись, авторизация и строгость входа API не отключаются конфигурацией; расширение — именованной точкой. | P05 | typed-config gate: Options без флагов отключения инвариантов |
| api-policy.md | §1 | rewrite | Публичная поверхность объявляется атрибутами `#[Api]/#[Spi]` на символе и манифестом `resources/contracts/php-api.json`, собранным сканером. | P04 | api-attributes gate |
| api-policy.md | §2 | rewrite | Граница пакета — корень FQCN (ownership-гейт), граница реализации — сегмент `Internal` модуля; `@internal` только снимает обещание совместимости. | P04 | ownership-гейт + package-graph-gate R2 |
| api-policy.md | §3 | keep | — | — | — |
| api-policy.md | §4 | rewrite | Ломающее изменение фиксирует Roave per package, блокирующий начиная с `1.0.0-rc.1`. | P09 | Roave BC gate |
| api-policy.md | §5 | rewrite | `operation-contract/1` замораживается на `1.0.0-rc.1`; до него P02–P08 перегенерируют снимок `api/v1` по D01. | P09 | OpenAPI snapshot test |
| api-policy.md | §6 | rewrite | Нагрузочный контракт API принадлежит модулям Cost/Bulk пакета `corex-operations` и публикуется в его OpenAPI-снимке. | P06 | OpenAPI snapshot test |
| api-policy.md | §7 | rewrite | Ошибка — RFC 9457 problem+json со строковым `code` из реестра `CoreX\Errors`, владелец кода — пакет по префиксу. | P04 | error-code registry test |
| api-policy.md | §8 | rewrite | Межпакетный импорт разрешён только для `#[Api]/#[Spi]`-символов (статья API-003). | P04 | api-attributes gate |
| config-policy.md | §1 | keep | — | — | — |
| config-policy.md | §2 | rewrite | Новый ключ вводится полем `<Name>Options` и записью в `config/corex-<kebab>.php` своего пакета. | P05 | typed-config gate |
| config-policy.md | §3 | rewrite | `config()`/`env()` допустимы только в service providers и командах конфигурации, никогда на runtime-пути (D08). | P05 | PHPStan-правило `config()` вне providers |
| config-policy.md | §4 | rewrite | Валидация — `ViolationList::failIfAny()` при boot; опасная комбинация в `production` — исключение. | P05 | CI `config:cache` + boot-тест |
| naming.md | §1 | keep | — | — | — |
| naming.md | §2 | keep | — | — | — |
| naming.md | §3 | keep | — | — | — |
| naming.md | §4 | keep | — | — | — |
| naming.md | NEW-1 | add | Namespace пакета = путь, один корень `CoreX\<Pascal>` на пакет, PascalCase всех kebab-частей без дефисов. | P02 | `check-maps.py` + composer psr-4 gate |
| naming.md | NEW-2 | add | Имя пакета `axiomasoft/corex-<kebab>` называет назначение; module id = composer-имя. | P02 | package-map check + module-id test |
| naming.md | NEW-3 | add | Идентификаторы пакета (config, publish-tags, env, artisan) выводятся из `<kebab>` по glossary §Identifiers. | P02 | ownership-гейт идентификаторов |
| core-zones.md | §1 | rewrite | Граф зависимостей модулей ядра ацикличен, основание — Context, Data, Errors, Time, Config, Database; допустимые рёбра — allowlist KernelModuleTest, исходно — измеренный граф P00 (ниже) без рёбер, которые снимает D08. | P05 | KernelModuleTest (acyclic + allowlist) |
| core-zones.md | §2 | remove | — | P02 | `check-maps.py` (namespace = путь заменяет карту принадлежности) |
| core-zones.md | §3 | rewrite | Правила R-Z переписываются по модулям ядра (R-Z2: чужой `<Module>\Internal` запрещён); E-BASEMODEL (Database→Audit) и LITMUS_ELOQUENT относятся к `CoreX\Database`. | P03 | KernelModuleTest (R-Z2) |
| core-zones.md | §4 | remove | — | P02 | `check-maps.py` (корзины `Support/`, `Data/` растворены) |
| core-zones.md | §5 | remove | — | P02 | `check-maps.py` (цели FQCN — namespace-map) |
| core-zones.md | §6 | remove | — | P02 | package-map check (решено D27–D29) |
| core-zones.md | §7 | remove | — | P02 | — |
| core-zones.md | §8 | rewrite | Enforcement модулей ядра — `tests/Arch/KernelModuleTest.php` по glossary §Kernel modules. | P03 | KernelModuleTest |
| extension-policy.md | §1 | keep | — | — | — |
| extension-policy.md | §2 | keep | — | — | — |
| extension-policy.md | §3 | keep | — | — | — |
| extension-policy.md | §4 | keep | — | — | — |
| extension-policy.md | §5 | keep | — | — | — |
| extension-policy.md | §6 | keep | — | — | — |
| extension-policy.md | NEW-1 | add | Секции манифеста и отчёты, известные только пакету, вносятся в ядро через `ManifestSectionCompiler`, `FieldCastRegistry`, `ConfigReportSection`; ядро не называет типы и ключи пакетов. | P02 | `check-maps.py --dependencies` → ArchitectureTest kernel-deps |
| message-policy.md | §1 | keep | — | — | — |
| message-policy.md | §2 | rewrite | Конверт опубликованного сообщения — CloudEvents 1.0 structured JSON с атрибутами `corexaccount`, `corexactor`, `corextrace`. | P06 | wire-contract tests |
| message-policy.md | §3 | keep | — | — | — |
| message-policy.md | §4 | rewrite | Новый интеграционный контракт объявляет `type: corex.<package>.<event>` и `dataschema`; breaking — `.v2`. | P06 | wire-contract tests |
| message-policy.md | §5 | keep | — | — | — |
| message-policy.md | §6 | keep | — | — | — |
| message-policy.md | §7 | keep | — | — | — |
| message-policy.md | §8 | rewrite | Webhook-проекция принадлежит `corex-webhooks`: Standard Webhooks v1, тело — CloudEvents-конверт. | P06 | webhook contract tests |
| structure-profiles.md | §1 | remove | — | P03 | feature-modules gate (профили заменены D05) |
| structure-profiles.md | §2 | rewrite | Процедуры STR-002…STR-006 выполняются в терминах feature modules и `<Module>/Internal`. | P03 | feature-modules gate |
| structure-profiles.md | §3 | remove | — | P03 | feature-modules gate (долг tenancy снимает раскладка P02/P03) |
| structure-profiles.md | §4 | remove | — | P03 | — |
| structure-profiles.md | §5 | remove | — | P03 | — |
| table-ownership.md | §1 | rewrite | Разделённый `sys_` снимается: после baseline v1 каждый префикс таблицы принадлежит одному пакету, а владение по-прежнему объявляется по таблице (DATA-001). | P08 | table-ownership-gate |
| table-ownership.md | §2 | rewrite | Каталог таблиц переписывается на новые имена пакетов (SavedViews → panels, `mod_*` → ядро, таблицы webhooks → webhooks). | P08 | table-ownership-gate |
| taxonomy.md | §1 | rewrite | Тип пакета — `kernel`, `package`, `bridge`, `metapackage`; продукт вне `packages/`. | P02 | package-map check |
| taxonomy.md | §2 | rewrite | `extra.corex` не несёт `public-api`; публичность задают атрибуты и `php-api.json`. | P04 | api-attributes gate |
| taxonomy.md | §3 | rewrite | Распределение по пакетам — glossary §Packages (ядро + 19 релизных пакетов). | P02 | package-map check |

## Измеренный граф модулей ядра (P00, целевые FQCN по `namespace-map.tsv`)

Источник: `check-maps.py` (symbol table + references, `src/` ядра), 2026-09-28. Исходная точка allowlist KernelModuleTest.

```text
Audit         -> Config, Context, Database
Config        -> Audit, Context, Data, Database, Departments, Features, Messaging, Modules, Settings   # static Config/CoreOptions, снимает D08 (P05)
Context       -> Data
Data          -> Context, Messaging
Database      -> Audit, Config                                                                     # E-BASEMODEL; Config снимает D08
Departments   -> Config, Database, Messaging
Features      -> Config, Database, Messaging, Settings
Messaging     -> Config, Context, Observability
Modules       -> Audit, Config, Context, Features, Messaging, Settings
Observability -> Audit, Errors
Settings      -> Config, Database, Messaging
Storage       -> Context
```

Циклы сегодня: через `Config` (статический holder и `CoreOptions` всех модулей) и Context↔Data. Цель P05: после D08 модуль
`Config` не импортирует прикладные модули (каждый модуль владеет своими Options), Context↔Data разрешается выбором направления
в дизайне P05; KernelModuleTest фиксирует оставшийся ацикличный список.
