---
id: D46
date: 2026-09-28
status: accepted
item: P02
items: [P02, P02.3, P02.4]
supersedes: []
superseded_by: null
---
# D46 — P02 design audit A1–A4 remediation (accepted)

**Actor:** owner-authorized fixer/codex/gpt-6-sol/high; запрос владельца после аудита: «исправляй в этой сессии».
**Evidence:** RAG:— `findings/P02-design-audit.md`, исходный fingerprint `83f9a46c53c7e2b70d55bddf263bf3d179e66f728cbe93a1201ec9f9d9ffed4d`;
`artifacts/P02-design-audit/preimage/`; `artifacts/P02-design-remediation/{check.py,results.json}`.

## Решение

1. P02.4: пять config-ренеймов включают basename `.php` во всех provider/publish/doc ссылках.
   `p02-identifiers.py` и PHP rename-completeness распознают точные config-basename после `/`;
   похожие имена вне карты не переименовываются. Возвращённый старый путь при уже новом config-ключе — RED обоих гейтов.
2. P02.3: `manifests()` проецирует root `autoload`/`autoload-dev` `classmap/files` по file-moves, directory-renames
   и merge `packages/modules/tests → packages/core/tests/Modules`; `verify` проверяет существование root autoload-путей.
   Composer dev-autoload должен проходить после удаления старых каталогов.
3. P02.4 Required Reads №7 — существующая `extra-moves.tsv`; обязательное execution-time чтение её destination `packages/core/config/corex-modules.php` объявлено в Implementation Rules. Файл создаёт P02.3; отсутствие результата блокирует P02.4. Design bundle разрешается до исполнения prerequisite без старой ссылки и без фиктивного файла.
4. P02.3 проверяет неизменность только `tests/Golden/baseline/` и `tests/Golden/rename-map.json`.
   Код Golden получает обязательные FQCN из карты. Baseline/map-дрейф остаётся RED; `composer golden` 4 passed сохраняется.

## Почему

Это уточняет реализацию D44/D45 и устраняет четыре воспроизведённых дефекта готовности.
Golden-защита не ослабляется: запрещённые к изменению данные остаются frozen,
а blanket-проверка кода сьюта противоречила обязательному namespace-переносу.
Инструменты, spec, dossier и examples изменяются согласованно; продукт и topology Routing не меняются.

## Consequences

Дизайн проходит повторный `finish`; GREEN нужен от независимого аудитора нового fingerprint.
P02.1–P02.4 не исполняются в этой remediation; O-3 сохраняется.
Ремонт выполнен текущей implementation/high-сессией по прямому запросу владельца,
рекомендованный frontier/high не выдаётся за фактический маршрут. Внешних решений не добавлено;
свежий E1/captures исходного дизайна переиспользуется.
