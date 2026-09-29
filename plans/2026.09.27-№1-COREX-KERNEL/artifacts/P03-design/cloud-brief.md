# P03 — задание облачной сессии: факты кода для дизайна feature modules

Ты собираешь **факты**, не проектируешь и не меняешь продукт. Дизайн P03 (решения, items, D#) делает автор плана
по твоему выходу. MCP, Perplexity и пакет Task в облаке недоступны и не нужны: только `git`, `python3` (stdlib).

## Контекст (прочитай только это)

1. `plans/2026.09.27-№1-COREX-KERNEL/backlog/P03.md` — скелет фазы.
2. `plans/2026.09.27-№1-COREX-KERNEL/decisions/D05-layout.md` — норма раскладки.
3. `plans/2026.09.27-№1-COREX-KERNEL/research/03-glossary.md` — §Naming rules (N4, N5), §Packages, §Kernel modules, §Terms.
4. `plans/2026.09.27-№1-COREX-KERNEL/research/P03-design-rag.md` — почему гейт раскладки — свой скрипт.
5. `scripts/package-graph-gate.php` (правило R2 «чужой Internal») и `scripts/tenancy-structure-gate.py` (образец гейта раскладки) — только чтобы не дублировать.

Норма D05 кратко: пакет `CoreX\<Pkg>\` (ядро `CoreX\` с модулями из glossary) состоит из feature modules
`<Module>\{Contracts,Data,Enums,Events,Exceptions}` (публичные категории — ровно на уровень ниже модуля) + `<Module>\Internal\…`
(реализация, Eloquent-модели, SQL, jobs, контроллеры); Laravel-мост пакета — `Laravel\…`; в корне `src/` — только provider и
модули. Слоевые «корзины» уровня пакета (`<Pkg>\Contracts`, `<Pkg>\Data`, `<Pkg>\Internal` на весь пакет) запрещены.

## Область

Все PHP-файлы `packages/*/src/**` и `products/flex-crm/src/**` (продукт — отдельной строкой, N8). Тесты и `database/`
только в пункте 5. Ничего не редактировать вне каталога выхода.

## Что сделать

Напиши `plans/2026.09.27-№1-COREX-KERNEL/artifacts/P03-design/cloud/scan.py` (stdlib, запуск из корня репо, детерминированный
вывод, сортировка) и сгенерируй им файлы ниже. Namespace и символы бери из файла (`namespace …;`, `class|interface|trait|enum`),
ссылки — из `use`-импортов, FQCN в коде и строковых литералах (`'CoreX\\…'`, `CoreX\X::class`); комментарии игнорируй.

1. `inventory.tsv` — строка на файл: `package` (composer name из `composer.json` пакета), `path`, `namespace`, `symbol`,
   `kind` (class/interface/trait/enum/none), `modifiers` (final/abstract/readonly), `role` — эвристика: `provider`
   (extends ServiceProvider), `command` (extends Command), `model` (extends Model/BaseModel/Pivot), `job` (implements ShouldQueue),
   `exception` (extends Throwable-класс), `data` (final readonly без методов кроме конструктора/геттеров), `contract`
   (interface), `enum`, `other`; `top` — первый каталог под `src/` (или `<root>` для файла в корне); `depth`.
2. `package-shapes.md` — по каждому пакету таблица верхнего уровня `src/`: запись → класс {`module` — каталог с собственными
   категориями/реализацией одной фичи; `bucket` — категория/слой на уровне пакета (`Contracts`, `Data`, `Enums`, `Events`,
   `Exceptions`, `Internal`, `Support`, `Models`, `Commands`, `Jobs`, `Concerns`, `Http`); `laravel`; `testing`; `root-file`}
   + число файлов. Итог: какие пакеты уже модульные, какие — «одна фича в корзинах».
3. `intra-refs.tsv` — ссылки между верхними записями `src/` **внутри** пакета: `package`, `from_top`, `to_top`, `to_is_internal`
   (цель под `…\Internal\`), `count`, пример `from_file → to_symbol`. И `intra-refs.md` — матрица на пакет + список всех
   ссылок в чужой (другой верхней записи) `Internal`.
4. `consumers.tsv` — ссылки **из других пакетов/продукта/монорепо `tests/`** на символы пакета: `target_package`, `symbol`,
   `consumer_package`, `count`. Это публичная поверхность, которую затронет перенос.
5. `strings-risk.tsv` — FQCN пакетов в строках и не-PHP файлах (`config/`, `database/`, `routes/`, `resources/`, JSON, neon,
   `tests/`): `file`, `line`, `fqcn`, `context` (morph map, config class, container binding, queue/job, другое). Отдельно отметь
   `Relation::morphMap`/`enforceMorphMap` и места, где тип модели может храниться в БД.
6. `violations-now.md` — что покажет гейт D05 на текущем дереве, по правилам (считай, не чини):
   - **L1** в корне `src/` только provider(ы), `<Pkg>Module.php`, каталоги модулей, `Laravel/`, `Testing/`;
   - **L2** публичные категории (`Contracts|Data|Enums|Events|Exceptions`) только непосредственно под модулем (не на уровне пакета, не глубже);
   - **L3** класс с ролью `model|job|command|other` (не interface/enum/data/exception) вне `…\Internal\` и `…\Laravel\`;
   - **L4** `Internal` не глубже уровня модуля (`<Module>\Internal\…`, не `<Module>\X\Internal`);
   - **L5** ссылка в `Internal` другого модуля того же пакета.
   Итог: таблица пакет × L1–L5 (число нарушений) + до 10 примеров на правило.
7. `module-proposal.tsv` — **два варианта** для каждого файла, без выбора:
   - `S1` «пакет из одной фичи = один неявный модуль»: для пакетов, где `package-shapes` = одна фича в корзинах, корзины
     остаются на уровне пакета (`CoreX\Transfer\Contracts`), остальные пакеты — по модулям;
   - `S2` «всегда явный модуль»: каждая корзина уходит в модуль с именем фичи (`CoreX\Transfer\<Feature>\Contracts`),
     имя фичи — по кластеру ссылок `intra-refs`/имени пакета; отметь `confidence` (high/medium/low) и почему.
   Колонки: `package`, `path`, `current_ns`, `s1_path`, `s1_ns`, `s2_path`, `s2_ns`, `s2_module`, `confidence`, `note`.
   Итог в `README.md`: число перемещаемых файлов и классов, меняющих FQCN, при S1 и при S2, по пакетам.
8. `README.md` — команда воспроизведения (`python3 …/cloud/scan.py`), сводные числа по пунктам 1–7, список неясностей
   (что эвристика не смогла классифицировать — пофайлово) и вопросы к дизайну (без ответов).

## Проверки перед сдачей

- `python3 plans/2026.09.27-№1-COREX-KERNEL/artifacts/P03-design/cloud/scan.py` дважды подряд → `git status` показывает
  изменения только в каталоге `cloud/`, повторный запуск не меняет файлы (детерминизм).
- Каждый PHP-файл области ровно в одной строке `inventory.tsv` (сверка с `git ls-files 'packages/*/src/*.php' 'products/*/src/*.php' | wc -l`).
- `git diff --stat origin/main` — только `plans/2026.09.27-№1-COREX-KERNEL/artifacts/P03-design/cloud/**`.

## Сдача

Ветка `design/p03-cloud-facts` от актуального `main`; один коммит `docs(plan): P03 code facts for feature-module design`;
`git push -u origin design/p03-cloud-facts`. Ответ: SHA, 10–15 строк сводки (числа из `README.md`), неясности.
Не открывай PR, не сливай в `main`, не трогай `plans/` вне `cloud/`, не меняй код продукта.
