# Эскизы верхнего слоя — метаданные, гейты, атрибуты, реестры (P1–P3, P10, P11)

**Статус: несущие эскизы, не финальный код.** Правила использования — см. заголовок
`01-shapes-runtime.md`. Детализация фазы принимает форму или отклоняет её с записью причины.

---

## 1. P1 — Схема `extra.corex`

Ключ уже существует в 9 пакетах и содержит только `module`. Расширяем его, а не вводим второй.

```json
{
  "extra": {
    "corex": {
      "module": "CoreX\\Media\\MediaModule",
      "package-type": "capability",
      "stability": "experimental",
      "public-api": [
        "CoreX\\Media\\Contracts",
        "CoreX\\Media\\Data",
        "CoreX\\Media\\Exceptions"
      ]
    }
  }
}
```

- `package-type` — закрытый набор: `foundation | capability | integration | product | tooling | fixture`.
- `stability` — `stable | experimental | internal | deprecated`.
- `public-api` — список **неймспейсов**, не символов (D4): список символов расходится с кодом,
  список неймспейсов проверяется машинно и не гниёт.
- `module` отсутствует у пакетов, которые не являются модулями, — это не дефект.

Ожидаемое распределение типов (черновик для P1, проверить по фактическому назначению):
`core` → foundation; `modules` → foundation; `attributes`, `auth`, `media`, `notifications`,
`recurrence`, `search`, `tasks`, `tenancy`, `transfer`, `wakeup`, `workflow`, `panels`, `intl` →
capability; `search-intl`, `search-meilisearch`, `tasks-workflow` → integration; `api` →
capability либо отдельный тип gateway, если P13 подтвердит; `crm` → product.

---

## 2. P2 — Схема инвентаря

`artifacts/architecture/files.csv` (плановый путь). Колонки — ровно те, что нужны последующим
фазам, без «на всякий случай»:

```text
package,file,namespace,symbol,symbol_kind,visibility,public_api,zone_or_context,layer,
framework_dependency,cross_package_imports,config_reads,facades,tables,events_emitted,
events_consumed,crosses_durable_boundary,suggested_namespace,suggested_name,verdict,risk,notes
```

`verdict` — закрытый набор: `KEEP | MOVE | RENAME | SPLIT | INLINE | DELETE | PUBLIC | INTERNAL`.

Самопроверка сканера обязательна: он должен воспроизвести числа из
`research/00-baseline-metrics.md` (838 файлов в `src`, 152 `config(`, 0 `static $`, 19 каталогов
`Internal` на 188 файлов, 85 миграций). Расхождение без объяснения — дефект сканера, а не
устаревшая база.

Почему нужен AST, а не только `rg`: алиасы (`use X as Y`), групповые импорты, динамические
вызовы фасадов и строковые имена классов в конфигурации текстовым поиском не ловятся. Минимум —
`nikic/php-parser` уровня dev-зависимости либо `token_get_all()` для узкого набора правил.

Расхождение трёх графов — самостоятельный результат фазы, а не побочный: composer-граф говорит,
что объявлено; namespace-граф — что импортируется; runtime-граф — что реально связывается в
контейнере. Пакет, присутствующий во втором и отсутствующий в первом, — это дефект R1 гейта;
присутствующий в третьем и отсутствующий во втором — скрытая связь через строковое имя.

---

## 3. P3 — Форма трёх новых гейтов

### 3.1 Гейт публичной поверхности

Вход — `extra.corex.public-api` каждого пакета. Правило: импорт символа из другого пакета
допустим, только если его неймспейс входит в объявленный `public-api` того пакета либо в
существующий allowlist исключений.

```php
// Псевдокод правила, встраиваемого в scripts/package-graph-gate.php как R4
foreach ($imports as $import) {
    $owner = $this->packageOwningNamespace($import);

    if ($owner === null || $owner === $currentPackage) {
        continue;
    }

    if (! $this->isPublicNamespace($owner, $import) && ! $this->isAllowlisted($currentPackage, $import)) {
        $violations[] = "R4 {$file}: импорт непубличного {$import} из пакета {$owner}";
    }
}
```

Важно: R4 добавляется **в существующий гейт**, а не рядом вторым инструментом. У R2 (чужой
`Internal`) и R4 (не перечисленный неймспейс) разные причины отказа, и сообщения должны
различаться, иначе диагностика вырождается.

### 3.2 Гейт сериализуемости durable-payload

Проблема: «durable» — не тип PHP, гейт должен знать, что проверять. Решение — маркерный
интерфейс без методов плюс правило PHPStan:

```php
/** Помечает тип, пересекающий durable-границу: очередь, outbox, чекпойнт, wakeup, bulk. */
interface DurablePayload {}
```

