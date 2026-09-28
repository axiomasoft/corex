# Архитектура CoreX 1.0

Норма с P00.2; источник имён — research/03-glossary.md. При расхождении с D# побеждает D#; при расхождении имени — glossary.
Документ переживает план: P09 переносит его в `docs/architecture/` вместе с правками доктрины по `artifacts/P00/doctrine-delta.md`.
Машинные карты переносов — `artifacts/P00/{package-map,namespace-map,dependency-edges}.tsv`, проверка — `check-maps.py`.

> **Карта решений.** D01 совместимость (нет) · D02 vendor `axiomasoft` · D03 PHP ^8.4 / Laravel ^12|^13 · D04 namespace =
> путь, один корень на пакет · D05 feature modules + `Internal` · D06 `#[Api]/#[Spi]` + манифест · D07 DTO вместо моделей ·
> D08 typed config без static · D09 тонкие providers, без фасадов в домене · D10 строковые коды + registry + RFC 9457 ·
> D11 CloudEvents / Standard Webhooks / OpenAPI 3.1 · D12 split operations · D13 движок tenancy за адаптером · D14 access
> без AzGuard · D15 flex/crm вне релиза · D16 зависимости · D17 планка качества · D18 CI supply chain · D19 релиз ·
> D20 схема · D21 катящийся дизайн · D22 принципы экосистемы · D23 PLAN2 superseded · D24 rolling backlog ·
> D25 modules → ядро · D26 `CoreX\Context` · D27 Money → intl · D28 SavedViews → panels · D29 закрытый список модулей ядра ·
> D30 имена и набор пакетов · D31 швы split operations · D32 швы независимости ядра · D33 идентификаторы · D34 конвенции карт ·
> D35 граница тестов.

## 1. Принципы (сквозные, проверяемые; D22 = chatom PLAN2 §1)

1. **Ядро ничего не знает о пакетах-расширениях.** Ни import, ни строки FQCN, ни имени таблицы, ни config-ключа, ни команды,
   ни строки в docs ядра о конкретном пакете. Пакет подключается к ядру только через `#[Spi]` и реестры модулей
   (`CoreX\Modules\Contracts\{ManifestSectionCompiler,FieldCastRegistry}`, `CoreX\Config\Contracts\ConfigReportSection`, D27/D32).
   Гейт: `check-maps.py --dependencies` (P00) → ArchitectureTest kernel-deps (P02).
2. **Один владелец.** FQCN, таблица, config-ключ, publish-tag, artisan-команда, route-имя, wire `type`, код ошибки
   однозначно определяют пакет-владельца (ownership-гейт P01/P02 по glossary).
3. **Пакет самодостаточен:** `composer.json` + `src/` + `config/` + `database/` + `resources/` + `tests/` + `README.md`;
   `composer test` проходит из каталога пакета с ядром и объявленными зависимостями, без остальных пакетов. Тесты импортируют
   из других пакетов только `#[Api]/#[Spi]` и их `Testing`-модули (D35).
4. **Публичное — только объявленное** (`#[Api]`, `#[Spi]`), остальное internal; wire — отдельный версионируемый контракт.
5. **Инварианты не конфигурируются.** Безопасность, транзакционность, outbox-запись, авторизация, строгость входа API не
   отключаются config-ом; расширение — через именованные точки.
6. **Compile at boot → immutable runtime.** Конфигурация и реестры собираются при boot/optimize, runtime читает типизированные
   снимки из контейнера; ни `config()` в runtime-пути, ни static mutable state.
7. **Схема детерминирована.** Выпущенная миграция не меняется; новая форма — новая миграция (с `1.0.0-rc.1`).
8. **Стандарты вместо изобретений:** Standard Webhooks, CloudEvents 1.0, OpenAPI 3.1, JSON Schema 2020-12, RFC 9457,
   PSR-20 (clock), SemVer.
9. **Без алиасов и мостов до 1.0** (D01). После 1.0 — строгий SemVer + Roave per package.
10. **Модули максимально независимы (B8).** Жёсткое ребро пакет→пакет — только если без него пакет бессмыслен; иначе контракт в
    ядре + bridge-пакет или `suggest` + условная регистрация.

## 2. Пакеты

