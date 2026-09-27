# Receipts — фаза P4 (durable boundary)

## P4.1 — Транзитная форма `DataScope`

**Дата:** 2026-09-21 · **Actor:** plan-exec/cursor-composer-2.5/medium

### Продукт

- `DataScope`: публичные скаляры `accountId`, `workspaceId`, `boxed`, `version`; `public const int VERSION = 2`; `fromTenantContext()` с тем же порядком параметров, что у конструктора; поле `TenantContext $context` сохранено (D21).
- Тесты §2.3 в `SupportDataTest.php`: вывод скаляров, parity `fromTenantContext`, `Invalid account identity.`; прежние негативы connection/actor/restoreEpoch без изменений.

### Точки миграции (не начата)

```text
$ rg -n 'new DataScope\(' packages | wc -l
33

$ rg -n 'fromTenantContext\(' packages -g '*.php' | rg -v 'DataScope\.php' | wc -l
1
```

(единственный вызов `fromTenantContext` вне определения — тест parity P4.1.)

Базовая метрика дизайна (2026-09-19): **21** (`research/P4-design.md` §0). Текущее дерево шире (новые тесты/фичи с 2026-09-19); **ни один файл вне `packages/core` не менялся**. В `SupportDataTest.php` +3 намеренных `new DataScope(` для §2.3 (было 2, стало 5); одна строка `rg` — docblock в `DataScope.php`, не код.

### Validation

```text
$ cd packages/core && composer test
{"tool":"pest","result":"passed","tests":30,"passed":30,"assertions":82}

$ cd packages/core && composer analyse
{"tool":"phpstan","result":"passed","errors":0}

$ cd packages/core && composer lint:check
{"tool":"pint","result":"passed"}

$ cd ../.. && composer lint:check
{"tool":"pint","result":"fail","files":[{"path":"tests/Pest.php"}]}
(вне Files P4.1 — тот же долг, что P3.6/P3.7)
```

---

## P4.2 — `TenantContextRestorer` и сверяющая реализация

**Дата:** 2026-09-21 · **Actor:** plan-exec/cursor-composer-2.5/high

### Продукт

- Контракт `CoreX\Tenancy\Contracts\TenantContextRestorer` (`restore(DataScope): TenantContext`).
- `GuardedTenantContextRestorer`: сверяет `accountId`, `workspaceId`, `boxed` с `TenantContextResolver::current()`; при совпадении возвращает тот же объект контекста (D22).
- `ScopeContextMismatch` с фабриками `account` / `workspace` / `profile` и тремя различимыми сообщениями.
- `CoreServiceProvider`: `scoped`-привязка `TenantContextRestorer` → `GuardedTenantContextRestorer`.
- `TenantContextRestorerTest`: 1 позитив + 3 негатива с проверкой текста.

### Что не проверяется (D22)

`connectionIdentity` и фактическое имя БД **не** сверяются в `restore()`: `ScopeConnectionGuard` живёт в `packages/attributes` и `packages/tasks-workflow` (две копии); втягивание в ядро — P6, полная сверка соединения при инициализации — P9. Долг зафиксирован здесь, а не подразумевается закрытым.

### Validation

```text
$ cd packages/core && composer test
{"tool":"pest","result":"passed","tests":34,"passed":34,"assertions":89}

$ cd packages/core && composer analyse
{"tool":"phpstan","result":"passed","errors":0}

$ cd packages/core && composer lint:check
{"tool":"pint","result":"passed"}

$ rg -n 'function current\(' packages/core/src/Tenancy/Contracts/TenantContextResolver.php
15:    public function current(): TenantContext;
```

---

## P4.3 — Миграция `attributes`: `RegistryFieldCatalogue` на restorer

**Дата:** 2026-09-21 · **Actor:** plan-exec/cursor-composer-2.5/medium

### Продукт

- `RegistryFieldCatalogue`: `TenantContextRestorer` перед `drivers`; один `restore($scope)` в начале `snapshot()`, обе бывшие точки `$scope->context` → `$context`.
- `AttributesServiceProvider`: `contexts: $app->make(TenantContextRestorer::class)`; ранний guard по `EntityRegistry`/`ModuleRegistry` вместо `instanceof UnavailableAttributes` (PHPStan, поведение то же).
- Тесты: `DataScope::fromTenantContext(` в пяти файлах; `fixedTenantContextRestorer()` в `tests/helpers.php` для unit-каталога.
- **CHANGELOG:** не требуется — публичная поверхность `corex/attributes` не менялась (`RegistryFieldCatalogue` — `Internal`).