Правило: у типа, реализующего `DurablePayload`, все публичные свойства и все типы в сигнатуре
конструктора — скаляры, `null`, enum с backing-типом, `DateTimeImmutable`, либо массивы из них.
Запрещены: объекты контейнера, Eloquent-модели, `Closure`, request-объекты, `TenantContext`,
любые интерфейсы.

Негативная проверка: подсадить свойство типа `TenantContext` в помеченный класс — гейт краснеет
с именем файла и именем свойства.

### 3.3 Гейт `config()` вне composition boundary

Allowlist путей: `*/src/*ServiceProvider.php`, `*/src/**/Commands/*`, плюс явные исключения с
указанием причины в конфиге гейта. Всё остальное — нарушение. В P3 гейт наблюдательный (печатает,
не валит), в P7 становится блокирующим: иначе он краснеет на 152 существующих вызовах и его
немедленно отключат.

---

## 4. P10 — Attributes: сменный источник определений

Ключевая поправка к аудиту: **новый интерфейс не нужен**. Порт уже есть:

```php
// packages/attributes/src/Contracts/FieldCatalogue.php — существует
interface FieldCatalogue
{
    public function snapshot(DataScope $scope, string $entity): FieldSnapshot;
}
```

Нужны его реализации и снятие требования `ModuleRegistry`. Сейчас провайдер отдаёт
`UnavailableAttributes`, если не связаны **и** `EntityRegistry`, **и** `ModuleRegistry`.

```php
final readonly class StaticFieldCatalogue implements FieldCatalogue
{
    /** @param array<string, list<FieldDefinition>> $byEntity */
    public function __construct(private array $byEntity) {}
}

final readonly class DatabaseFieldCatalogue implements FieldCatalogue
{
    public function __construct(
        private ConnectionInterface $connection,
        private AttributeTables $tables,
    ) {}
}

final readonly class CompositeFieldCatalogue implements FieldCatalogue
{
    /** @param list<FieldCatalogue> $sources — порядок = precedence */
    public function __construct(
        private array $sources,
        private ConflictPolicy $policy,
    ) {}
}

final readonly class CachedFieldCatalogue implements FieldCatalogue
{
    public function __construct(
        private FieldCatalogue $inner,
        private CacheRepository $cache,
        private Clock $clock,
    ) {}
}
```

Провайдер после правки: `ModuleFieldCatalogue` (ренейм `RegistryFieldCatalogue` в P15)
подключается **как один из источников** composite, если модульная система присутствует; при её
отсутствии остаются статический и БД-источники. `UnavailableAttributes` остаётся только для
случая «нет ни одного источника».

### Conflict policy — шесть различимых случаев

```php
enum AttributeConflict: string
{
    case DuplicateKey = 'duplicate_key';               // два источника дают один key
    case DuplicateStableId = 'duplicate_stable_id';    // один id под разными key
    case TypeMismatch = 'type_mismatch';
    case FlagConflict = 'flag_conflict';               // required/default/index расходятся
    case RemovedInHigherPrecedence = 'removed_in_higher_precedence';
    case DeprecatedShadowsActive = 'deprecated_shadows_active';
}
```

Политика для каждого случая объявляется явно (`reject` | `higher-precedence-wins` |
`merge-if-identical`), и дефолт — `reject`. «Побеждает последний» без объявления — это
молчаливое переопределение схемы поверх живых данных.

### Стабильный идентификатор поля (Q6 закрывается в P10)

```php
final readonly class AttributeId
{
    public function __construct(public string $value) {}   // стабилен навсегда
}

final readonly class AttributeKey
{
    public function __construct(public string $value) {}   // может переименовываться
}

final readonly class AttributeDefinition
{
    public function __construct(
        public AttributeId $id,
        public AttributeKey $key,
        public AttributeType $type,
        public AttributeLifecycle $lifecycle,
        public SchemaRevision $introducedIn,
        public ?SchemaRevision $deprecatedIn,
        public AttributeConstraints $constraints,
        public IndexingPolicy $indexing,
        public VisibilityPolicy $visibility,
    ) {}
}

enum AttributeLifecycle: string
{
    case Draft = 'draft';
    case Active = 'active';
    case Deprecated = 'deprecated';
    case Migrating = 'migrating';
    case Retired = 'retired';
}
```

Существующие `SchemaChange` kinds (`add/change/retire/drop`) и статусы операций
(`pending/verified/failed`) не переписываются: lifecycle определения — **другая** ось,
описывающая состояние поля, а не состояние DDL-операции.

### Выбор движка хранения по subject type (сейчас — глобальный ключ конфига)

```php
interface AttributeStoreResolver
{
    public function for(string $entity): AttributeValueStore;
}
```

Политика выбора учитывает ожидаемую кардинальность, требования к фильтрации и аналитике,
уникальные ограничения и частоту записи — но **гранулярность — subject type или attribute set,
никогда не отдельное поле** (выбор на каждое поле почти гарантированно создаёт неуправляемую
сложность).

