# Политика публичной поверхности CoreX

**Версия:** 1.0.0 · **Статус:** нормативный · **Уровень:** 0 (`corex/*`)

Процедуры статей `API-001` … `API-008` конституции. Инварианты — в
[constitution.md](constitution.md); уровни зрелости `extra.corex.stability` — в
[taxonomy.md](taxonomy.md).

## Чем объявляется публичная поверхность

Единственный source owner публичности — атрибут на объявлении class/interface/trait/enum:
`#[Api(since: '1.0')]` или `#[Spi(kind: SpiKind::Implement|Call, since: '1.0')]`.
Метка не наследуется; разрешена ровно одна. `since` обозначает введённую версию,
а не доказательство опубликованного релиза. `@api`, имя каталога и generated JSON
не разрешают импорт. Атрибуты принадлежат `Packman\Attributes`. Каждый тип в `Internal`
явно помечается `#[Internal]`; одновременно `Api`/`Spi` запрещены. Метка `Internal`
также обозначает private implementation вне одноимённого каталога.

| Слой | Поддерживаемый способ использования |
|:--|:--|
| Api | Вызов, чтение, catch; внешняя реализация/наследование запрещены |
| Spi Implement | Реализация/наследование документированного контракта, включая protected hooks |
| Spi Call | Вызов, подписка и документированная интеграция |
| Internal | Только реализация пакета-владельца; обещания BC нет |

`composer php-api` запускает `scripts/php-api.php --strict --check`: исходная классификация,
замыкание native/PHPDoc типов, immutable DTO, imports и свежесть снимков проверяются вместе.
Модели и Collection не входят в Api payload. Типы `Internal` не входят ни в одну
публичную или SPI-сигнатуру, включая типы собственного пакета. Model extension SPI допускается
только в документированной Laravel-поверхности. Product проверяется как потребитель.

`packages/<name>/resources/contracts/php-api.json` и [public-api](../reference/public-api.md)
генерируются из одной исходной классификации: Packman владеет JSON serialization,
CoreX — полным графом типов и Markdown-справочником. Native JSON хранит `layer: spi`
и отдельный `spi_kind`; внешние inherited members не копируются. CoreX продолжает
проверять унаследованные контракты в source graph. Граф пакетов читает тот же source owner index: R4 проверяет
конкретный символ, R4b — классификацию и свежесть снимков. R1/R2/R3/R5 остаются независимыми.
`@internal` не обеспечивает межпакетную границу при общем корне `CoreX`; её обеспечивает граф.

[SemVer policy](semver-policy.md) определяет правила слоёв и BC selection отдельно для
base/candidate revision. Namespace metadata удалена из manifests; произвольный JSON
не может сделать неизвестный или Internal символ публичным.

## Переход между уровнями зрелости

Значения и обещания поля `extra.corex.stability` — в [taxonomy.md](taxonomy.md) (таблица
`stability`). Здесь — только процедура перехода между уровнями.

| Переход | Что обязано произойти |
|:--|:--|
| `experimental` → `stable` | BC-дифф в CI зелёный; поддерживаемые символы не менялись в последнем минорном релизе; есть внешний потребитель, подтвердивший работоспособность |
| `stable` → `deprecated` | Названа замена и мажорная версия удаления; запись в `CHANGELOG.md` пакета |
| любой → `internal` | Пакет исключён из перечня поставки; потребители уведомлены |
| `deprecated` → удаление | Только в мажоре, не раньше объявленной версии |

Обратный переход `stable` → `experimental` не выполняется: это и есть ломающее изменение,
о котором `stable` обещал предупредить мажором.

## Что считается ломающим изменением

Внутри поддерживаемой мажорной версии допускаются только аддитивные изменения. Новый
параметр обязан быть необязательным, а его значение по умолчанию обязано сохранять прежнее
поведение.

Запрещено (закрытый список):

1. Удаление публичного символа, метода, свойства или константы.
2. Удаление или переименование поля в публичном типе данных.
3. Добавление обязательного параметра и превращение необязательного в обязательный.
4. Сужение типа параметра и расширение типа возвращаемого значения.
5. Смена допустимости `null` в любую сторону на публичной сигнатуре.
6. Удаление случая enum, входящего в публичный тип.
7. Удаление или переименование стабильного кода ошибки, смена его category,
   семантики RFC 6901 `path`, `retryable`, либо удаление документированного detail key.
8. Смена контракта пагинации и порядка выдачи.
9. Удаление темы webhook либо смена её payload'а без новой версии.
10. Перенос публичного символа в другой неймспейс без сохранения прежнего.

Этот список — вход гейта P3 и P13, а не пожелание. Каждый пункт обязан быть проверяем
машинно либо назван как непроверяемый явно.

## Публичный контракт API v2

Скомпилированный Packman `ModuleRegistry` содержит объявления операций и
инструментов. `OperationRegistry`, `ApiProfile` и снимки `api/v2` проецируют эти
объявления. Хеш профиля зависит от установленного кода и настроек контракта.
Текущая активация для account/workspace вычисляется при вызове.

