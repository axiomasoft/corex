# Политика схемы

Схемой таблицы владеет её пакет: [каталог владения](table-ownership.md) и статьи
`DATA-001` … `DATA-007` [конституции](constitution.md). Пакет доставляет исходные файлы
миграций в составе Composer-пакета и исполняет их через наборы схемы.

## Наборы и регистрация

`Packman\Schema\Data\Migration\SchemaSet` описывает единицу исполнения:

| Поле | Правило |
|:--|:--|
| `id()` | `<composer-name>:<set>`, уникально в реестре; `<set>` — имя каталога |
| `package` | Composer-имя владельца |
| `scope` | `SchemaScope::Central`, `Tenant` или `Dedicated` |
| `lifecycle` | `SchemaLifecycle::Platform` либо `Module` для пакета с `extra.packman.module` |
| `path` | Абсолютный путь к `database/migrations/<set>` внутри пакета |
| `version` | Целое поколение ≥ 1; baseline — 1 |
| `drivers` | Содержит `pgsql`; содержит `sqlite`, если набор поддерживает SQLite |
| `requires` | Composer-имена пакетов, чьи наборы должны быть зарегистрированы; иначе набор неактивен |
| `connection` | Имя соединения из Options владельца; обязательно только для `Dedicated` |

Пакет реализует `Packman\Schema\Contracts\Migration\SchemaSetProvider` во внутреннем классе и в
`register()` своего service provider тегирует этот класс именем контракта:

```php
$this->app->tag([PackageSchemaSets::class], SchemaSetProvider::class);
```

Singleton `Packman\Schema\Contracts\Migration\SchemaSets` собирает теги при первом разрешении,
сортирует наборы по id и сохраняет неизменяемый реестр. Некорректный путь, повтор id,
повтор basename миграции в одной scope, отсутствие `pgsql` или неверное сочетание scope
и connection приводят к исключению. Статический гейт дополнительно требует уникальность
basename во всём репозитории.

Пакеты не публикуют миграции и не вызывают `loadMigrationsFrom`. Обычный
`php artisan migrate` обслуживает миграции приложения; для таблиц пакетов нужны команды ниже.

## Scope, lifecycle и журнал

| Каталог набора | Scope | База исполнения | `tenant_scope` каталога владения |
|:--|:--|:--|:--|
| `root` | `Central` | Центральная база tenancy | `root` |
| `tenant` | `Tenant` | База аккаунта; в boxed — default connection | `tenant` |
| `tenant-cloud` | `Tenant` | База аккаунта, требует `axiomasoft/corex-tenancy` | `tenant` |
| `budget` | `Dedicated` | Соединение владельца набора | `shared-budget` |

`SchemaMigrator::migrate()` исполняет активные Platform-наборы выбранной scope;
`includeModules: true` добавляет Module-наборы. Module-наборы также создаются через
`ModuleLifecycle::enable()`, который получает пути из реестра. `migrateSets()` исполняет
явно выбранные id, в том числе в тестах через трейт `MigratesSchemaSets`.
На PostgreSQL оба пути Module-наборов используют тот же per-migration schema diff,
что lifecycle: journal, DDL и `mod_records` claims сохраняются одной транзакцией
под lifecycle graph lock. Если явная Module-выборка устанавливается в новую базу,
мигратор добавляет prerequisite `axiomasoft/packman-modules:tenant` для registry/claims.
В template clone должны переноситься и Laravel journal, и `mod_records`; последующий
`enable` использует уже сохранённые claims. Владение старых таблиц без claims нельзя
восстановить по имени или префиксу. SQLite-наборы сохраняют самостоятельный путь
установки; PostgreSQL lifecycle/purge на этом драйвере не поддерживается.

Для Central и Tenant можно указать соединение; по умолчанию используется default connection.
Dedicated исполняет каждый набор на его собственном соединении и запрещает общий
`--database`. Драйвер проверяется до исполнения: неподдержанный драйвер вызывает исключение.
Пути одной группы исполняются вместе; Laravel упорядочивает файлы по basename.

Единственный журнал — таблица Laravel `migrations` базы исполнения, строка обозначает
basename файла. Дополнительный журнал CoreX не создаётся. `status()` показывает `applied`,
`pending` и `unknown` (записи с кодом пакета, которым больше не соответствует файл в реестре).

## Команды установки и проверки

```bash
php artisan packman:schema:migrate --scope=tenant
php artisan packman:schema:status --scope=tenant
php artisan packman:schema:migrate --scope=central --database=central
php artisan packman:schema:migrate --scope=dedicated
```

Имена соединений задаёт приложение. На production для миграции нужен `--force`.
Операторский шаблон аккаунта создаётся командой
`packman:schema:migrate --scope=tenant --modules --database=<template>`;
обычная активация модулей проходит через `ModuleLifecycle`.
Tenancy wave runner мигрирует Platform-наборы Tenant на соединении `tenant`, сохраняя
свою блокировку и транзакцию; модульные наборы догоняет `ModuleLifecycle::upgrade()`.
`packman:schema:status` возвращает exit 1 при `pending` или `unknown`; повторная миграция
без новых файлов ничего не исполняет.

## Форма baseline

