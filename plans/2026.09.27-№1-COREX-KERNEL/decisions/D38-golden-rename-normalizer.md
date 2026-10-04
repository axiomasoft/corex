---
id: D38
date: 2026-09-28
status: superseded
item: P01.2
items: [P01.2, P02, P03, P04, P05, P06, P07, P08]
supersedes: []
superseded_by: D40
---
# D38 — golden-rename-normalizer (accepted)

**Actor:** author-choice (claude-opus-5-5/high, Claude Code, `task:plan-design … P01`, 2026-09-28) по мандату B6.
**Evidence:** RAG:— `artifacts/P01-design/facts/README.md` F6 (`api/v1` + `check-api-contract.php` — intra-major гейт с committed baseline; генератор `corex:api-snapshots`); `artifacts/P00/{package-map,namespace-map}.tsv` (558 строк переносов P02/P03); plan.md §2 «страховочная сетка P01 зелёная перед P02 и после каждого item».

## Solution

1. Golden — Pest-сьют `Golden` в корне: `tests/Golden/baseline/*.json` (замороженный снимок P01) против снимка текущего кода. Слои: `php-surface` (Reflection публичных типов `packages/*/src`), `routes`, `events` (`DomainEvent::name()` + зарегистрированные message contracts), `wire` (вывод `corex:api-snapshots` во временный каталог: operations/errors/openapi/webhooks).
2. Сравнение: `normalize(apply(rename-map, baseline)) == normalize(current)`. `tests/Golden/rename-map.json` — правила **точных токенов** (не префиксов: `namespace-map.tsv` ключуется путём, psr-4 folding): `generated` = `class` (старый FQCN → конечный FQCN из `check-maps.py::symbol_table`, 666 правил) + `package` (16 однозначных composer-имён); `ambiguous_packages` — `corex/core`, `corex/modules`, `corex/operations` (split по путям); `manual` — правила, которые добавляет фаза переименования (`identifier` для config/tags/команд/таблиц и `field` для полей wire с неоднозначным владельцем). Генератор — plan-owned `artifacts/P01-design/scripts/golden-rename-map.py` (сохраняет `manual`, `--check`).
3. Baseline не перезаписывается в P02/P03. Перезапись (`GOLDEN_UPDATE=1`) допустима только при намеренном изменении поверхности (P04+) и только вместе со строкой в `tests/Golden/REBASELINE.md` со ссылкой на D#; anti-weakening gate проверяет это сочетание.
4. Нормализатор убирает недетерминизм: сортировка ключей и списков по стабильному ключу, отбрасывание времени/UUID/путей абсолютных, `contract_fingerprint`/`registry_fingerprint`/хэши файлов пересчитываются после rename (не сравниваются как строки).

## Why

- Механические фазы обязаны доказать «переименовано, а не изменено»: сравнение с картой отличает легальный перенос от случайного изменения поверхности.
- Существующий `api/v1`-гейт сравнивает с committed baseline, который P02 обязан обновить (владельцы/имена) — сам по себе не отличает перенос от поломки.
- Отклонено: обновлять golden при каждом переносе (golden перестаёт что-либо доказывать); хранить карты только в каталоге плана (тесты не должны зависеть от временных plan-артефактов).

## Consequences

P01.2 создаёт сьют и карту; P02/P03 расширяют `rename-map.json`; P04+ перебазируют через D# и `REBASELINE.md`.