```text
axiomasoft/corex                     ядро (packages/core, CoreX\): Attributes, Audit, Config, Context, Data, Database,
                                     Departments, Errors, Features, Messaging, Modules, Observability, Settings, Storage, Time
axiomasoft/corex-access              авторизация платформы (tenant-boundary, entity-context, scope, матрица ролей) без AzGuard
axiomasoft/corex-custom-fields       типизированные пользовательские поля сущностей (бывш. corex/attributes)
axiomasoft/corex-intl                локали, переводы, валюты и деньги (+ MoneyCast из ядра), форматирование, территории
axiomasoft/corex-media               приватные медиафайлы tenant
axiomasoft/corex-notifications       inbox, доставка, дайджесты, предпочтения
axiomasoft/corex-operations          движок операций: definition, invocation, idempotency, versioning, validation, pagination,
                                     cost, bulk, HTTP-доставка, OpenAPI-проекция
axiomasoft/corex-tools               runtime инструментов: GuardedToolInvoker, схемы, contract-hash, MCP-проекция
axiomasoft/corex-webhooks            исходящие вебхуки: подписки, Standard Webhooks, SSRF-политика, retry/dead-letter
axiomasoft/corex-panels              headless-панели, каталог доступа, SavedViews (из ядра)
axiomasoft/corex-recurrence          раскрытие RFC 5545
axiomasoft/corex-search              граница поиска и жизненный цикл индекса
axiomasoft/corex-tasks               задачи, проекты, спринты, планирование, учёт времени
axiomasoft/corex-tenancy             физический DB-per-account: control plane, provisioning, identity, membership, impersonation
axiomasoft/corex-transfer            импорт/экспорт
axiomasoft/corex-wakeup              надёжные tenant-локальные wakeup requests
axiomasoft/corex-workflow            привязка workflow-движка
bridge:      axiomasoft/corex-search-intl · axiomasoft/corex-search-meilisearch · axiomasoft/corex-tasks-workflow ·
             axiomasoft/corex-access-azguard (P07, после стабильного AzGuard)
metapackage: axiomasoft/corex-full (lockstep, P09)
product:     products/flex-crm (flex/crm, вне релизного набора, D15)
```

Правила рёбер: `пакет → ядро (#[Api]|#[Spi])`; `bridge → оба соединяемых пакета (#[Api]|#[Spi])`;
`пакет ↛ Internal другого пакета`; `ядро ↛ любой пакет`; циклов нет (включая тестовые рёбра); `notifications ↛ wakeup`
(контракт в ядре, D16, P07). Целевой граф и все решения разрыва — `artifacts/P00/dependency-edges.tsv` (1756 рёбер: keep 1620,
remove 94, move 29, invert 13); исполнение resolutions — предусловие механического split в P02.

Почему `Modules` в ядре (D25): 12 из 19 живых пакетов требуют пару core+modules, modules требует только core — независимого
потребителя нет; система модулей — определяющая ответственность L0 (`dev-doctrine.md` §1; Bitrix D7 `main`).
Почему Money/SavedViews уходят из ядра (D27/D28): `brick/money` — тяжёлая зависимость единственного cast, владелец денег — intl;
SavedViews — UI-состояние единственного потребителя panels. Departments остаётся (D29): scope-иерархия Settings/Features ядра.

## 3. Раскладка пакета

```text
packages/<name>/
├── composer.json          # axiomasoft/corex-<name>; PSR-4 ровно одна запись: "CoreX\\<Name>\\": "src/"
├── README.md  CHANGELOG.md  LICENSE
├── config/corex-<name>.php
├── database/migrations/   # baseline v1 (D20); tenant/central — явное разделение
├── resources/contracts/   # php-api.json (D06), openapi.json (если есть HTTP), schemas/
├── src/
│   ├── <Name>ServiceProvider.php          # единственный публичный provider (D09)
│   ├── <Module>/{Contracts,Data,Enums,Events,Exceptions}/   # публичное (#[Api]/#[Spi])
│   ├── <Module>/Internal/…               # реализация, модели Eloquent, SQL, jobs, контроллеры
│   ├── Testing/…                         # тестовая поддержка для тестов других пакетов (D35)
│   └── Laravel/…                         # внутренние providers, команды, middleware (internal)
└── tests/{Unit,Feature,Arch}/ + phpunit.xml + phpstan.neon (свой vendor); Tests\TestCase — свой у каждого пакета
```