- Каталог: `database/migrations/<set>/`; файл возвращает анонимный класс `Migration`.
  Одна таблица со всеми её объектами — один файл
  `2026_10_01_<PP><NNNN>_create_<table>.php`. Для отложенного FK внутри пакета или
  `tenant-cloud` — отдельный `2026_10_01_<PP><NNNN>_add_<table>_<purpose>.php`.
  `PP` — код пакета из [schema-sets.json](../../scripts/schema-sets.json),
  `NNNN` — шаги 0010, 0020, …; таблицы создаются раньше ссылок на них.
- PK, unique, index, FK и CHECK имеют явные литеральные имена
  `<table>_<purpose>_<kind>`, где kind — `pk|uq|ix|fk|ck`; PK — `<table>_pk`.
  Длина ≤ 63 байта, имена уникальны по scope. Длинное естественное имя сокращается
  при подготовке файла: первые 51 байт + `_` + первые 8 символов SHA-1 полного имени
  + `_` + kind; в миграции остаётся литерал. Неназванные объекты, `constrained()`
  и `*Morphs()` запрещены.
- Ключи CoreX и ссылки на них — `uuid`. Полиморфная ссылка — пара
  `<name>_type string(64)` и `<name>_id string(64)` с явным составным индексом.
  DB enum/set запрещены: используют строку и, при необходимости, CHECK на PostgreSQL.
- Счётчики, позиции, попытки, длительности, поколения и Unix-время — bigint с
  сохранением знака. Ограниченные значения (проценты, HTTP-статусы, порты, размеры,
  версии конвертов и лимиты конфигурации) могут сохранить меньший целочисленный тип
  только по записи `таблица.колонка → причина` в
  [schema-gate-allowlist.json](../../scripts/schema-gate-allowlist.json).
- `up()` использует только PHP и `Illuminate\…`: без конфигурации, DI, моделей,
  классов пакетов, выбора соединения и вставки/изменения данных. Соединение задаёт
  мигратор. `down()` удаляет созданное в обратном порядке; существующие защитные
  охранники отката сохраняются, правило чистоты относится к `up()`.
- Baseline содержит финальную форму свёрнутой цепочки с принятыми нормализациями.
  Поля, nullability, defaults и объекты вне этих нормализаций сохраняются.
  Схема определяется файлом, а не конфигурацией приложения.
- Поддержка SQLite сохраняется для наборов, которые её имеют. Таблицы, колонки
  и обычные индексы одинаковы на обоих драйверах; PG-only CHECK через ALTER,
  partial/expression indexes, jsonb-операторы, COMMENT и триггеры помещаются в ветку
  `Schema::getConnection()->getDriverName() === 'pgsql'`.

## Enforcement

`scripts/php84 composer schema-gate` проверяет все shipped-миграции пакетов и продуктов,
исключая тестовые fixture. Временных исключений по пакету нет.

| Код | Проверка |
|:--|:--|
| S1 | Каждый файл принадлежит ровно одному зарегистрированному каталогу; набор содержит файлы |
| S2 | Форма имени, код PP и уникальность basename по репозиторию |
| S3 | Явные имена индексов и ограничений; запрет неназванных Blueprint shorthand |
| S4 | Литеральные имена объектов, суффиксы, длина ≤ 63, уникальность по scope |
| S5 | Запрет DB enum/set |
| S6 | Меньшие целочисленные типы только по конечному allowlist |
| S7 | Чистота `up()`, включая вызванные внутренние методы |
| S8 | До rc: version = 1, дата файлов — `2026_10_01` |
| S9 | Созданная таблица есть в каталоге владения; пакет и scope совпадают |

[schema-sets.json](../../scripts/schema-sets.json) — проекция действующего реестра:
`scripts/php84 scripts/schema-gate.php --write-sets` обновляет её, `--check` сверяет.
`composer table-ownership-gate` использует S9 для зарегистрированных путей и T4 для
межпакетных FK; `composer table-write-ownership-gate` проверяет записи в чужие таблицы.

Golden-схема сравнивает канонические каталоги fresh-баз PostgreSQL (Tenant, Central,
Dedicated) и SQLite (наборы с поддержкой SQLite) с
[tests/Golden/schema](../../tests/Golden/schema). Проверяются типы, defaults, nullability,
имена и определения индексов, FK и CHECK; enum-типы отсутствуют, имена укладываются в 63 байта.
Изменение ожидаемого каталога через `GOLDEN_UPDATE=1` требует указать класс изменения
при проверке выпуска. Golden проверяет результат DDL, а не байтовую неизменность файла.

## После `1.0.0-rc.1`

Выпущенная миграция набора неизменна. Новая форма вводится новым файлом
`<дата>_<PP><NNNN>_<create|add>_<table>[_<purpose>].php` с новым шагом;
`SchemaSet::version` повышается на 1 в том же commit. Существующая история Laravel
`migrations` сохраняется. Для новой таблицы сразу добавляется запись владельца.

При подготовке следующего выпуска проверяют новую форму и Golden-каталог, сохраняя
выпущенные файлы. Текущий S8 закрепляет baseline до rc; допуск последующих поколений
требует соответствующей версии этого правила. Политика неизменности не разрешает
редактировать выпущенную миграцию ради прохождения Golden.
