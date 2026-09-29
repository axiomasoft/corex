# P02 — design audit A1–A4 remediation

Дата: 2026-09-28. Автор: fixer/codex/gpt-6-sol/high, по запросу владельца «исправляй в этой сессии».
Scope: дизайн P02; решение [D46](../decisions/D46-p02-design-audit-remediation.md).

| Finding | Исправление | Проверка |
|:--|:--|:--|
| A1 | Config basename `.php` заменяется и обнаруживается обоими identifier-гейтами после `/`. | Настоящий Laravel ServiceProvider загружает пять переименованных configs. Возврат старого пути даёт exit 1 обоих гейтов; похожие имена сохраняются. |
| A2 | Root autoload/autoload-dev classmap/files проецируются по переносам, включая Modules tests; verify проверяет существование путей. | Composer dump-autoload --dev проходит на новой карте и падает на старой; verify обнаруживает старый каталог. |
| A3 | Required Read №7 содержит карту переноса; Implementation Rules требуют читать её destination packages/core/config/corex-modules.php после P02.3. | Design bundle собирается до prerequisite; файл доступен после реального git mv в fixture; старый путь отсутствует. |
| A4 | Frozen-проверка ограничена Golden baseline и rename-map; обязательные правки кода сьюта разрешены. | Правка кода проходит; правка baseline или rename-map даёт exit 1; откат возвращает exit 0. |

Согласованы scripts, item specs, dossier, examples, phase и roadmap. Проверки:
[check.py](../artifacts/P02-design-remediation/check.py),
[results.json](../artifacts/P02-design-remediation/results.json).
Все fixtures изолированы во временных git-каталогах; исходники продукта используются только для чтения.
Текущий дорефакторинговый продукт ожидаемо имеет 353 старых identifier-occurrences:
[identifier-baseline.log](../artifacts/P02-design-remediation/identifier-baseline.log).

Исходный [аудит ATTENTION](P02-design-audit.md) сохранён. Его воспроизведения используют
[preimage](../artifacts/P02-design-audit/preimage/), а повторная проверка исходного RED записана в
[original-reproductions.json](../artifacts/P02-design-remediation/original-reproductions.json).

Это авторская проверка исправлений, не независимый GREEN. После повторного finish требуется
task:plan-audit 2026.09.27-№1-COREX-KERNEL design другим аудитором; рекомендован frontier/xhigh.
P02 не исполнялся. Полный transfer dry-run и product/package gates остаются в execution runbook.
O-3 сохраняется: hosted CI P01 GREEN либо отдельное решение владельца до старта P02.1.