Ядро — та же раскладка с модулями из закрытого списка на месте `<Module>` (`packages/core/src/<Module>/`), root symbol
`CoreX\CoreServiceProvider`, dev-харнесс `CoreX\Testing\` (`tests/Support/`). Корень монорепо: `packages/*` (релизные),
`products/*` (не релизные потребители), `tests/` — только кросс-пакетные архитектурные гейты и consumer-сценарии, `docs/`
(+ `docs-public/`), `scripts/`, `plans/`.

## 4. Namespaces и классификация

- Ядро: `CoreX\` + закрытый список §2 (glossary §Kernel modules); пакет `axiomasoft/corex-<name>` владеет `CoreX\<Name>\`
  (PascalCase, без дефисов: `corex-search-meilisearch` → `CoreX\SearchMeilisearch\`). Сегменты пакетов зарезервированы, в ядре
  запрещены; `CoreX\Tenancy` — только пакет tenancy, контекст ядра — `CoreX\Context` (D26).
- Namespace = путь: префикс каталога = корень пакета + путь от `src/`; PSR-4 folding запрещён; переносы P02/P03 — строго по
  `namespace-map.tsv` (D34).
- Атрибуты `CoreX\Attributes\Api` (`since`), `CoreX\Attributes\Spi` (`kind: Implement|Call`, `since`) — та же семантика,
  что `Chatom\Attributes\{Api,Spi}`.
- Манифест `resources/contracts/php-api.json` на пакет строится сканером; тип вне `Internal` без атрибута — ошибка;
  `#[Api]`-сигнатура, ссылающаяся на internal/Eloquent/`Illuminate\Support\Collection` — ошибка; межпакетный import
  неклассифицированного символа — ошибка.
- `#[Spi(Implement)]`-интерфейс не расширяется методами в minor (только новым интерфейсом); `#[Api]`-интерфейсы приложение
  не реализует.

## 5. Application API

- Каждый пакет экспортирует интерфейсы модулей (`<Module>\Contracts\*`) с методами «один DTO → view/result», DTO `final readonly`
  с инвариантами в конструкторе и именованными фабриками; результаты — `*View`/`*Result`; идентификаторы — value objects.
- Контекст (tenant/actor) передаётся явно или через порт ядра `CoreX\Context`, никогда через static.
- Ошибки — иерархия исключений с кодом `ErrorCode` (`CoreX\Errors`, D10).

## 6. Extension SPI

- Механизмы расширения закрыты (EXT-001): compiled registries модулей (`collecting → compiled → frozen`, EXT-004),
  `#[Spi(Implement)]`-порты с дефолтами через `bindIf`, декорация через `extend`, опубликованные сообщения outbox.
- Секции манифеста, которые понимают только пакеты (api, tools, типы полей), компилируют сами пакеты через
  `CoreX\Modules\Contracts\ManifestSectionCompiler` и `FieldCastRegistry`; раздел отчёта конфигурации — через
  `CoreX\Config\Contracts\ConfigReportSection`; секции snapshot контракта operations — через
  `CoreX\Operations\OpenApi\Contracts\ContractSnapshotSection` (D31/D32).
- Точка расширения объявляет: id-тип, порядок (детерминированный), политику дубликата (ошибка), фазу жизни.
- Замена ядра/инвариантов — только `unsafe*`-API, `@internal`.

## 7. Конфигурация и композиция (D08, D09, D33)

- `config/corex-<name>.php` → `<Name>Options::fromArray()` (immutable, typed, `ViolationList::failIfAny()`) в `register()` →
  `instance(Options)`; runtime получает Options конструктором. Static `Config` удалён. Ядро — `config/corex.php`
  (раздел `modules` — бывший `corex-modules`).
- Идентификаторы: config `corex-<kebab>`, publish-tags `corex-<kebab>-{config,migrations[-<scope>]}`, env
  `COREX_<UPPER_SNAKE>_*`, artisan `corex:<kebab>:<verb>`; ядро — `corex`, `COREX_<MODULE>_*`, `corex:<module>:<verb>`.
- Один публичный provider → внутренние providers по обязанностям; без анонимных классов и логики в providers; фасады —
  только в `Laravel\` мосте; домен на портах (PSR-20 `ClockInterface` из `CoreX\Time`, порты сессии/аутентификации/событий
  определяет P05).
- `production` + опасная комбинация → исключение при boot; CI: `config:cache`, Octane-сценарий «два запроса, разные tenant».

## 8. Operations / Tools / Webhooks (D12, D31)

- Engine `corex-operations`: `Operation` definition (module, id, version, method, path, ability, handler, schemas,
  mode Query/Command/…, idempotency, scopes, fields) → compiled registry → invoker chain (authorize → validate →
  idempotency → handler → serialize). Строгость входа — инвариант; формат конверта — именованная политика
  `InputEnvelopePolicy` (P06), не отключение. Публичные утилиты для tools/webhooks: `CoreX\Operations\Validation\{JsonCanonical,
  SchemaBudget,SchemaProfile}`, `CoreX\Operations\Pagination\{OrderingRegistry,SignedCursorCodec}`, `ApiOptions`.
- Tools `corex-tools`: публичный `ToolInvoker` (`#[Api]`) с contract-hash guard, schema validation in/out, execution guard,
  MCP-проекция; компилирует секцию tools манифеста.
- Webhooks `corex-webhooks`: собственный `WebhooksServiceProvider`, config `corex-webhooks`, команды `corex:webhooks:deliver`
  и `corex:webhooks:prune`; SSRF-политика (HTTPS-only, DNS pinning `CURLOPT_RESOLVE`, peer-IP check, CIDR deny + tenant
  allowlist), Standard Webhooks подпись, retry budget + dead-letter как контракт, transient vs permanent failure в типе результата;
  секция topics snapshot вносится через `ContractSnapshotSection`.
- Engine не импортирует типы tools/webhooks; tools/webhooks зависят от engine (`dependency-edges.tsv`).

## 9. Tenancy и Access (D13, D14, D26)

- Контракты tenant-контекста — модуль ядра `CoreX\Context` (value objects `Context\Data\{AccountRef,WorkspaceRef,TenantContext}`,
  SPI lifecycle `Context\Contracts\*`); DB-per-account движок — `corex-tenancy` за адаптером; выбор движка — P07.
- Impersonation на портах (`GrantStore`, `AuditSink`, `SessionStore`, `Clock`) + capability-профили (`dev|standard|regulated`)
  как typed options; central + tenant audit mirror; исключение аудита из write-guard публикует `CoreX\Context\ImpersonationGuard`.
- `corex-access` — контракты авторизации без AzGuard; AzGuard — bridge `corex-access-azguard`.

## 10. Wire (D11)

- Опубликованные сообщения: CloudEvents 1.0 structured JSON (`specversion`, `id`, `source: corex://<app>/<account>`,
  `type: corex.<package>.<event>`, `time`, `datacontenttype`, `dataschema`, `subject`, `data`) + extension-атрибуты
  `corexaccount`, `corexactor`, `corextrace`. Breaking payload → `type` с суффиксом `.v2` + новый `dataschema`.
- Webhooks: Standard Webhooks v1, тело = envelope.
- REST: OpenAPI 3.1, ошибки RFC 9457 + `code`.

## 11. Планка качества (D17, D18)

PHPStan max + strict + bleedingEdge, пустой baseline; Rector; Pint; `final` по умолчанию; readonly DTO; именованные
аргументы; mutation MSI по зонам; Roave per package (блокирующий с rc); API-манифест; composer-dependency-analyser;
unused-public; ownership-гейт по glossary; `check-maps.py` до завершения P03; CI: SHA-pin, `permissions: {}`, zizmor,
Dependabot, `composer audit --locked`, SBOM.

## 12. Сверка с chatom (D22)

Источник chatom — `artifacts/P00-design/facts/chatom-principles.md` (commit `21a6cca`) и chatom PLAN2 D-решения.

| Принцип | chatom | corex | Статус |
|:--|:--|:--|:--|
| Ядро не знает расширений | Core не знает companions (§1.1) | Ядро не знает пакетов, SPI + реестры (§1.1, D32) | совпадает |
| Один владелец | FQCN/таблица/config/tag/команда/npm/wire (§1.2) | FQCN/таблица/config/tag/команда/route/wire/код ошибки (§1.2) | совпадает |
| Пакет самодостаточен | `composer test`/`pnpm test` без companions (§1.3) | `composer test` без остальных пакетов, тесты — через `Testing` (§1.3, D35) | совпадает |
| Публичное — объявленное | `#[Api]/#[Spi]`, wire отдельно (§1.4) | то же (D06) | совпадает |
| Инварианты не конфигурируются | §1.5 | §1.5 | совпадает |
| Compile at boot | §1.6 | §1.6 (D08) | совпадает |
| Схема детерминирована | schema steps + migrations journal (D25) | baseline-сквош, механика P08 (D20) | открыто: сверить при дизайне P08 |
| Стандарты | Standard Webhooks, CloudEvents-envelope, OpenAPI 3.1, JSON Schema, SemVer (§1.8) | то же + RFC 9457, PSR-20 (D11) | совпадает |
| Без алиасов до 1.0 | §1.9 | §1.9 (D01) | совпадает |
| Vendor | `axiomasoft/chatom*` (D17) | `axiomasoft/corex*` (D02) | совпадает |
| PHP / Laravel | ^8.4 / ^12\|^13 (D18) | ^8.4 / ^12\|^13 (D03) | совпадает |
| Namespace | `Chatom\` + закрытый список (D22) | `CoreX\` + закрытый список (D04, D29) | совпадает |
| Layout | feature modules + `Internal` (D21) | то же (D05) | совпадает |
| Атрибуты | `Chatom\Attributes\{Api,Spi}` | `CoreX\Attributes\{Api,Spi}`; общий пакет атрибутов не вводится до третьего потребителя | совпадает |
| Модели | views, модели internal (D24) | то же (D07) | совпадает |
| Ошибки | строковый `errorCode()` + категория, companion-префикс (P04.3) | строковые коды + registry + RFC 9457 (D10) | открыто: форма `ProblemDefinition` сверяется при P04 обоих планов |
| Wire | CloudEvents + Standard Webhooks + problem+json (D26) | то же (D11) | совпадает |
| Релиз | split + lockstep, `1.0.0-rc.1` (D28) | то же (D19) | совпадает |
| Связь продуктов | — | chatom может стать потребителем corex (tenancy/operations) | открыто: вне scope до rc обоих |
