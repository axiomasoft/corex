# P12 — нормативный дизайн ребейзлайна границ пакетов

**Schema:** p12-rebaseline-design/1
**Базовая ревизия:** `bfc05d060e3961a5881640b7f828503440bb2902`
**Вход:** `research/P12-audit-reconciliation.md` (что именно подтверждено), `research/P12-design-rag.md` (внешние факты)
**Решение:** `decisions/D41-package-boundary-rebaseline.md`

## §0. Как пользоваться этим документом

Это **единственный** источник кода для фазы P12. Порядок работы исполнителя:

1. Открыть спецификацию item'а `phases/P12/P12.N.md` — там scope, DoD и команды проверки.
2. Взять код из соответствующего параграфа этого документа (`§N`), не из `audits/*`.
3. Прогнать команды верификации из §10 для затронутых пакетов.

**Жёсткие запреты на всю фазу:**

- Не копировать PHP из `audits/2026-09-17-perplexity-*.md`: там есть невалидный код
  (тип `scalar`, метод `Manager::connection()`), см. разбор §4.
- Не создавать новые Composer-пакеты и не удалять существующие. P12 — только границы,
  имена и гейты.
- Не менять `packages/*/database/migrations/**`. DB-контракт `FROZEN v1.0`; любое изменение
  DDL требует G-SCHEMA и в P12 запрещено. Имена таблиц в §7 выносятся в конфиг
  **с сохранением текущих значений по умолчанию** — итоговый SQL байт-в-байт тот же.
- Не добавлять runtime-зависимости. Новые `require-dev` в P12 запрещены (см. §2.1).
- Не ослаблять существующие гейты. `scripts/anti-weakening-gate.sh` остаётся зелёным.
- Не трогать `packages/*/src/**` пакетов, не перечисленных в scope текущего item'а.

**Инварианты стиля** (действуют для каждого файла, который вы создаёте или правите):
`declare(strict_types=1);`; классы `final`, кроме абстрактных точек расширения;
constructor property promotion; явные типы аргументов и возврата; `#[Override]` при
переопределении; именованные аргументы при двух и более однотипных параметрах;
в `src/Contracts/` — только интерфейсы.

## §1. P12.1 — ремонт трёх P0

### §1.1 Контракт модуля `corex/intl` (дефект NEW-1)

`packages/intl/composer.json` объявляет `extra.corex.module = CoreX\Intl\IntlModule`,
а класс контракту не соответствует. `ModuleDiscovery` проверяет наследование и бросает
исключение, то есть **любое приложение с установленным `corex/intl` падает на discovery**:

```9:44:packages/modules/src/Discovery/ModuleDiscovery.php
            $class = $package['extra']['corex']['module'] ?? null;

            if (is_string($class)) {
                if (! is_subclass_of($class, CorexModule::class)) {
                    throw InvalidManifestException::notACorexModule($class);
                }
```

Вдобавок текущий `IntlModule` ссылается на класс `CoreX\Modules\Contracts\ModuleDescriptor`,
которого в репозитории нет ни в одном пакете, — то есть даже прямой вызов метода даёт fatal error.

Заменить `packages/intl/src/IntlModule.php` целиком на:

```php
<?php

declare(strict_types=1);

namespace CoreX\Intl;

use CoreX\Modules\CorexModule;
use CoreX\Modules\Manifest;
use Override;

final class IntlModule extends CorexModule
{
    #[Override]
    public function manifest(): Manifest
    {
        return Manifest::make('corex/intl')
            ->title('Internationalization')
            ->description('Typed locale, money/rate snapshots, and offline geography reference utilities.');
    }
}
```

Обязательные детали, которые нельзя изменить:

- `tablePrefix()` **не вызывается**. `Manifest::TABLE_PREFIX_PATTERN` — `/^[a-z]{2,3}$/`,
  а таблицы пакета используют префикс `corex_` (`corex_rate_snapshots`,
  `corex_reference_records` и т. д.). Любое значение здесь было бы ложью о схеме.
- `providers` в `Manifest` отсутствует как поле — и не нужен: провайдер уже объявлен
  в `extra.laravel.providers` того же `composer.json`. Информация не теряется.
- Появляется реальная зависимость `corex/intl` → `corex/modules`. Её обязательно
  объявить в `require` (см. §1.3), иначе гейт §2 станет красным.

