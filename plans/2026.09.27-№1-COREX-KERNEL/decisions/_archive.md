<!-- plan-projection
projection: decision-archive
projection_version: v1
source_scope: decisions/
through: 2026-09-29/D54
inputs_sha256: 0217868a21f462ac0e3a80329e88c85b6fc53e747dc553962106cf3a9dea6c20
generated_by: task plan-views decisions
-->

# Архив решений

> Не править руками: это генерируемый вид.

| ID | Date | Status | Items | Решение |
|:--|:--|:--|:--|:--|
| D38 | 2026-09-28 | superseded | P01.2, P02, P03, P04, P05, P06, P07, P08 | 1. Golden — Pest-сьют `Golden` в корне: `tests/Golden/baseline/*.json` (замороженный снимок P01) против снимка текущего кода. Слои: `php-surface` (Reflection публичных типов `packages/*/src`), `routes`, `events` (`DomainEvent::name()` + зарегистрированные message contracts), `wire` (вывод `corex:api-snapshots` во временный каталог: operations/errors/openapi/webhooks). 2. Сравнение: `normalize(apply(rename-map, baseline)) == normalize(current)`. `tests/Golden/rename-map.json` — правила **точных токенов** (не префиксов: `namespace-map.tsv` ключуется путём, psr-4 folding): `generated` = `class` (старый FQCN → конечный FQCN из `check-maps.py::symbol_table`, 666 правил) + `package` (16 однозначных composer-имён); `ambiguous_packages` — `corex/core`, `corex/modules`, `corex/operations` (split по путям); `manual` — правила, которые добавляет фаза переименования (`identifier` для config/tags/команд/таблиц и `field` для полей wire с неоднозначным владельцем). Генератор — plan-owned `artifacts/P01-design/scripts/golden-rename-map.py` (сохраняет `manual`, `--check`). 3. Baseline не перезаписывается в P02/P03. Перезапись (`GOLDEN_UPDATE=1`) допустима только при намеренном изменении поверхности (P04+) и только вместе со строкой в `tests/Golden/REBASELINE.md` со ссылкой на D#; anti-weakening gate проверяет это сочетание. 4. Нормализатор убирает недетерминизм: сортировка ключей и списков по стабильному ключу, отбрасывание времени/UUID/путей абсолютных, `contract_fingerprint`/`registry_fingerprint`/хэши файлов пересчитываются после rename (не сравниваются как строки). |