### Поведение снимка

Хеш/сортировка/набор полей не менялись: логика после восстановления контекста идентична прежним вызовам `$scope->context` при совпадающем restorer.

### Bookkeeping (вне Files item, для зелёного `package-graph-gate`)

- `scripts/package-graph-gate.json`: R4 exception `AttributesServiceProvider` → `TenantContextRestorer` (ADR P4.2).
- `scripts/package-graph-undeclared-surface.json`: `TenantContextRestorer`, `GuardedTenantContextRestorer`; carryover R4b для PHPStan `DurablePayload*` (ветка P3.5).

### Validation

```text
$ cd packages/attributes && composer test
{"tool":"pest","result":"passed","tests":16,"passed":16,"assertions":40}

$ cd packages/attributes && composer test:pg
{"tool":"pest","result":"passed","tests":13,"passed":13,"assertions":36}

$ cd packages/attributes && composer analyse
{"tool":"phpstan","result":"passed","errors":0}

$ cd packages/attributes && composer lint:check
{"tool":"pint","result":"passed"}

$ rg -n '\$scope->context' packages/attributes/src
(пусто)

$ rg -n 'new DataScope\(' packages/attributes
(пусто)

$ composer package-graph-gate
exit 0
```

---

## P4.4 — Миграция `tasks-workflow`: материализатор и wakeup-handler

**Дата:** 2026-09-21 · **Actor:** plan-exec/cursor-composer-2.5/medium

### Продукт

- `SqlTaskRecurrenceMaterializer`: `TenantContextRestorer` последним параметром; один `restore($scope)` в начале `materialize()`, контекст вниз в `materializeOccurrence` / `scheduleWakeup`; бывшие `$scope->context` → `$context`.
- `MaterializationWakeupHandler`: только `DataScope::fromTenantContext(` вместо `new DataScope(`; блок `try`/`finally`, подмена `DataScopeAuthorizer` и оба `forgetScopedInstances()` без изменений.
- `TasksWorkflowServiceProvider`: `contexts: $app->make(TenantContextRestorer::class)`.
- Тесты: `DataScope::fromTenantContext(` в `MaterializationTest.php` и `MaterializationPgTest.php`.
- **CHANGELOG:** не требуется — публичная поверхность `corex/tasks-workflow` не менялась.

### Bookkeeping (вне Files item)

- `scripts/package-graph-gate.json`: R4 exceptions `TasksWorkflowServiceProvider` + `SqlTaskRecurrenceMaterializer` → `TenantContextRestorer` (ADR P4.2).
- `tests/Pest.php`: `group('pg')` на уровне каталога `Pg` (снят дублирующий `uses(PgTestCase)` в `MaterializationPgTest.php`, иначе `composer test` 255).

### Validation

```text
$ cd packages/tasks-workflow && composer test
Tests: 12 passed (57 assertions)

$ cd packages/tasks-workflow && composer analyse
{"tool":"phpstan","result":"passed","errors":0}

$ cd packages/tasks-workflow && composer lint:check
{"tool":"pint","result":"passed"}

$ rg -n '\$scope->context' packages/tasks-workflow/src
(пусто)

$ rg -n 'new DataScope\(' packages/tasks-workflow
(пусто)

$ rg -n 'new DataScope\(' packages/*/src
packages/core/src/Support/Data/DataScope.php:59 (комментарий в docblock, не вызов)

$ composer package-graph-gate
exit 0
```

---

## P4.5 — Миграция конструирования в тестах `search`, `search-intl`, `search-meilisearch`

**Дата:** 2026-09-21 · **Actor:** plan-exec/cursor-composer-2.5/medium

### Продукт

- Пять тестовых файлов: `new DataScope(` → `DataScope::fromTenantContext(` без перестановки аргументов (8 вхождений в `PostgresSearchProviderTest.php`, по одному в остальных четырёх).
- `packages/search/src`, `packages/search-intl/src`, `packages/search-meilisearch/src` не менялись.
- **CHANGELOG:** не требуется — публичная поверхность трёх пакетов не менялась.

### Validation