### §1.2 Утечка `corex/core` → `corex/tenancy` (дефект A2b)

```5:14:packages/core/src/Tenancy/Contracts/TenancyManager.php
namespace CoreX\Tenancy\Contracts;

use Closure;
use CoreX\Tenancy\AccountRef;
use CoreX\Tenancy\StanclTenancyManager;
use CoreX\Tenancy\TenantContext;
use CoreX\Tenancy\WorkspaceRef;
```

`AccountRef`, `TenantContext`, `WorkspaceRef` живут в `packages/core/src/Tenancy/` — это
нормально. `StanclTenancyManager` существует только в `packages/tenancy/src/` — контракт
в ядре ссылается на реализацию из другого пакета.

Импорт используется исключительно в docblock'ах (`{@see StanclTenancyManager}`). Починка:

1. Удалить строку `use CoreX\Tenancy\StanclTenancyManager;`.
2. Каждое упоминание в docblock заменить на текстовое, без `@see` на класс:
   формулировка вида `реализуется пакетом corex/tenancy` вместо `{@see StanclTenancyManager}`.

Запрещено: переносить `StanclTenancyManager` в ядро (тянет `stancl/tenancy` в ядро),
добавлять `corex/tenancy` в `require` ядра (ядро не зависит ни от одного `corex/*` —
это правило закреплено в `tests/Arch/LayerTest.php`).

После правки проверить, что других таких утечек нет:

```bash
rg -n "^use CoreX\\\\" packages/core/src --no-heading \
  | rg -v "use CoreX\\\\(Contracts|Support|Tenancy|Modules)\\\\" || echo "clean"
```

### §1.3 Незаявленные cross-package зависимости (дефект A3)

Привести `require` в соответствие с фактическими импортами. Версии — `"@dev"`, как у всех
внутренних зависимостей. Если у пакета есть блок `repositories`, добавить туда
`{"type": "path", "url": "../<пакет>"}` для каждой новой зависимости.

| Пакет | Добавить в `require` | Причина |
|:--|:--|:--|
| `packages/intl/composer.json` | `"corex/modules": "@dev"` | `IntlModule` из §1.1 наследует `CoreX\Modules\CorexModule` |
| `packages/tasks-workflow/composer.json` | `"corex/workflow": "@dev"` | импорты `CoreX\Workflow\*` в `src/` |

Про `corex/wakeup`: в `packages/tenancy/composer.json` он объявлен в `suggest`, и
`packages/tasks-workflow` его тоже использует. Перед правкой проверить фактический характер
использования:

```bash
rg -n "CoreX\\\\Wakeup" packages/tenancy/src packages/tasks-workflow/src --no-heading
```

Правило: если импорт стоит на пути выполнения без охраны `class_exists()`/`$app->bound()` —
это `require`. Если он под охраной и есть работающий fallback — оставить в `suggest`
и **обязательно** внести в allowlist гейта §2.2 с указанием файла-охранника.

## §2. P12.3 — гейт графа пакетов

### §2.1 Почему repo-local скрипт, а не Deptrac/PHPat

`qossmic/deptrac` заброшен (RAG:✅), актуален `deptrac/deptrac`. `phpat/phpat` живой,
но это новая dev-зависимость и отдельный DSL. В репозитории уже есть работающий паттерн
исполняемых гейтов — `scripts/contract-guard.sh`, `scripts/anti-weakening-gate.sh`,
`tests/Arch/LayerTest.php` + `tests/Support/SourceScanner.php`. P12 следует ему.

Гейт читает **сырые `use`-строки**, а не разрешённые классы, — как и существующий
`SourceScanner`. Это принципиально: гейт обязан быть зелёным/красным независимо от того,
установлен ли autoloader конкретного пакета.

### §2.2 Три правила гейта

**R1 — undeclared dependency.** Если файл `packages/A/src/**` импортирует класс, владелец
которого — пакет `B` (`A !== B`), то `packages/A/composer.json` обязан содержать `B`
в `require`, либо пара `(A, B)` обязана стоять в allowlist с указанием файла-охранника.

**R2 — foreign Internal.** Пакет `A` не может импортировать `B\...\Internal\...`.
`Internal` — приватная реализация пакета; исключений нет.