HTTP размещает операции под `/api/v2`. Хост подключает MCP endpoint с той же
проверкой principal и client. Exposed tool и HTTP вызывают один `OperationInvoker`.
Перед обработчиком он проверяет активацию, scope, права действия, записи и поля.
Тот же ключ и логический запрос возвращают исходный результат после проверки
текущих прав на действие, запись и выпуск полей. Replay восстанавливает расчёт
по исходному `BudgetReceipt` и не создаёт новый платёж. При смене смыслового
запроса с тем же ключом возвращается конфликт. Cursor связан с версией, scope,
порядком и ключом подписи.

OpenAPI публикует `Idempotency-Key` для разрешающих его операций и отмечает
обязательность по декларации. Exposed tool проверяет опубликованную схему
аргументов перед исполнением. При неизвестном результате MCP возвращает
`outcome: unknown` с исходным Problem code. Повтор с тем же ключом читает или
сверяет уже принятый результат.

Успешный JSON-ответ содержит `data` и `meta`. Ошибки используют стабильный
descriptor и формат RFC 9457 Problem Details. Async command возвращает 202, заголовок `Location` и
согласованный `data.status_url`. Ссылки на результат и ошибки строк доступны
после текущей проверки разрешений владельца. Условный запрос метаданных
проверяет текущую авторизацию до вычисления private ETag и ответа 304.
Провайдер политики метаданных поддерживает `FieldUseSnapshot`, чтобы все
решения по выдаваемым полям читались из одного свежего снимка владельца.
Search проверяет готовность индекса до снимка и сверяет поколение снова внутри
него. Если хост не может предоставить такой снимок, выдача метаданных закрыта.

Пять опубликованных файлов `api/v2` имеют имена `errors.json`, `manifest.json`,
`openapi.json`, `operations.json` и `webhooks.json`. Исторические байты `api/v1`
хранятся как исходная база сравнения из `1c11e0e35961409631cf68ebb28c7d2cd53ebb91`.

Зелёный контракт проверяют `tests/Feature/OperationContractTest.php`,
`tests/Arch/ApiContractTest.php`, `corex:operations:snapshot --check` и
`composer api-contract`. Изменение публичной поверхности требует нового решения
о совместимости; эта запись не разрешает релиз или автоматическое обновление
baseline.

`scripts/check-api-contract.php` сравнивает разобранные `operations.json` и
`errors.json` с committed baseline. OpenAPI остаётся HTTP-проекцией. Гейт не
обновляет baseline: запись снимка — отдельная команда генератора. Маркер
deprecation не разрешает удаление внутри major.

| Правило | Вердикт | Допустимое добавление |
|:--|:--|:--|
| `operation-removal` | удаление operation id либо смена method/path | новая operation |
| `response-field-removal` | удаление поля ответа | новое поле ответа |
| `required-input-addition` | новое обязательное входное поле | необязательное входное поле |
| `type-change` | смена `type` | — |
| `nullability-change` | смена `nullable` в любую сторону | — |
| `enum-removal` | удаление значения enum | новое значение enum |
| `error-contract-drift` | удаление code, смена category/path/retryable, удаление detail key | новый code, новый detail key |
| `pagination-reinterpretation` | смена envelope, cursor format или ordering id | — |

Неизвестный ключ снимка или схемы закрывается `unknown-schema` и JSON pointer.
P14 добавляет `webhooks.json` в тот же generator и manifest. Гейт также отвергает
удаление topic, required payload input addition, response-field removal,
type/nullability change, PII widening, signature/id semantics drift, major rebind
и смену default cost enforcement. Новые optional topics/fields допустимы.
Baseline не обновляется автоматически; механическая регенерация проходит сравнение
с прежним кандидатом и byte-check. Наличие пустого topic list не означает,
что приложение объявило или разрешило webhook topics.

## Нагрузочный контракт API

`operation-cost/1`, `bulk-operation/1` и `webhook-delivery/1` потребляют тот же
compiled module registry, P13 invoker, P5 сообщения и P4 scope/Clock. Cost recipe
объявляется только через `Api::cost()`. Legacy default: base=1, остальные веса=0,
max_units=10000. Формула — base + row×max(pageRows,exportRows) + field×selectedFields
+ relation×relationNodes + attribute_filter×attributeFilters + highlight×flag
+ side_effect×sideEffects. Structural caps и проверка overflow действуют до handler
и в observe mode; bulk требует явную recipe и bounded input.

При включённом cost control global всегда enforce; account/client/actor по умолчанию
observe. Capacity=10000 и refill=1000 units/sec — локальные defaults, не тариф или SLO.
Shipped `cost.enabled=false` требует явного setup и activation: этот флаг не означает
observe и не доказывает работающую защиту. После activation отсутствие shared SQL
authority запрещает handler. Все узлы используют один installation budget store;
tenant payload остаётся физически tenant-local. Две explicit connection identities
проверяются до SQL; scopes берутся из trusted context, keys — HMAC, metrics без ids.