```text
$ rg -n 'new DataScope\(' packages/search packages/search-intl packages/search-meilisearch
(пусто)

$ git diff --stat -- 'packages/search/src' 'packages/search-intl/src' 'packages/search-meilisearch/src'
(пусто)

$ cd packages/search && composer test
{"tool":"pest","result":"passed","tests":10,"passed":10,"assertions":21}

$ cd packages/search && composer test:pg
{"tool":"pest","result":"passed","tests":6,"passed":6,"assertions":22}

$ cd packages/search-intl && composer test
{"tool":"pest","result":"passed","tests":6,"passed":6,"assertions":14}

$ cd packages/search-meilisearch && composer test
{"tool":"pest","result":"passed","tests":3,"passed":3,"assertions":3}

$ cd packages/search && composer analyse
{"tool":"phpstan","result":"passed","errors":0}
$ cd packages/search-intl && composer analyse
{"tool":"phpstan","result":"passed","errors":0}
$ cd packages/search-meilisearch && composer analyse
{"tool":"phpstan","result":"passed","errors":0}

$ cd packages/search && composer lint:check
{"tool":"pint","result":"passed"}
$ cd packages/search-intl && composer lint:check
{"tool":"pint","result":"passed"}
$ cd packages/search-meilisearch && composer lint:check
{"tool":"pint","result":"passed"}

$ composer analyse
{"tool":"phpstan","result":"passed","errors":0}

$ composer lint:check
{"tool":"pint","result":"passed"}
```

### Bookkeeping (вне Files item)

- `vendor/bin/pint tests/Pest.php` — снят carryover fail root `lint:check` (import order), без смены семантики Pg group из P4.4.

---

## P4.6 — Миграция конструирования в тестах `intl` и `transfer`

**Дата:** 2026-09-21 · **Actor:** plan-exec/cursor-composer-2.5/medium

### Продукт

- Семь тестовых файлов: `new DataScope(` → `DataScope::fromTenantContext(` без перестановки аргументов (по одному вхождению в каждом файле).
- `packages/intl/src`, `packages/transfer/src` не менялись.
- **CHANGELOG:** не требуется — публичная поверхность двух пакетов не менялась.

### Готовность к флипу (вход P4.7)

```text
$ rg -n 'new DataScope\(' packages/intl packages/transfer
(пусто)

$ rg -n '\$scope->context' packages/*/src
(пусто)

$ rg -n 'new DataScope\(' packages --glob '!packages/core/**'
(пусто)

$ rg -n 'new DataScope\(' packages
packages/core/tests/Unit/SupportDataTest.php:16
packages/core/tests/Unit/SupportDataTest.php:75
packages/core/tests/Unit/SupportDataTest.php:101
packages/core/tests/Unit/SupportDataTest.php:124
packages/core/tests/Unit/SupportDataTest.php:153
packages/core/tests/Unit/TenantContextRestorerTest.php:25
packages/core/src/Support/Data/DataScope.php:59
```

Остаток только в `packages/core` (тесты транзитной формы + docblock §3.1); вне ядра прямого конструирования нет — радиус P4.7 (D21).

### Validation

```text
$ cd packages/intl && composer test
{"tool":"pest","result":"passed","tests":33,"passed":33,"assertions":96}

$ cd packages/intl && composer test:pg
{"tool":"pest","result":"passed","tests":2,"passed":2,"assertions":10}
(повтор после transient deadlock DROP SCHEMA)

$ cd packages/transfer && composer test
{"tool":"pest","result":"passed","tests":7,"passed":7,"assertions":19}

$ cd packages/transfer && composer test:pg
{"tool":"pest","result":"passed","tests":10,"passed":10,"assertions":30}

$ cd packages/intl && composer analyse && composer lint:check
{"tool":"phpstan","result":"passed","errors":0}
{"tool":"pint","result":"passed"}

$ cd packages/transfer && composer analyse && composer lint:check
{"tool":"phpstan","result":"passed","errors":0}
{"tool":"pint","result":"passed"}

$ composer analyse
{"tool":"phpstan","result":"passed","errors":0}

$ composer lint:check
{"tool":"pint","result":"passed"}

$ git diff --stat -- 'packages/intl/src' 'packages/transfer/src'
(пусто)
```

---

## P4.7 — Флип `DataScope`, маркер `DurablePayload`, правило на `array<mixed>`

**Дата:** 2026-09-21 · **Actor:** plan-exec/cursor-composer-2.5/high

### Продукт