**R3 — namespace ownership.** Один неймспейс-префикс определяется ровно одним Composer-пакетом,
кроме двух случаев из §2.3, перечисленных в `reservations` явно.

Владелец класса определяется индексом, построенным сканированием `packages/*/src/**/*.php`:
из каждого файла берётся `namespace X;` и имя объявленного типа; FQCN сопоставляется
владельцу по самому длинному совпадению префикса — та же дисциплина, что у PSR-4.

### §2.3 Правило host-reserved namespace

Расщепление неймспейса между пакетами допустимо **только** при одновременном выполнении
трёх условий:

1. хост-пакет объявляет резервацию в `reservations` конфига гейта;
2. хост-пакет определяет **ноль** классов под зарезервированным префиксом;
3. `composer.json` пакета-адаптера требует хост в `require`.

Как это разводит два случая:

| Префикс | Хост | Адаптер | Условие 2 | Вердикт |
|:--|:--|:--|:--|:--|
| `CoreX\Search\Meilisearch\` | `corex/search` | `corex/search-meilisearch` | выполнено: в `packages/search/src` нет классов под этим префиксом | **разрешено** (§5.4) |
| `CoreX\Tenancy\` | `corex/core` | `corex/tenancy` | **нарушено**: ядро определяет 16 классов в `CoreX\Tenancy\` | **legacy-исключение** |

`CoreX\Tenancy\` не приводится в порядок в P12: развод потребовал бы перемещения 16 классов
ядра и ломал бы публичный API во всех пакетах. Он вносится в гейт как **именованное
просроченное исключение** с условием снятия, а не замалчивается:

```json
"legacy_namespace_splits": [
  {
    "prefix": "CoreX\\Tenancy\\",
    "packages": ["corex/core", "corex/tenancy"],
    "adr": "ADR-CORE-REDUCTION",
    "expires_when": "corex/core reduction lands; core defines zero classes under CoreX\\Tenancy\\"
  }
]
```

Гейт печатает это исключение в отчёте на каждом прогоне. Молчаливое прохождение запрещено
(`root.evidence-integrity`: отключённая проверка не считается зелёной).

### §2.4 Что создать

- `scripts/package-graph-gate.php` — скрипт: строит индекс, применяет R1–R3, печатает
  таблицу нарушений `путь:строка: импорт → пакет-владелец`, пишет машинный отчёт
  в `artifacts/P12-package-graph/report.json`, выходит с кодом `1` при нарушении.
- `scripts/package-graph-gate.json` — конфиг: `allowlist` (R1), `reservations` (R3),
  `legacy_namespace_splits` (§2.3). Каждая запись обязана иметь поле-причину.
- `tests/Arch/PackageGraphTest.php` — Pest-тест, запускающий скрипт и требующий
  пустого списка нарушений; сообщение об ошибке перечисляет их построчно.
- Строка вызова в корневом `composer.json` в существующей цепочке гейтов.

Требование к диагностике: красный гейт обязан называть файл, строку, импорт и пакет-владельца.
Вердикт без адреса нарушения не принимается.

## §3. P12.2 — полный контур тестов пакетов

`composer.json:67` (`test:packages`) запускает 8 пакетов из 20. Не запускаются:
`attributes`, `core`, `import-export`, `intl`, `intl-search`, `media`, `modules`, `panels`,
`search`, `search-meilisearch`, `tasks-workflow`, `workflow`.

Работы:

1. Добавить в `packages/core/composer.json` и `packages/modules/composer.json` скрипты
   `test`, `test:coverage`, `analyse`, `lint:check`. Форму копировать у
   `packages/intl/composer.json` (см. вывод в §0-входе): относительные пути
   `../../vendor/bin/...`, `--bootstrap=../../vendor/autoload.php`, разделение
   `--exclude-group=pg` / `--group=pg`.
2. Расширить `test:packages` до всех 20 пакетов. Сохранить существующее разделение
   SQLite-лейна и pg-лейна: пакеты, у которых есть `test:pg`, в основном прогоне идут
   с `--exclude-group=pg`.
3. `corex/search-meilisearch` исключает группу `meilisearch` в основном прогоне
   (скрипт уже так устроен) — не менять.
4. Если у пакета нет `phpunit.xml` или каталога `tests/` — **не создавать тесты**.
   Зафиксировать в receipt item'а как явную дыру покрытия с перечислением пакетов.
   Выдумывать тесты в P12 запрещено.

Требование доказательства: receipt обязан содержать вывод, из которого видно 20 запущенных
пакетов и отсутствие «тихих» пропусков. Ненулевой код возврата любого пакета блокирует item.

## §4. P12.4 — `corex/import-export` → `corex/transfer`

### §4.1 Почему hard rename, а без `replace`

Оба аудита предлагают `"replace": {"corex/import-export": "self.version"}` как способ
сохранить совместимость. Это неверно: `replace` влияет **только** на solver Composer и
не сохраняет ни один PHP-неймспейс (RAG:✅). Пакет `@dev`, монорепо-локальный, внешних
потребителей нет, поэтому выбран hard rename без bridge-слоя. `class_alias`-мост
тоже не вводится: он нужен для уже используемого извне неймспейса.

### §4.2 Дельта переименования

| Было | Стало |
|:--|:--|
| каталог `packages/import-export/` | `packages/transfer/` |
| Composer-имя `corex/import-export` | `corex/transfer` |
| PSR-4 `CoreX\ImportExport\` | `CoreX\Transfer\` |
| PSR-4 dev `CoreX\ImportExport\Tests\` | `CoreX\Transfer\Tests\` |
| `src/Support/` | `src/Data/` |
| конфиг `config/corex-import-export.php` | `config/corex-transfer.php` |
| ключ конфига `corex-import-export.*` | `corex-transfer.*` |

Переименование каталога и файлов — **только** `git mv`, чтобы история сохранилась.

Обязательно обновить все внешние ссылки на пакет, не только его содержимое:
корневой `composer.json` (`repositories`, `require`, `test:packages`), блоки `repositories`
и `require` в других пакетах, `phpstan.neon` и `pint.json`, `.github/workflows/**`,
`docs/**`, профили установки и lock-файлы профилей. Проверка полноты:

```bash
rg -n -i "import.?export" --hidden -g '!vendor' -g '!node_modules' -g '!.git' . | rg -v "^audits/"
```

Пустой вывод (кроме `audits/`, где имя зафиксировано исторически) — условие закрытия item'а.

### §4.3 Что в этом item'е делать запрещено

`Support/` → `Data/` — **только перемещение и правка неймспейса**. Разделение
`DatabaseTransferRunner`, изменение таксономии ошибок, вынос внешнего вызова из
retryable-транзакции и снятие `catch (Throwable)` (дефекты D2/D3/D4) **отложены**
в ADR через P12.9. Смешивать переименование и смену поведения в одном item'е запрещено:
это сделало бы дифф непроверяемым.

## §5. P12.5 — консолидация search через `SearchManager`

### §5.1 Дефект

Два провайдера регистрируют один и тот же биндинг:

```23:23:packages/search/src/SearchServiceProvider.php
        $this->app->scoped(SearchProvider::class, function (Application $app): SearchProvider {
```

```30:30:packages/search-meilisearch/src/MeilisearchServiceProvider.php
        $this->app->scoped(SearchProvider::class, static fn (): SearchProvider => new MeilisearchQueryAdapter(
```

Побеждает тот, кто зарегистрировался последним, — итог зависит от порядка загрузки
провайдеров, которым приложение управляет лишь косвенно. Вторая половина дефекта:
при невалидном конфиге `corex/search` молча подставляет `UnsupportedSearchProvider`,
и неверная конфигурация продакшена выглядит как «поиск ничего не нашёл».

### §5.2 Целевая схема

Реестр драйверов вместо конкуренции за биндинг. Ответственности:

- `corex/search` владеет `SearchManager` и драйверами `postgres`, `null`;
- `corex/search-meilisearch` **регистрирует** драйвер `meilisearch` в менеджере
  и больше **не** биндит `SearchProvider`;
- `SearchProvider` резолвится через менеджер.

`SearchManager` наследует `Illuminate\Support\Manager`: `getDefaultDriver()`,
`create<Name>Driver()`, `extend()`, `driver()`. **Метода `connection()` у `Manager` нет** —
пример из аудита №1 использует его и не работает; соединение брать через
`$this->container->make('db')->connection()`.

`packages/search/src/SearchManager.php`:

```php
<?php

declare(strict_types=1);

namespace CoreX\Search;

use CoreX\Search\Contracts\SearchProvider;
use CoreX\Search\Contracts\SearchSchema;
use CoreX\Search\Exceptions\InvalidSearchConfiguration;
use CoreX\Search\Internal\Postgres\PostgresSearchProvider;
use CoreX\Search\Internal\Query\SearchCursorCodec;
use CoreX\Search\Internal\UnsupportedSearchProvider;
use Illuminate\Support\Manager;
use Override;

final class SearchManager extends Manager
{
    #[Override]
    public function getDefaultDriver(): string
    {
        $driver = $this->config->get('corex-search.driver');

        if (! is_string($driver) || $driver === '') {
            throw InvalidSearchConfiguration::missingDriver();
        }

        return $driver;
    }

    protected function createNullDriver(): SearchProvider
    {
        return new UnsupportedSearchProvider;
    }

    protected function createPostgresDriver(): SearchProvider
    {
        $secret = $this->config->get('corex-search.cursor_secret');

        if (! is_string($secret) || strlen($secret) < 32) {
            throw InvalidSearchConfiguration::weakCursorSecret();
        }

        if (! $this->container->bound(SearchSchema::class)) {
            throw InvalidSearchConfiguration::missingSchemaBinding();
        }

        if (! $this->container->bound('db')) {
            throw InvalidSearchConfiguration::missingDatabase();
        }

        return new PostgresSearchProvider(
            connection: $this->container->make('db')->connection(),
            schemas: $this->container->make(SearchSchema::class),
            cursors: new SearchCursorCodec(secret: $secret),
            configuration: (string) $this->config->get('corex-search.postgres.text_search_config', 'simple'),
        );
    }
}
```

Замечания, которые нельзя потерять:

- `Manager` сам бросает `InvalidArgumentException` для неизвестного имени драйвера —
  отдельная проверка не нужна. Именно это делает `driver: 'meilisearch'` без установленного
  пакета-адаптера честной ошибкой вместо тихого пустого поиска.
- `UnsupportedSearchProvider` **сохраняется** как реализация драйвера `null`. Класс
  остаётся, меняется только его роль: из молчаливого fallback он становится результатом
  явного выбора `driver: 'null'`. Это сохраняет существующие тесты пакета.
- Исключения складываются в `packages/search/src/Exceptions/InvalidSearchConfiguration.php`
  (в `Contracts/` — только интерфейсы). Именованные конструкторы обязаны в тексте называть
  ключ конфига и способ починки.

`packages/search/src/SearchServiceProvider.php` — новый `register()`:

```php
    #[Override]
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/corex-search.php', 'corex-search');

        $this->app->singleton(SearchManager::class, static fn (Application $app): SearchManager => new SearchManager($app));

        $this->app->scoped(SearchProvider::class, static fn (Application $app): SearchProvider => $app->make(SearchManager::class)->driver());
    }
```

`SearchManager` — `singleton`, потому что это реестр драйверов и он не держит tenant-состояния.
`SearchProvider` остаётся `scoped`, как сейчас: сам провайдер держит соединение.

### §5.3 Значение по умолчанию

`packages/search/config/corex-search.php` сейчас: `'driver' => 'unsupported'`.
Заменить на `'driver' => 'null'` — иначе дефолтный конфиг падает на неизвестном драйвере.
Это осознанный выбор: отключённый поиск остаётся рабочим дефолтом, а **невалидная**
конфигурация (выбранный драйвер + сломанные параметры) теперь падает громко.
Разбор fail-closed/null-driver — RAG:✅ §3.2.

### §5.4 Сторона `search-meilisearch`

Неймспейс: `CoreX\SearchMeilisearch\` → `CoreX\Search\Meilisearch\` (и `...\Tests\`
соответственно). Допустимо строго по правилу §2.3; перед закрытием item'а убедиться, что
в `packages/search/src` нет ни одного класса под `CoreX\Search\Meilisearch\`, и внести
резервацию в конфиг гейта:

```json
"reservations": [
  {
    "prefix": "CoreX\\Search\\Meilisearch\\",
    "host": "corex/search",
    "owner": "corex/search-meilisearch",
    "reason": "host-reserved adapter namespace; host defines zero classes under the prefix"
  }
]
```

`MeilisearchServiceProvider` переписывается: регистрация драйвера вместо биндинга
`SearchProvider`, и валидация конфига переносится **внутрь** фабрики драйвера.
Ключевая причина переноса: сейчас `register()` читает конфиг и делает `return` на этапе
регистрации провайдеров, то есть решение принимается до того, как приложение успело
переопределить конфиг. Регистрация драйвера обязана происходить всегда, а валидация —
в момент выбора этого драйвера.

```php
    #[Override]
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/corex-search-meilisearch.php', 'corex-search-meilisearch');
    }

    #[Override]
    public function boot(): void
    {
        $this->app->make(SearchManager::class)->extend('meilisearch', static function (Application $app): SearchProvider {
            // читать конфиг и бросать InvalidMeilisearchConfiguration при:
            //   host не string или не проходит FILTER_VALIDATE_URL
            //   index_uid не string или пустой
            //   key не string или пустой
            //   ! class_exists(\Meilisearch\Client::class)  -> сообщение с composer require
            // затем вернуть new MeilisearchQueryAdapter(client: new Client($host, $key), indexUid: $indexUid)
        });
    }
```

Обязательные детали:

- `extend()` вызывается в `boot()`, а `SearchProvider` — `scoped` и резолвится лениво,
  поэтому драйвер зарегистрирован раньше первого использования независимо от порядка
  загрузки провайдеров. Это и есть устранение исходного дефекта.
- Ключ `enabled` из конфига **удаляется**. Выбор драйвера теперь один и тот же —
  `corex-search.driver`; два независимых переключателя дают неопределимое состояние
  (`enabled: true` + `driver: postgres`).
- Охрана `class_exists(\Meilisearch\Client::class)` обязательна с текстом, называющим
  пакет для установки: SDK может отсутствовать при ручной сборке автозагрузчика.
- Пин `"meilisearch/meilisearch-php": "1.16.1"` **не трогать**: смена ограничения
  зависимости требует G-DEPENDENCY и отложена в ADR (актуальная ветка SDK — `2.0.0-beta.*`,
  RAG:✅).

## §6. P12.6 — `corex/intl-search` → `corex/search-intl`

Переименование ради читаемой топологии: имя отражает «адаптер поверх search», а не
самостоятельную возможность. Механика — как в §4.1: hard rename, без `replace`,
без `class_alias`.

| Было | Стало |
|:--|:--|
| `packages/intl-search/` | `packages/search-intl/` |
| `corex/intl-search` | `corex/search-intl` |
| PSR-4 `CoreX\IntlSearch\` | `CoreX\SearchIntl\` |

Неймспейс — **плоский** `CoreX\SearchIntl\`, а не `CoreX\Search\Intl\`. Обоснование:
правило §2.3 требует резервации у хоста и нулевого числа классов под префиксом; заводить
вторую резервацию в `CoreX\Search\` ради пакета-декоратора из нескольких классов —
неоправданная сложность. Одна резервация (§5.4) и одно legacy-исключение (§2.3) —
столько нюанса, сколько репозиторий готов обслуживать.

Bridge-политика, которую item обязан зафиксировать в `docs/dev-doctrine.md`:
пакет-мост декорирует контракты своих хостов и **не владеет** предметными контрактами.
Единственный собственный контракт `Contracts/SavedViewLabelReader` допустим, потому что
описывает саму операцию декорирования. Контроль — правилом R2 гейта §2.2 плюс проверкой,
что в `packages/search-intl/src/Contracts/` не появляется контрактов, чьи реализации
живут в других пакетах.

Ложные предпосылки аудитов, на которые опираться нельзя: saved views принадлежат
`corex/core` (не `panels`/`views`), а `SqlSavedViewLabelReader` не содержит SQL —
он делегирует `TranslationReader`. См. разбор E2/E3.

## §7. P12.7 — `corex/attributes`: table-map, обязательная валидация, optional `modules`

### §7.1 Типизированный table-map (дефект B1)

Имена таблиц захардкожены в четырёх классах: `JsonbAttributeStore.php:155,160`,
`EavAttributeStore.php:37,54,65,75,96`, `QueuedSchemaOperations.php:39`,
`SchemaOperationWorker.php:30,36,49,118,155`.

Целевая форма — иммутабельный объект, собираемый из конфига **один раз** в провайдере
и внедряемый в конструктор. Обоснование (Octane, тестируемость, ранняя валидация) —
RAG:✅ §3.3.

`packages/attributes/src/Data/AttributeTables.php`:

```php
<?php

declare(strict_types=1);

namespace CoreX\Attributes\Data;

use CoreX\Attributes\Exceptions\InvalidAttributeTables;

final readonly class AttributeTables
{
    private const IDENTIFIER = '/^[a-z][a-z0-9_]*$/';

    public function __construct(
        public string $documents,
        public string $values,
        public string $fields,
        public string $schemaOperations,
    ) {
        foreach (get_object_vars($this) as $property => $table) {
            if (preg_match(self::IDENTIFIER, $table) !== 1) {
                throw InvalidAttributeTables::invalidIdentifier(property: $property, table: $table);
            }
        }

        $tables = [$this->documents, $this->values, $this->fields, $this->schemaOperations];

        if (count($tables) !== count(array_unique($tables))) {
            throw InvalidAttributeTables::duplicateNames();
        }
    }
}
```

Жёсткие требования:

- Значения по умолчанию в `config/corex-attributes.php` **равны текущим захардкоженным
  строкам, символ в символ**. Первым шагом item'а выписать фактические имена командой
  `rg -o "table\('[a-z_]+'\)" packages/attributes/src` и взять их как есть.
  Список полей объекта определяется этим выводом, а не примером выше.
- Валидация идентификатора обязательна: имя таблицы в query builder подставляется
  не как bound-параметр (RAG:✅ §3.3), поэтому произвольная строка из конфига неприемлема.
- Миграции не меняются. Проверка: `git diff --stat packages/attributes/database/` пуст.
- `config()` внутри store-классов не появляется. Объект приходит через конструктор;
  `config()` остаётся только в провайдере — это и есть граница композиции.

### §7.2 Обязательная schema-валидация (дефект B4)

```28:31:packages/attributes/src/Internal/Engine/JsonbAttributeStore.php
        private ?FieldCatalogue $catalogue = null,
```

Nullable `FieldCatalogue` означает, что запись значений может пройти **без** проверки
по схеме. То же в `EavAttributeStore.php:27-30`. Сделать параметр обязательным
и не-nullable в обоих классах, удалить ветки пропуска валидации
(`JsonbAttributeStore.php:189-192`, `EavAttributeStore.php:156-159`).

Провайдер уже всегда передаёт каталог (`AttributesServiceProvider.php:39,45`), поэтому
на стороне вызова правок не требуется — но тесты, конструирующие store без каталога,
придётся обновить. Это ожидаемая часть item'а.

### §7.3 `corex/modules` как optional (дефект B2/B3)

Перенести `"corex/modules"` из `require` в `suggest` в `packages/attributes/composer.json`
с текстом, называющим, что теряется без пакета. Fallback уже реализован
(`AttributesServiceProvider.php:68-78`) и корректен: без `EntityRegistry`/`ModuleRegistry`
нет источника определений полей, поэтому `UnavailableAttributes` — правильный ответ,
а не сокрытие ошибки.

Что требуется сверх переноса строки:

- Внести пару `(corex/attributes, corex/modules)` в allowlist гейта §2.2, указав
  файлом-охранником `AttributesServiceProvider.php` и строки проверки `$app->bound(...)`.
- Задокументировать в README пакета: без `corex/modules` динамические поля недоступны,
  и это наблюдаемое состояние, а не деградация без следов.
- Разделение пакета на три (предложение аудита №2) **не выполняется**: корневая причина —
  обязательность зависимости, а не число пакетов.

## §8. P12.8 — `Closure` из `DataScope` [OWNER-GATE:G-API]

### §8.1 Дефект

```13:20:packages/core/src/Support/Data/DataScope.php
    public function __construct(
        public TenantContext $context,
        public string $connectionIdentity,
        public string $actorId,
        public Closure $authorize,
        public string $restoreEpoch,
    ) {
```

`DataScope` передаётся в search, transfer и workflow, то есть пересекает границу очереди.
PHP не сериализует `Closure` — попытка даёт `Serialization of 'Closure' is not allowed`
(RAG:✅ §3.5). Дополнительно: closure захватывает лексическое окружение, поэтому решение
о доступе, принятое на момент постановки задачи, переиспользуется на момент исполнения —
это разрыв time-of-check/time-of-use, и он не диагностируется на `QUEUE_CONNECTION=sync`.

`laravel/serializable-closure` — **не** решение. Он делает код транспортируемым, но
транспортируемость поведения не даёт семантики авторизации: подписанный payload
доказывает неизменность данных, а не право актора выполнить операцию **сейчас**.
Плюс два подтверждённых по README ограничения: сериализация в REPL-окружении
не поддерживается, а несколько замыканий с одинаковой сигнатурой на одной строке
исходника после сериализации могут стать неразличимыми.

> Внимание: утверждение «README не поддерживает замыкания, ссылающиеся на объекты
> с `readonly`-свойствами» фигурировало в RAG-ответе и **не подтвердилось** при прямой
> проверке README (`artifacts/P12-design/`, 2882 байта, HTTP 200): слова `readonly`
> в нём нет. Аргументом не пользуемся. Обоснование §8.2 от этого не зависит.

### §8.2 Целевая форма

`Closure $authorize` заменяется на инжектируемый контракт, возвращающий решение-данные.
`DataScope` остаётся `final readonly` и становится полностью сериализуемым.

Обязательные свойства целевого решения:

- В `DataScope` не остаётся ни одного свойства типа `Closure`, `callable`, `object`
  с поведением. Допустимы идентификаторы, скаляры, enum'ы и `readonly`-DTO из скаляров.
- Проверка доступа объявляется интерфейсом в `packages/core/src/Contracts/`
  и резолвится **на исполняющей стороне** из контейнера, а не переносится в payload.
- Результат проверки — `readonly`-объект решения с полем «разрешено» и кодом причины,
  пригодным для аудита. Булев возврат недостаточен: он не оставляет следа, почему отказано.
- Ни один вызов существующей проверки не должен исчезнуть. Перед правкой составить полный
  список обращений и сверить его после:
  ```bash
  rg -n "authorize" packages/*/src --no-heading | rg -v "^packages/[^/]+/src/.*Contracts/"
  ```

### §8.3 Почему это единственный G-API-item фазы

`DataScope` — часть контракта `shared-data-foundation/1`, поэтому правка требует одобрения
владельца. Полная декомпозиция на `AccessContext`/`StorageContext` (предложение обоих
аудитов) в P12 **не выполняется** — она меняет контракт шире необходимого и отложена в ADR.
Снимается ровно один дефект: несериализуемое поведение в сериализуемом объекте.

Item не считается закрытым, пока не показан тест, который сериализует `DataScope`
и восстанавливает его без исключения. Без такого теста дефект может вернуться незаметно —
именно так он и появился.

## §9. Инварианты, общие для всех item'ов

| Инвариант | Как проверяется |
|:--|:--|
| DDL не изменён | `git diff --stat -- 'packages/*/database/**'` пуст |
| Нет новых зависимостей | дифф `require`/`require-dev` содержит только строки, названные в §1.3, §7.3 |
| Гейты не ослаблены | `bash scripts/anti-weakening-gate.sh` зелёный |
| Слои не нарушены | `tests/Arch/LayerTest.php` зелёный |
| В `Contracts/` только интерфейсы | новые исключения и DTO создаются в `Exceptions/` и `Data/` |
| Стиль | `composer lint` и `composer analyse` для затронутых пакетов |
| Переименование сохраняет историю | `git log --follow` на перемещённый файл показывает прошлые коммиты |

Коммиты — Russian Conventional Commits. Релиз-видимые изменения — в корневой
`CHANGELOG.md` под `[Unreleased]`; отдельного ledger'а у пакетов нет.

## §10. Команды верификации

Для затронутого пакета:

```bash
cd packages/<пакет> && composer test && composer analyse && composer lint:check
```

Корневой прогон (обязателен для item'ов, меняющих границы или имена):

```bash
composer analyse
composer test:arch
php scripts/package-graph-gate.php        # после P12.3
bash scripts/anti-weakening-gate.sh
composer test:packages                    # после P12.2 — все 20 пакетов
```

Если pg-лейн недоступен в окружении, это раскрывается в receipt как ограничение
окружения с указанием непокрытых групп. Пропущенная проверка зелёной не считается.
