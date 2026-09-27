# P00 — Досье дизайна (frontier)

Статус: design-time факты и рекомендации для P00.1/P00.2. Frontier-формат (варианты, критерии, рекомендация, acceptance);
runbook не требуется. Факты — `RAG:—`, воспроизводятся командами из корня репозитория (`ea0b024`).

## 1 Факты

| Факт | Значение | Команда / файл |
|:--|:--|:--|
| Пакеты | 20 composer-пакетов + тумбстоун `import-export` | `ls packages/*/composer.json` |
| Межпакетные импорты, декларации public-api, коллизии | см. файл | `artifacts/P00-design/facts/import-matrix.txt` |
| Потребители зон ядра (вне `packages/core`) | см. файл | `python3 artifacts/P00-design/scripts/zone-consumers.py` → `facts/zone-consumers.tsv` |
| Потребители `corex/modules` | 12 пакетов объявляют require: access, attributes, crm, intl, media, notifications, operations, panels, tasks, tasks-workflow, wakeup, workflow; не требуют: recurrence, search, search-intl, search-meilisearch, tenancy, transfer | `grep -l '"corex/modules"' packages/*/composer.json` |
| Зависимости `corex/modules` | только `corex/core` + illuminate | `packages/modules/composer.json` |
| Tenancy-зона ядра | 18 пакетов-потребителей, 412 импортов: `AccountRef`, `WorkspaceRef`, `TenantContext`, `ImpersonationGuard/State`, `Null*`, `ProvisionParams`, `SingleAccountResolver`, `DatabaseConnectionConfig`, `Contracts\{AccountConnectionResolver, ImpersonationService, TenancyManager, TenantContextResolver, TenantContextRestorer, TenantDatabaseLifecycle, TenantDatabaseProvisioner}` | `ls packages/core/src/Tenancy{,/Contracts}` |
| SavedViews | 1 потребитель (panels, 7 импортов) | zone-consumers |
| Money | 2 потребителя (crm, modules — по 1 импорту `MoneyCast`); ядро требует `brick/money` только ради него; `corex/intl` уже требует `brick/money` и владеет Currency/Money-форматированием | zone-consumers, `packages/core/composer.json`, `packages/intl/composer.json` |
| Departments | 0 внешних потребителей в `src`, но вплетён в ядро: `Settings` (scope отдела), `Features` (overrides), `Enums\SettingScope`, `Contracts\SettingsScope`; `access/ScopePredicate` | `grep -rln Department packages/*/src` |
| Features | 1 внешний потребитель (modules) | zone-consumers |
| Корневые символы `CoreX\*` | только `CoreServiceProvider` (13 импортов — тесты/провайдеры) | grep |

## 2 Развилки P00.1 и рекомендации

