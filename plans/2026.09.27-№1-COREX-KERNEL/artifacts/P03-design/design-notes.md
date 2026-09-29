# P03 — черновые выводы автора дизайна (не норма; оформить в D57 при дизайне)

Состояние на 2026-09-29, сессия автора плана прервана для продолжения в новой. Факты — `cloud/` (скан `1350c1c`,
воспроизводится байт в байт: `python3 …/cloud/scan.py` без диффа). Прототип карты — `scripts/p03-module-map.py` →
`artifacts/P03/{module-map.tsv,public-by-usage.tsv}`.

## Ответы на вопросы облака (предлагаемые решения)

1. **S1/S2 → третий вариант «корень пакета = модуль»** (общий для всех пакетов, не только single-feature): корень `src/`
   пакета — его основной модуль (категории `Contracts|Data|Enums|Events|Exceptions`, `Internal/`, `Laravel/`, `Testing/`,
   `PHPStan/`, provider(ы), `<Pkg>Module.php`); дополнительные feature-модули — подкаталоги с тем же набором. Ядро
   (`CoreX\`) — только модули закрытого списка glossary + `CoreServiceProvider`. Избегает `Webhooks\Webhooks\…` (S2) и
   сохраняет запрет «корзин», охватывающих несколько фич (D05). Прототип: 1168 файлов, **325 переносов**, 0 коллизий
   (S1 облака — 420, S2 — 664).
2. **Публичные по использованию** (класс-реализация, которую уже называет другой пакет или продукт; тесты монорепо не
   считаются): 57 символов **не переносятся** в P03 — ни один FQCN, видимый другим пакетам, не меняется (`Config`,
   `SecretRef`, `DataScope`, `Context\*`, `BaseModel`, `Department`, модели tasks для tasks-workflow…). Список
   `public-by-usage.tsv` — ratchet-исключение гейта L3; P04 заменяет его атрибутами `#[Api]/#[Spi]`/DTO (D06/D07).
3. **L3 в `Events`/`Data`:** классы в публичной категории — законны по каталогу (события, value objects с логикой); L3
   применяется только к реализации вне категорий/`Internal`/`Laravel`/`Testing`/`PHPStan`.
4. **Команды, jobs, контроллеры, модели без внешних потребителей** → `<Module>\Internal\…` (D05 допускает Internal).
   Корзина уровня пакета растворяется в корневой модуль, её имя сохраняется группой: `Http/X` → `Internal/Http/X`.
5. **Крупные модули** (`Modules` 105, `Membership` 55, `Messaging` 44) в P03 не делятся; подкаталоги вне категорий →
   `Internal/<Sub>/…`.
6. **L5** (ссылка в `Internal` другого модуля того же пакета; 162 сейчас): не блокирует P03 — ratchet-baseline,
   сокращается в P04 через контракты модулей. Ссылки из корня/`Laravel`/provider (composition root) не считаются.
   Граница «чужой пакет → `Internal`» остаётся за `package-graph-gate` R2 (блокирующая).
7. **Строки в БД:** колонки `*_type` хранят алиасы/значения enum, не FQCN (ложные срабатывания `db-type-column`);
   morph map строится из алиасов модулей (`ModulesBootRegistrar`). Проверить в дизайне: `Relation::morphMap` покрывает все
   модели с `morphTo` (crm `ActivityTarget`), иначе стоп.
8. **Инструмент переноса:** переиспользовать функции `p02-transfer.py` (`relative_pass`, `literal_pass`, `rector`,
   `p02-references.php`, `namespace_pass`, `move_pass`) с планом, собранным из `module-map.tsv` (packages/extra/dir_renames
   пустые; манифесты не меняются — корни PSR-4 те же). Golden: правила переименования классов — `manual` в
   `tests/Golden/rename-map.json` (RenameMap поддерживает `identifier` с `\`) → golden без rebaseline.
9. **Гейт раскладки** — свой скрипт (E1), правила L1–L5 из `cloud-brief.md` с поправками п.1–6; в CI; негативные контроли.

## Открыто для дизайна

- Проверить прототип карты выборочно (правило `keep` у 2 строк — перенос из корзины с `Internal` внутри) и L1 для
  root-файлов вроде `IntlConfigSection.php`, `RecordContext.php`, `RRuleRecurrenceExpander.php` (публичный по D30).
- Items (предложение): P03.1 карта + гейт раскладки (report/ratchet) + инструмент и сухой прогон; P03.2 перенос + golden
  без rebaseline + гейт strict + docs/constitution STR-*.