- `DataScope`: скалярный promoted-конструктор (`accountId`, `workspaceId`, `connectionIdentity`, `actorId`, `restoreEpoch`, `boxed`, `version`), `implements DurablePayload`, удалены `TenantContext $context` и транзитная форма; `fromTenantContext()` строит скаляры; отказ на `version !== VERSION`.
- `DurablePayloadRule`: идентификатор `corex.durablePayload.untypedArrayProperty` через `Type::getArrays()` + `MixedType` (отдельные сообщения для missing vs explicit mixed); promoted-обход без изменения текстов трёх legacy-исключений deny-list.
- Фикстура PHPStan: четыре declared + три promoted array-случая; `ValidTypedArrayPayload` / `PromotedValidArrayPayload` без ошибок.
- Тесты `SupportDataTest` / `TenantContextRestorerTest` на `fromTenantContext` и скалярный конструктор; негатив на unsupported version.
- **CHANGELOG:** `### Changed` — **BREAKING** строка P4.7.

### Уведомление потребителей `shared-data-foundation/1` (текст для владельца)

> **Breaking:** `CoreX\Support\Data\DataScope` больше не принимает `TenantContext` в конструкторе и не имеет свойства `$context`. Используйте `DataScope::fromTenantContext($context, $connectionIdentity, $actorId, $restoreEpoch)` на границе с живым контекстом или создавайте VO напрямую из скаляров. Тип помечен `CoreX\Contracts\DurablePayload`; неизвестная `version` в конструкторе отклоняется. См. CHANGELOG [Unreleased] и D6/D21. Codec для wire-формы — отдельно (P4.8).

### Полнота (перепрогон §P4.6 перед мутацией)

```text
$ rg -n 'new DataScope\(' packages/intl packages/transfer
(пусто)

$ rg -n '\$scope->context' packages/*/src
(пусто)
```

### Validation

```text
$ cd packages/core && composer test
{"tool":"pest","result":"passed","tests":35,"passed":35,"assertions":91}

$ ./vendor/bin/pest tests/PHPStan/DurablePayloadRuleTest.php
{"tool":"pest","result":"passed","tests":3,"passed":3,"assertions":3}

$ vendor/bin/phpstan analyse --memory-limit=1G
{"tool":"phpstan","result":"passed","errors":0}

$ composer lint:check && composer package-graph-gate && bash scripts/anti-weakening-gate.sh
{"tool":"pint","result":"passed"}
package-graph-gate: OK
ANTI-WEAKENING GATE: OK

$ cd packages/attributes && composer test
{"tool":"pest","result":"passed","tests":16,"passed":16}

$ cd packages/intl && composer test
{"tool":"pest","result":"passed","tests":33,"passed":33}

$ cd packages/search && composer test
{"tool":"pest","result":"passed","tests":10,"passed":10}

$ cd packages/search-intl && composer test
{"tool":"pest","result":"passed","tests":6,"passed":6}

$ cd packages/search-meilisearch && composer test
{"tool":"pest","result":"passed","tests":3,"passed":3}

$ cd packages/tasks-workflow && composer test
Tests: 12 passed

$ cd packages/transfer && composer test
{"tool":"pest","result":"passed","tests":7,"passed":7}
```

---

## P4.8 — `DataScopeCodec`: версия формата, отказ на неизвестной и на неполной

**Дата:** 2026-09-21 · **Actor:** plan-exec/cursor-composer-2.5/high

### Продукт

- `CoreX\Contracts\DataScopeCodec` — encode/decode между `DataScope` и `array<string, scalar|null>`.
- `ArrayDataScopeCodec` — фиксированный порядок ключей `version, account, workspace, connection, actor, restoreEpoch, boxed`; отказ на `version !== 2` и на отсутствующий/неверно типизированный ключ; `optionalString()` различает отсутствующий ключ и явный `null`.
- `InvalidScopePayload` — фабрики `unsupportedVersion()` и `missing()`.
- `CoreServiceProvider`: `singleton(DataScopeCodec::class, ArrayDataScopeCodec::class)`.
- `DataScopeCodecTest`: round-trip (с workspace и без) + шесть негативных/граничных кейсов из спецификации.
- **CHANGELOG:** строка P4.8 в `### Added`.

### Апкастер не пишется — почему

Версия 1 (форма с объектом контекста) никогда не сериализовалась в durable-хранилище: до P4.8 codec'а не существовало, а единственная durable-форма скоупа сегодня — `workspace_scope_key` в `sys_wakeup_requests`, строящаяся из контекста, а не из `DataScope`. Писать преобразование формата без строк в БД — код без входных данных; `decode()` отказывает на неизвестной версии, первая миграция версии появится при появлении второй (P5/P13). См. `research/P4-design.md` §6.3.

### JCS не заявляется

