# Политика публичной поверхности CoreX

**Версия:** 1.0.0 · **Статус:** нормативный · **Уровень:** 0 (`corex/*`)

Процедуры статей `API-001` … `API-008` конституции. Инварианты — в
[constitution.md](constitution.md); уровни зрелости `extra.corex.stability` — в
[taxonomy.md](taxonomy.md).

## Чем объявляется публичная поверхность

Публичная поверхность пакета объявляется тремя носителями. Ни один из них не является
рукописным перечислением всех публичных символов.

1. **`extra.corex.public-api`** — перечень публичных неймспейсов пакета (`taxonomy.md`).
   Это единственный носитель, который читает машина, решая «вправе ли пакет A импортировать
   символ пакета B». Перечень неймспейсов, в отличие от перечня символов, не гниёт при
   рефакторинге внутри неймспейса.
2. **`@internal` в исходниках** — объявление, что на символ не распространяется обещание
   совместимости. Потребитель, использующий такой символ, обязан ожидать его изменения
   или удаления в любом релизе.
3. **Release-to-release дифф публичного API** — ответ на настоящий вопрос релиза: «сломали
   ли мы потребителя». Вводится в P3, становится условием тега 1.0 в P17.

`@api` в исходниках допускается как пояснение для читателя, но носителем публичности не
является: носитель — перечень неймспейсов.

## Граница `@internal` и граница пакета — разные границы

Статический анализ PHP сравнивает `@internal` с **корнем неймспейса** символа: символ
считается использованным «снаружи», если корневой неймспейс вызывающего кода отличается от
корневого неймспейса объявления.

Все пакеты `corex/*` живут под одним корнем `CoreX\`. Значит, на межпакетной границе
CoreX такая проверка не даёт **ничего**: импорт `CoreX\Tenancy\...` из `CoreX\Tasks\...`
для неё — внутреннее использование.

Отсюда обязательное разделение:

| Вопрос | Носитель ответа |
|:--|:--|
| Обещаем ли мы совместимость этого символа внешнему потребителю пакета | `@internal` в исходнике |
| Вправе ли пакет A импортировать символ пакета B | `scripts/package-graph-gate.php` (правила R2 и R4) |

Правило, выраженное только через `@internal`, считается **не выраженным**: гейт, который
всегда зелёный, хуже отсутствующего гейта, потому что создаёт ложную уверенность.

Измеренное состояние разметки на 2026-09-19 (вход P3, не перемеряется в этом документе):
`@internal` встречается в **225 файлах** (250 вхождений) девяти пакетов — modules 74,
core 66, tenancy 50, auth 16, api 9, crm 4, tasks 3, panels 2, tasks-workflow 1;
`@api` — **0 вхождений**.

Enforcement межпакетной границы — **гейт графа пакетов** (`composer package-graph-gate`,
правила R2 и R4), а не статический анализ PHPDoc.

## Переход между уровнями зрелости

Значения и обещания поля `extra.corex.stability` — в [taxonomy.md](taxonomy.md) (таблица
`stability`). Здесь — только процедура перехода между уровнями.

| Переход | Что обязано произойти |
|:--|:--|
| `experimental` → `stable` | BC-дифф в CI зелёный; публичные неймспейсы не менялись в последнем минорном релизе; есть внешний потребитель, подтвердивший работоспособность |
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

## Совместимость `api/v1`

`operation-contract/1` заморожен для локального checkout решением владельца
P13.12 (G-API, `owner-session/local-source`). Единственный источник объявлений —
скомпилированный `ModuleRegistry`; `OperationRegistry`, `ApiProfile` и снимки
`api/v1` являются его проекциями. Хеш профиля зависит только от установленного
кода и настроек контракта, а активация для account/workspace вычисляется при
вызове. Дополнительный изменяемый реестр операций или tenant-поля в хеше
запрещены.

HTTP и exposed tool вызывают один `OperationInvoker`. Перед обработчиком он
проверяет активацию модуля, scope, actor и разрешённые поля записи; поля ответа
отсекаются тем же правилом при новом результате и replay. Идемпотентность
команды означает одну зафиксированную пару effect/outcome; replay повторно
проверяет текущую авторизацию. Cursor связан с версией, scope, порядком и
ключом подписи. Ошибки имеют стабильный descriptor и две HTTP-проекции:
envelope и RFC 9457 Problem Details; человеческий текст не является частью
снимка. Async command возвращает 202 и `Location`. GraphQL-путь, зависимость,
capability и снимок не входят в этот контракт.

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
errors1000, runtime30s, memory128MiB. Проверки cooperative; один read/write call не
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
[P14 qualification receipt](../../plans/2026.09.19-№1-COREX-PLATFORM-HARDENING/findings/P14-execution-validation.md).

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

Пакет-потребитель вправе импортировать из пакета-владельца только неймспейс, перечисленный
в `extra.corex.public-api` владельца. Диагностика различает два случая, и сообщения обязаны
быть разными:

- символ лежит в `Internal`-неймспейсе владельца — нарушение границы реализации;
- символ лежит вне `Internal`, но его неймспейс не перечислен в `public-api` — нарушение
  объявленной поверхности.

Слияние этих сообщений в одно вырождает диагностику: первое означает «так нельзя», второе —
«либо так нельзя, либо владелец забыл объявить неймспейс публичным».

Исключение оформляется в конфигурации гейта, а не в коде потребителя, и несёт причину.