| # | Развилка | Варианты | Критерий | Рекомендация |
|:--|:--|:--|:--|:--|
| F1 | `corex/modules` | A: отдельный пакет · B: модуль ядра `CoreX\Modules` | независимый потребитель; определяющая ответственность L0 | **B**: 12/19 пакетов требуют пару core+modules; modules без core не существует; ядро без модулей — не платформа (Bitrix `main`) |
| F2 | Tenancy-зона ядра | A: `CoreX\Tenancy` (коллизия) · B: `CoreX\Context` · C: `CoreX\Accounts` | FQCN → один пакет; имя = смысл (контекст исполнения: account, workspace, актор + SPI lifecycle, который реализует tenancy-пакет) | **B** `CoreX\Context` (`Context\Data\{AccountRef,WorkspaceRef,TenantContext}`, `Context\Contracts\*`); SPI lifecycle (`TenancyManager`, `TenantDatabase*`, `AccountConnectionResolver`) — `CoreX\Context\Contracts`, null-реализации — `Context\Internal` |
| F3 | Money | A: оставить в ядре · B: в `corex-intl` | ядро минимально по зависимостям; владелец денежной семантики | **B**: ядро теряет `brick/money`; `MoneyCast` → `corex-intl`. Единственное использование в будущем ядре — `packages/modules/src/Compiler/RegistryCompiler.php:22,308` жёстко распознаёт `MoneyCast::class` как тип поля `money` (ядро знает о конкретном cast — нарушение принципа §1.1). Замена: kernel SPI сопоставления cast → тип поля (реестр модулей), intl регистрирует `MoneyCast → money`; реализация — P04/P05 (до неё P02 переносит cast в intl и оставляет в компиляторе строковое сопоставление по FQCN из реестра). Записать отдельным D# |
| F4 | SavedViews | A: ядро · B: `corex-panels` | единственный потребитель; UI-состояние | **B** |
| F5 | Departments | A: ядро · B: отдельный пакет | вплетён в scope-иерархию Settings/Features | **A** (модуль ядра `CoreX\Departments`); вынос создал бы ребро ядро→пакет |
| F6 | Features, Settings, Audit, Storage, Messaging, Observability, Data, Time, Errors, Config | A: ядро · B: пакеты | платформенные сервисы, нужные каждому приложению; механизмы надёжности | **A** — модули ядра |
| F7 | `corex/attributes` (EAV) | `custom-fields` · `fields` · оставить | освободить `CoreX\Attributes` для `#[Api]/#[Spi]`; имя = назначение | **`corex-custom-fields`**, `CoreX\CustomFields` |
| F8 | `corex/wakeup` | `timers` · `wakeup` · `wakeups` | назначение: durable tenant-local wakeup requests | **оставить `wakeup`** (`CoreX\Wakeup`): термин точный, «timers» шире смысла |
| F9 | Split operations | 2/3/4 пакета | независимый потребитель | 3: `corex-operations` (`CoreX\Operations`: Definition, Invocation, Idempotency, Versioning, Validation, Authorization, Pagination, Errors, Http, OpenApi, Bulk, Cost, Laravel), `corex-tools` (`CoreX\Tools`: Tools/*), `corex-webhooks` (`CoreX\Webhooks`: Webhooks/*); проверить при исполнении связность `Tools`/`Webhooks` → engine (импорты) и записать в `package-map.tsv` направление рёбер |
| F10 | access | `CoreX\Access` | имя пакета = namespace | `corex-access`, `CoreX\Access`; AzGuard-часть — P07 (D14) |
| F11 | search-meilisearch | `CoreX\SearchMeilisearch` | один корень на пакет (D04) | да |
| F12 | Атрибуты API | `CoreX\Attributes\{Api,Spi}` | идентично chatom `Chatom\Attributes` | да (после F7) |

## 3 Правила имён (для glossary §Naming rules)

- composer: `axiomasoft/corex` (ядро), `axiomasoft/corex-<kebab>`; module id = composer-имя.
- namespace: `CoreX\<Pascal>` без дефисов; ядро — `CoreX\<KernelModule>` из закрытого списка.
- config: `corex` (ядро), `corex-<kebab>` (пакет); env: `COREX_<UPPER_SNAKE>_*`; artisan: `corex:<kebab>:<verb>`;
  publish-tags: `corex-<kebab>-{config,migrations}`; таблицы: префикс по `table-ownership.md` (сверить в P08).

## 4 Acceptance P00.1

`check-maps.py` exit 0; каждая строка §2 имеет вердикт в glossary с одной фразой обоснования; D24+ записаны для F1–F12
(accepted, author-choice), либо open-question с owner-gate.

## 5 Схемы карт и алгоритм `check-maps.py` (P00.1, P00.2)

**Факт (RAG:—, `packages/*/composer.json`):** PSR-4 корни не совпадают с путями (psr-4 folding). `corex/operations`
объявляет `CoreX\Api\Contracts\` → 10 каталогов (`src/Definition/Contracts/` … `src/Tools/Contracts/`, `src/Webhooks/Contracts/`);
при split (F9) каталоги одного префикса уходят в разные пакеты. `corex/access` = `CoreX\Auth\`, `corex/search-meilisearch` =
`CoreX\Search\Meilisearch\`, `corex/core` = `CoreX\` (зоны — подкаталоги `src/`). Поэтому **ключ карты — каталог, не префикс**.

`artifacts/P00/package-map.tsv` — одна строка на каталог `packages/*` (21, включая `import-export`, `crm`):
`old_dir  old_name  new_dir  new_name  new_root  type  phase` · `type ∈ {kernel, package, bridge, product, tombstone-remove}` ·
для `modules`: `new_dir=packages/core`, `new_name=axiomasoft/corex`, `type=kernel` (merge, F1) · для `crm`: `new_dir=products/flex-crm`
(D15) · для `import-export`: `new_dir=—`, `type=tombstone-remove` · split: `operations` даёт три строки с одним `old_dir`
и разными `new_dir` (единственное допустимое повторение `old_dir`, помечается `type` и `note` в glossary).

`artifacts/P00/namespace-map.tsv` — одна строка на **исходный каталог**:
`old_package  old_path  old_prefix  new_package  new_prefix  phase  note`, где `old_path` — путь от корня репозитория
с `/` на конце (`packages/operations/src/Tools/`) либо путь к файлу, когда каталог делится между владельцами
(плоский `packages/core/src/Contracts/`, зоны — `CONTRACT_ZONE` в `zone-consumers.py`), `old_prefix` — PSR-4 префикс, которому каталог принадлежит сейчас,
`new_prefix` — FQCN-префикс каталога после P02/P03 (namespace = путь, D04).

Алгоритм `check-maps.py` (stdlib, запуск из корня репозитория; exit 1 с перечнем нарушений, иначе `maps OK: <N> prefixes, <M> packages`):
1. Источник истины — `autoload.psr-4` и `autoload-dev.psr-4` всех `packages/*/composer.json` (корневой `composer.json`
   дублирует их — не источник). Строка из `namespace-map.tsv` с `old_prefix`, которого нет в psr-4 пакета `old_package`, — ошибка.
2. Каждый `*.php` под `packages/*/{src,tests,database}` сопоставляется строке с **самым длинным** `old_path`, являющимся
   префиксом пути файла. Файл без строки — ошибка `uncovered`; строка без файлов — ошибка `dead-row`; две строки с
   одинаковым `old_path` — ошибка `duplicate`.
3. Корень владения: `seg(new_prefix)` = первые два сегмента (`CoreX\Context`). Для каждого `seg` множество `new_package`
   — ровно один элемент (иначе `root-collision`). Ядро (`axiomasoft/corex`) владеет только сегментами из §Kernel modules
   glossary; пакет — только своим корнем `CoreX\<Pascal>` (иначе `foreign-root`).
4. Каждый `new_package` из namespace-map есть в `package-map.tsv.new_name`; каждый `new_name` (кроме `tombstone-remove`)
   владеет ≥1 строкой namespace-map.
5. `artifacts/P00/zone-consumers.tsv` — колонки 1–4 совпадают с выводом `zone-consumers.py` (вызвать как модуль или
   subprocess), колонка 5 `verdict ∈ {keep, move→<new_name>}`; каждая зона имеет вердикт.
6. `--doctrine`: множество ID `^### ([A-Z]+-[0-9]+)` из `docs/architecture/constitution.md` (сейчас 58) равно множеству
   ID в колонке 2 таблицы `artifacts/P00/doctrine-delta.md`, каждый ID ровно один раз; `action ∈ {keep, rewrite, remove, add}`;
   для `rewrite|add` колонки `phase` (`P01`…`P09`) и `gate` непусты. Строки `add` без ID constitution используют `NEW-<n>`.

Негативный контроль (обязателен, выполняется исполнителем, результат — в Completion Notes): временно удалить одну строку
namespace-map → `uncovered`, exit 1; продублировать `seg` на второй пакет → `root-collision`, exit 1; вернуть → exit 0.

## 6 Failure playbook

| Симптом | Действие |
|:--|:--|
| Каталог не ложится ни в один пакет по критерию pplx-02 | вердикт в glossary + D# (accepted, если критерий однозначен; иначе `open-questions.md` + D# proposed) |
| Зона ядра с 2 потребителями и без статуса механизма надёжности (Errors, Events, Identity, Settings…) | применить правило F6 (платформенный сервис → keep) и записать фразу-обоснование; не выносить без D# |
| `root-collision` после F2/F7 | вернуться к F2/F7: коллизия означает неосвобождённый корень (`CoreX\Tenancy`, `CoreX\Attributes`) |
| `zone-consumers.py --check` → exit 1 | код изменился после дизайна: перегенерировать `facts/zone-consumers.tsv`, отметить дельту в Completion Notes |
| Решение требует внешнего/необратимого действия | стоп, `open-questions.md`, owner-gate |

## 7 Self-check (design)

- Факты, которые исполнитель ищет сам: вердикты по 21 зоне (`zone-consumers.tsv`) и 21 каталогу — вывод из §1/§2 по критериям,
  не поиск; строки namespace-map — перечисление psr-4 + подкаталогов `packages/core/src/*` (команда §5 п.2 проверяет полноту).
- Решения, оставшиеся исполнителю: frontier-вердикты по F1–F12 в пределах рекомендаций §2 (отклонение → D# с обоснованием).
- Доказательство: `check-maps.py` (exit 0 + негативный контроль), `check-maps.py --doctrine`, `zone-consumers.py --check`, ревью.
