---
id: D57
date: 2026-09-29
status: accepted
item: P03
items: [P03, P03.1, P03.2, P04]
supersedes: [D05]
superseded_by: null
---
# D57 — P03 feature-module layout, map and gate (accepted)

**Actor:** author-choice (claude-opus-5-5/high, `task:plan-design … P03`) по мандату владельца B6 («есть идеальное решение — утверждай»); вариант выбран по фактам облачного скана.
**Evidence:** RAG:— облачный скан `artifacts/P03-design/cloud/` (`1350c1c`, воспроизведён байт в байт на `52446de`), `research/P03-design.md` §2 F1–F9; дизайн-прогоны `p03-module-map.py` (1168 файлов, 278 переносов, 68 остаются, 0 коллизий), `p03-transfer.py plan` (0 problems), `apply --no-rector` + `verify` в scratch-worktree (0 problems), `module-layout-gate.py` на перенесённом дереве = проекции (R3 68, R5 91). RAG:✅ `research/P03-design-rag.md` E1 (Pest arch не выражает раскладку → свой гейт), E2 (PHPStan `@internal` по первому сегменту).

## Solution

1. **Норма раскладки (заменяет D05).** Пакет `CoreX\<Pkg>\` — это его **корневой модуль**: в `src/` лежат публичные категории `Contracts|Data|Enums|Events|Exceptions`, мосты `Internal|Laravel|Testing|PHPStan`, provider(ы), `<Pkg>Module.php` и каталоги **feature modules** с тем же набором (категории и мосты ровно на уровень ниже модуля; provider модуля — прямо в модуле). Ядро `CoreX\` корневого модуля не имеет: только `CoreServiceProvider.php` и модули закрытого списка glossary. Слоевые каталоги (`Support`, `Models`, `Commands`, `Jobs`, `Concerns`, `Http`, …) на любом уровне модуля запрещены — их файлы уходят в категорию по роли или в `<Module>\Internal\<Bucket>\…` (имя корзины сохраняется группой внутри `Internal`). S1 (корзины single-feature пакетов остаются) отклонён — сохраняет корзины; S2 (всегда явный модуль) отклонён — `CoreX\Webhooks\Webhooks\…`, 664 переноса против 278.
2. **Карта** — `artifacts/P03/module-map.tsv` из `p03-module-map.py` (вход — облачный скан, `--check` — свежесть). Interface → `Contracts`, enum → `Enums`, исключение → `Exceptions`, data-класс → `Data`, остальная реализация → `Internal`; файлы в категориях и мостах, provider(ы) и `*Module.php` не двигаются; крупные модули (`Modules`, `Membership`, `Messaging`) в P03 не делятся.
3. **Остаются на месте** (68, `artifacts/P03/public-by-usage.tsv`) классы реализации, которые иначе ушли бы в `Internal`, если их называет другой пакет или продукт, опубликованный `config/*.php` своего пакета или glossary. Причина — граница `package-graph-gate` R2 (чужой `Internal` запрещён) и конфиг хоста; P04 классифицирует их `#[Api]/#[Spi]` (D06, D07) и сокращает список. Поправка к черновику: FQCN, видимые снаружи, **меняются** у 23 публичных типов, уходящих в категорию (`CoreX\Data\RecordRef` → `CoreX\Data\Data\RecordRef`, `CoreX\Search\Support\SearchQuery` → `CoreX\Search\Data\SearchQuery`, …) — это цель раскладки; D01 совместимость не требует, потребители в монорепо переписываются тем же переносом.
4. **Продукт** `products/flex-crm` не переносится (glossary N8: правила N1–N7, включая N5, к нему не применяются); гейт раскладки его не сканирует; ссылки продукта на перенесённые классы пакетов переписываются.
5. **Гейт** `scripts/module-layout-gate.py` (+ `scripts/module-layout.json`, `scripts/module-layout-baseline.json`), правила R0–R5 из докстринга (R0 namespace = путь, R1 корни и слоевые корзины, R2 категория только прямо под модулем, R3 файл в категории/мосте, R4 `Internal` не вложен, R5 ссылка в `Internal` другого модуля того же пакета кроме composition root: provider(ы), `*Module.php`, любой `Laravel`). Baseline — ratchet: новая запись или устаревшая (исправленная, но не удалённая) → exit 1; baseline только сокращается. P03.1 вводит гейт с baseline текущего дерева (436 записей); P03.2 после переноса оставляет 159 (R3 68 = остающиеся на месте, R5 91 = межмодульные ссылки в `Internal`, их сокращает P04 через контракты модулей). Граница «чужой пакет → `Internal`» остаётся за `package-graph-gate` R2.
6. **Перенос** — `artifacts/P03-design/scripts/p03-transfer.py` поверх проходов P02.3 (`relative_pass`, `literal_pass`, Rector `RenameClassRector` с `products/` в области, `p02-references.php`, `namespace_pass`, `move_pass`); манифесты не меняются. Сухой прогон с Rector и полным стеком в scratch-worktree обязателен до основного дерева; дефект → карта/скрипт → новый сухой прогон.
7. **Golden без rebaseline.** `tests/Golden/baseline/**` не меняется; `tests/Golden/rename-map.json` получает `manual`-правила `identifier` (имя из baseline → имя P03; `RenameMap` принимает `identifier` с `\` как класс, `Normalizer` выводит `internal` из спроецированного имени) — `p03-transfer.py golden`. `scripts/rename-completeness.json` дополняется классами и исчезнувшими namespace P03 (`manifest`); прежние P02-записи указывают на итоговое имя.
8. **Строки в БД и очереди.** Morph map строится из алиасов сущностей модулей; модели без алиаса и jobs хранят FQCN в `*_type`/payload очереди. По D01 миграции данных и алиасов нет; перенос моделей/jobs в `Internal` фиксируется в CHANGELOG пакетов («очистить очереди перед обновлением; существующие `*_type` со старым FQCN не резолвятся»).
9. **Ретайр `tenancy-structure-gate`.** `scripts/tenancy-structure-gate.py` (P9 прежнего плана: ratchet совместимости FQCN tenancy) красный на `main` уже после P02 и противоречит D01 и этой раскладке; P03.1 удаляет его, composer-скрипт и шаг CI, заменяя гейтом п.5. STR-001…STR-006 constitution, `structure-profiles.md` и исключения `exceptions/STR-003`, `exceptions/STR-005` переписываются под эту норму (строки `artifacts/P00/doctrine-delta.md`).

## Why

«Корень пакета — модуль» — наименьший перенос (278 против 420/664), без коллизий и без `X\X\…`, при этом корзины уровня пакета исчезают и граница public/internal читается по пути. Реализация, которую уже используют снаружи, не прячется в `Internal` до классификации P04 — иначе P03 создал бы нарушения R2 и сломал бы опубликованный конфиг. Свой гейт — потому что Pest arch/PHPStan не выражают «категория ровно под модулем» и «тот же модуль» (E1, E2).

## Consequences

P03.1 — гейт, baseline, ретайр гейта tenancy, STR-* docs; P03.2 — перенос, golden-правила, rename-completeness, сокращение baseline до 159. Glossary N5 ссылается на D57. P04 сокращает `public-by-usage` и R5-baseline. D05 → superseded.