Codec работает с PHP-массивом скаляров; детерминированный порядок ключей достаточен для отпечатков idempotency внутри одного рантайма, но **RFC 8785 / JCS не заявляется** ни в докблоке, ни в CHANGELOG (§5.3 P4-design). Сериализация массива в JSON-строку — ответственность будущих потребителей (P5/P13).

### Validation

```text
$ cd packages/core && composer test
{"tool":"pest","result":"passed","tests":41,"passed":41,"assertions":106}

$ cd packages/core && composer analyse
{"tool":"phpstan","result":"passed","errors":0}

$ cd packages/core && composer lint:check
{"tool":"pint","result":"passed"}
```

---

## P4.9 — PSR-20: `psr/clock`, три реализации, привязка `LaravelClock`

**Дата:** 2026-09-21 · **Actor:** plan-exec/cursor-composer-2.5/high

### Продукт

- `packages/core/composer.json`: `require` `psr/clock` `^1.0`, `provide` `psr/clock-implementation` `1.0`.
- `CoreX\Support\Time\SystemClock`, `FrozenClock`, `LaravelClock` — по `research/P4-design.md` §7.2 (D20).
- `CoreServiceProvider`: `singleton(Psr\Clock\ClockInterface::class)` → `LaravelClock` + `DateFactory`.
- `ClockTest`: UTC у `SystemClock`, `advance()` у `FrozenClock`, `travelTo` у контейнерного `ClockInterface`.
- **CHANGELOG:** строка P4.9 в `### Added`.

### `composer why psr/clock` (до правки `composer.json`)

```text
$ composer why psr/clock
nesbot/carbon 3.14.0 requires psr/clock (^1.0)
symfony/clock v8.1.0 requires psr/clock (^1.0)
```

### `composer update --dry-run` (после правки, корень монорепо)

```text
$ composer update --dry-run psr/clock
Lock file operations: 0 installs, 1 update, 0 removals
  - Upgrading corex/core (dev-main … => dev-main …)
Package operations: 0 installs, 1 update, 0 removals
```

Новых устанавливаемых пакетов нет; единственное изменение lock — path-пакет `corex/core`.

### Validation

```text
$ cd packages/core && composer validate --strict
./composer.json is valid

$ cd packages/core && composer test
{"tool":"pest","result":"passed","tests":44,"passed":44,"assertions":111}

$ cd packages/core && composer analyse
{"tool":"phpstan","result":"passed","errors":0}

$ cd packages/core && composer lint:check
{"tool":"pint","result":"passed"}
```

---

## P4.10 — Часы в durable-путях `wakeup`: lease, backoff, наступление срока

**Дата:** 2026-09-21 · **Actor:** plan-exec/cursor-composer-2.5/high

### Продукт

- `SweepRunner`, `SqlWakeupScheduler`: конструктор `Psr\Clock\ClockInterface`; все точки §7.4 переведены с глобального `now()`; один `$now` на транзакцию/`run`/`catch`.
- `WakeupServiceProvider`: именованные аргументы `clock: $app->make(ClockInterface::class)`.
- `packages/wakeup/composer.json`: `require` `psr/clock` `^1.0`.
- `WakeupSchedulerTest`: backoff `2s/4s/8s/16s` и граница lease (`FrozenClock`); `TestCase` — дефолт `LaravelClock`.
- **CHANGELOG:** строка P4.10 в `### Added`.

### Долг D23 (серверное время PostgreSQL)

P4 **не** переводит предикаты lease, backoff и `fire_at` на `now()` / `clock_timestamp()` в SQL. Решения по-прежнему в часах приложения (D20); при расхождении NTP между воркерами возможен ранний перехват lease — это **не** закрыто в P4.

**Условие возврата:** первый мультихостовый деплой воркеров **или** первый зафиксированный ранний перехват lease; тогда перевод на серверное время выполняют **сразу** для `wakeup` и `messaging` (D23, D30).

### Bookkeeping (вне Files item, для зелёного `package-graph-gate`)

- `scripts/package-graph-undeclared-surface.json`: R4b carryover `ArrayDataScopeCodec`, `FrozenClock`, `LaravelClock`, `SystemClock` (продукт P4.8/P4.9).

### Validation