Третье состояние, которого нет в аудите и которое даёт первичный источник по PostgreSQL:
продвижение «горячего» поля. JSONB + GIN отвечает на «найти документы», но **не** отдаёт порядок
для `ORDER BY` — сортировку из индекса умеет только B-tree. Поэтому политика индексирования
различает три уровня:

1. редкое/отображаемое поле — JSONB без отдельного индекса;
2. фильтруемое/сортируемое — B-tree expression index с partial-предикатом по наличию ключа и
   точным совпадением cast'а и направления, плюс `id` в конце как tie-breaker для keyset-пагинации;
3. продуктово важное — типизированная колонка с настоящими ограничениями.

Ловушка, которую обязана закрыть спека: cast в индексе и запросе падает на грязном значении
(`"N/A"` в числовом поле). Значит либо валидация на записи, либо generated column, заполняемая
контролируемым кодом.

### Восемь независимых проверок записи

Порядок важен: дешёвые и не раскрывающие информацию проверки — первыми.

```text
1. поле существует в снапшоте схемы
2. ревизия схемы совместима с заявленной вызовом
3. tenant владеет subject'ом
4. актор может видеть запись
5. актор может писать это поле
6. текущая операция вправе мутировать
7. тип значения валиден
8. ограничения валидны
```

«Поле зарегистрировано» не означает «поле разрешено» — это и есть schema-confusion, от которой
защищает список. Пробел `onlyFields` (N08: компилируется в метаданные, но диспетчер его не
применяет) закрывается пунктами 5 и 6 совместно с P13.

**Сверить с деревом:** `packages/attributes/src/AttributesServiceProvider.php:86-96`,
`Internal/Registry/RegistryFieldCatalogue.php`, `Internal/Engine/{JsonbAttributeStore,EavAttributeStore}.php`,
`Support/Schema/**`, `packages/core/src/Support/Data/RecordRef.php` (идентичность уже opaque —
`SubjectType`/`SubjectId`/`SubjectRef` вводить не нужно).

---

## 5. P11 — Реестры: compile → frozen

В `packages/modules` уже есть `RegistryCompiler` → `CompiledRegistry` → `CompiledModuleRegistry`.
Не хватает ровно трёх вещей.

```php
final readonly class CompiledRegistry
{
    /**
     * @param array<string, list<CompiledExtension>> $byPoint
     * @param array<string, CompiledExtension> $byId
     */
    public function __construct(
        public array $byPoint,
        public array $byId,
        public string $fingerprint,
    ) {}
}
```

**(1) Детерминированный порядок.** `priority DESC, id ASC`. Не опираться на порядок обхода
файловой системы, порядок включения модулей и порядок вставки в массив — это не публичное
правило предшествования, а случайность. Равный приоритет не является механизмом разрешения
конфликта: tie-breaker обязателен.

**(2) Дубликат — ошибка компиляции.**

```php
if (isset($seen[$key])) {
    throw new DuplicateExtensionId(sprintf(
        'Точка расширения %s: идентификатор %s объявлен дважды — %s и %s.',
        $point, $key, $seen[$key]->provider, $candidate->provider,
    ));
}
```

Замена допускается только явной декларацией `replaces` с проверкой существования цели и
ацикличности графа замен. Сообщение обязано называть **оба** источника, иначе диагностика
бесполезна.

**(3) Fingerprint** покрывает: набор и версии включённых модулей, нормализованные декларации,
версию схемы точки расширения, релевантную конфигурацию, версию сборки. Именно это предотвращает
классический дефект «пакет обновили, а устаревший скомпилированный артефакт выжил».

**Freeze:**

```php
public function freeze(): void
{
    $this->frozen = true;
}

public function register(ExtensionDefinition $definition): void
{
    if ($this->frozen) {
        throw new RegistryFrozen($definition->id());
    }
    // ...
}
```

И самое неочевидное требование фазы: **durable-решение сохраняет fingerprint либо id+версию
использованного расширения.** Иначе повтор джоба после активации/обновления расширения молча
выполнит другую реализацию. Затрагивает `workflow`, `wakeup`, `tasks-workflow` и будущий
bulk-store — по одному полю в payload.

Для долгоживущих воркеров замена снапшота атомарна: собрать → провалидировать → опубликовать с
новой версией → воркеры берут новый снапшот на границе job'а, никогда не посреди него.

**Сверить с деревом:** `packages/modules/src/Compiler/**`, `Registry/CompiledModuleRegistry.php`,
`Commands/CompileModulesCommand.php`, `packages/api/src/OperationRegistry.php`,
`packages/search/src/SearchManager.php` (Laravel-manager как четвёртая форма реестра),
`docs/dev-doctrine.md` §4 (закрытый набор восьми extenders — расширению не подлежит).