Atomic reserve всех ancestors предшествует эффекту. Terminal settlement возвращает
units однократно; actual>estimate создаёт debt, отсутствие server counters означает
actual=estimate. Pre-handler reject возвращает estimate, crash/unknown/expiry не даёт
refund. Replay обслуживается за base=1. HTTP/tool metadata совпадают; 429 несёт
расчётный Retry-After, инфраструктурный отказ не выдаётся за исчерпание бюджета.

Transfer остаётся единственным import/export engine. Optional factory — installed
host DI, public transfer ports; API не пишет чужие таблицы и не исполняет class names
из payload. Literal bulk routes требуют уже admitted scope/client/definition и host
auth/field-policy adapter. Worker rechecks scope, authorization, restore epoch и
extension stamp на каждом bounded step; polling pending rows переживает потерю queue.
Cancellation ждёт не более одного bounded batch и сохраняет уже committed effects.
Import resumes committed checkpoint; unsealed export restart — новый полный capture.
Unknown outcomes требуют reconciliation; rollback не отменяет network effects.
Current result access проверяет scope/field policy/hash; foreign ids скрыты за 404.

Hard profile transfer: 1 GiB/source, 1 MiB/row, 256 fields, depth32, batch500,
errors1000, runtime30s, memory128MiB по умолчанию; host config `corex-import-export.limits.max_memory_mib` допускает 1..1024MiB для полного PHP процесса. Проверки cooperative; один read/write call не
preemptible. Host process limits, edge/body/request-rate/concurrency caps остаются
обязательной частью установки. Input parameters≤20KiB, failures page≤100,
result TTL24h≤metadata TTL30d; active jobs не prune. Retention не воскрешает cancelled
job и не превращает unknown в success. Ни billing, GraphQL, hard real-time cap,
универсального exactly-once, ни cross-account scheduler этот контракт не обещает.

Schema ownership, PII и retention — [table-ownership.md](table-ownership.md).
Миграции публикуются budget/tenant tags и применяются host явно; boot не исполняет DDL.
Remote webhook delivery выключена по умолчанию и требует key/worker/transport readiness.
Local grants и synthetic tests не разрешают production migration/rotation или live sends.
Setup, endpoint semantics и оставшиеся host boundaries —
[API README](../../packages/operations/README.md); текущие commands/hashes/negative fixtures —

## Контракт ошибки

Общий словарь — `ErrorCategory`, `UnavailabilityReason`, неизменяемый `ErrorDescriptor`
и интерфейс `DescribesError`. Код остаётся у пакета-владельца: центрального enum кодов нет.
Исключение реализует интерфейс на публичной границе; типизированный result может нести
тот же descriptor без исключения. Базовой иерархии `CoreXException` нет.

Стабильные поля — `code`, `category`, RFC 6901 `path`, `retryable` и документированные
ключи `details`. Сообщение, класс исключения и stack trace в контракт не входят. HTTP-статус
— проекция транспорта; его не хранит descriptor.

`path` равен `null` либо JSON Pointer: пустая строка — корень документа, непустой путь
начинается с `/`, экранирование только `~0` и `~1`. `retryable=true` значит лишь то, что
тот же semantic input может успеть позже. Это не разрешение на автоматический повтор:
вызывающий ещё проверяет идемпотентность и `Retry-After`.

| Reason | Смысл | Category | Default retryable |
|:--|:--|:--|:--|
| `capability_unavailable` | пакет, provider или backend отсутствует либо недоступен | `unavailable` | решает producer |
| `capability_disabled` | capability есть, но выключена конфигурацией или активацией | `unavailable` | `false` |
| `operation_unsupported` | выбранный driver не реализует операцию | `unsupported` | `false` |
| `access_denied` | actor или policy запрещает действие | `authorization` | `false` |
| `feature_not_entitled` | authoritative edition или license не даёт feature | `authorization` | `false` |

Пять reason не склеиваются. `feature_not_entitled` не эмитится без источника entitlement.
Это не шестой статус и не синоним отказа доступа.

Ломающее изменение контракта ошибки: удалить или переименовать code, сменить meaning,
category, семантику path или retryability, удалить документированный detail key.
Аддитивное: новый code, новый необязательный detail key, более точное человеческое сообщение.

| Situation | Form |
|:--|:--|
| Expected business denial | Typed result/decision with stable reason |
| Normal query absence | nullable/optional result |
| Programmer/configuration defect | typed exception |
| Infrastructure failure | typed exception with safe descriptor |
| Optimistic/concurrent conflict | domain conflict exception or explicit typed conflict |
| Batch partial failure | report with per-record descriptors; do not throw away successes |

## Правило межпакетного импорта

Пакет-потребитель вправе импортировать конкретный Api/Spi символ пакета-владельца
при объявленной зависимости и допустимом направлении типов пакетов. Foreign Internal
получает R2; неизвестный либо неразмеченный символ — R4; неклассифицированное объявление — R4b.
Снимок не является разрешением: stale manifest получает `API-STALE`, source ошибки остаются RED.
Именованные composition edges конечны, проверяются отдельно и не расширяют публичность.