```text
$ rg '(^|[^>])now\(' packages/wakeup/src
(пусто — глобальный хелпер now() отсутствует)

$ cd packages/wakeup && composer test
{"tool":"pest","result":"passed","tests":8,"passed":8,"assertions":34}

$ cd packages/wakeup && composer test:pg
{"tool":"pest","result":"passed","tests":1,"passed":1,"assertions":1}

$ cd packages/wakeup && composer analyse
{"tool":"phpstan","result":"passed","errors":0}

$ cd packages/wakeup && composer lint:check
{"tool":"pint","result":"passed"}

$ cd packages/tasks-workflow && composer test
(passed)

$ cd packages/tenancy && composer test
(passed)

$ composer lint:check && composer package-graph-gate
{"tool":"pint","result":"passed"}
package-graph-gate: OK (exceptions only)
```

---

## P4.11 — Доказательство изоляции контекста между единицами работы

**Дата:** 2026-09-21 · **Actor:** plan-exec/cursor-composer-2.5/medium

### Тесты (§8.2)

- `packages/core/tests/Feature/WorkUnitIsolationTest.php`: два прохода в одном приложении;
  pass A бросает исключение; между проходами только `forgetScopedInstances()`; pass B
  инспектирует **до** warmup второго прохода — `assertNotSame` на `TenantContextRestorer`,
  `restore($scopeA)` → `ScopeContextMismatch`, авторизатор после A равен исходному,
  конфиг без следов pass A (baseline `database.connections.testbench` + отсутствие
  `corex.work_unit_isolation.leak_marker`).
- `packages/tasks-workflow/tests/Pg/WorkUnitIsolationPgTest.php`: две материализации с разными
  `accountId`/`restoreEpoch`, pass A падает после успешной материализации; pass B не видит
  scoped-состояние A и материализует вторую серию (2 задачи в БД).

### Измерение `static $` (не assert)

```text
$ rg -n -- '^\s*(private|protected|public)?\s*static \$' packages/*/src | wc -l
0
```

### Ручная проверка config-утечки (не в CI)

При установке `corex.work_unit_isolation.leak_marker` в try pass A без очистки в `finally`
маркер **остаётся** после `forgetScopedInstances()` — ожидаемо для Laravel; граница джоба
сама по себе конфиг не сбрасывает. Продуктовый тест сознательно не мутирует конфиг в pass A
(как `MaterializationWakeupHandler`); адресат усиления доказательства / runtime-сброса — P6.

### Validation

```text
$ git diff --stat -- 'packages/*/src'
(пусто)

$ cd packages/core && composer test
{"tool":"pest","result":"passed","tests":45,"passed":45,"assertions":120}

$ cd packages/core && composer analyse && composer lint:check
{"tool":"phpstan","result":"passed","errors":0}
{"tool":"pint","result":"passed"}

$ composer test:pg --filter=WorkUnitIsolation
{"tool":"pest","result":"passed","tests":1,"passed":1,"assertions":11}

$ composer test:pg
{"tool":"pest","result":"passed","tests":143,"passed":143,"assertions":486}

$ composer analyse && composer lint:check
{"tool":"phpstan","result":"passed","errors":0}
{"tool":"pint","result":"passed"}
```

---

## P4.12 — Аудит `singleton`/`scoped` и гейт контекст-зависимых конструкторов

**Дата:** 2026-09-21 · **Actor:** plan-exec/cursor-composer-2.5/high

### Измерение

- Артефакт: `findings/P4-binding-audit.md` — 115 строк биндингов из 13 пакетов с
  `*ServiceProvider.php` в `src`; rollup по 20 живым пакетам + tombstone `import-export`.
- Статически разбираемые `->singleton(` с литералом `::class`: **19** (остальные 48 singleton
  — closure / `config()` / `Config::…Class()` → **не разбирается статически**, названный предел гейта).
- Захват контекста в конструкторе (три запрещённых типа): **2** (`ServiceProviderModuleRegistrar`,
  `PubSubManager` в `core`) → allowlist с `owner_phase` **P6**.

### Негативная посадка (обратима в тесте)

```text
packages/core/src/Internal/SingletonInjectionProbeServiceProvider.php: CoreX\Internal\SingletonInjectionProbe — app
```

(Воспроизведение: `vendor/bin/pest --filter 'singleton injection gate names probe'`.)

### Validation

```text
$ vendor/bin/pest --filter SingletonInjectionTest
{"tool":"pest","result":"passed","tests":2,"passed":2,"assertions":4}

$ composer analyse && composer lint:check
{"tool":"phpstan","result":"passed","errors":0}
{"tool":"pint","result":"passed"}

$ bash scripts/anti-weakening-gate.sh
ANTI-WEAKENING GATE: OK

$ git diff --stat -- 'packages/*/src'
(пусто — продуктовый src не менялся)
```

---
