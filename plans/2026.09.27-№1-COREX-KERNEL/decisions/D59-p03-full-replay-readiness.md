---
id: D59
date: 2026-09-29
status: accepted
item: P03
items: [P03, P03.1, P03.2]
supersedes: []
superseded_by: null
---
# D59 — P03: полный replay и исправление readiness в одной сессии

**Actor:** owner-authorized auditor/designer, codex/gpt-6-astra/xhigh. Прямой мандат владельца
после вызова design audit: «после этого должны уже железно приступить к выполнению, поэтому фикси
прямо в этой сессии». Это разрешает текущему root исправить дизайн, finish и получить независимую
проверку исправлений без возврата к автору по D56. Product execution остаётся отдельным шагом P03.1 → P03.2.
**Evidence:** `audits/2026-09-29-p03-design-recheck/` (от корня репозитория): исходный полный Rector replay,
минимальные воспроизведения, исправленные проверки; итог — `audits/2026-09-29-p03-design-recheck.md`.
RAG:—; новые внешние решения/зависимости отсутствуют, используются уже установленные PhpParser/Rector.

## Solution

1. После namespace/move проходов `p03-transfer.py apply` переиспользует P02 `p02-imports.php`:
   карта содержит все исходные символы и пути, а не только переносимые. Это сохраняет короткие
   ссылки на остающихся на месте соседей (12 imports в полном replay). Без этого Golden падает
   при загрузке `CompiledModuleRegistry`. Регрессионная фикстура доказывает RED до прохода / GREEN после.
2. Package-relative `path:` в PHPStan NEON проецируется по той же карте. Сообщения, count,
   ignore rules и ceilings не меняются; regenerating baseline запрещено. Восемь файлов в replay.
3. `p03-fixtures.py` проецирует короткие пути CORE_ZONE_DUMP/FOUNDATION_SUPPORT и `packagePath()`
   архитектурных тестов. Пять уже существующих cross-zone imports, чьи цели впервые попадают
   в Internal, сохраняются точными парами source/FQCN, полученными из HEAD и карты. R-Z0/R-Z1/R-Z2,
   запрет новых связей и все отрицательные контроли остаются; D57 R5 ratchet продолжает действовать.
   Исправляется скрытый остаток P02: `packagePath('attributes', …)` → `custom-fields` в
   `AchievedInvariantTest.php`. Удаляется только ссылка на пустой, отсутствующий в Git каталог
   `packages/core/tests/Views` в root `phpunit.xml`; ни один тест не удаляется.
4. После Pint обязательна команда `adapters`. Она проверяет SHA исходного adapter-файла по HEAD,
   сравнивает нормализованный AST до/после через NameResolver и карту (порядок/операции/литералы
   сохраняются), затем обновляет пять хэшей `tests/fixtures/public-error-contract.php`.
   Изменение возвращаемого значения отклоняется до записи fixture. Никакой blind rebaseline.
   Golden map проверяется `p03-transfer.py golden-check`: все preimage fields неизменны, manual содержит ровно
   376 требуемых добавлений. P01 generator уже stale на исходном HEAD после P02 (695 сохранённых class/package
   rules против 16 реконструируемых); регенерация удалила бы frozen правила. JSON пишется канонически.
5. Полный scratch setup включает собственный root vendor и 21 package harness. Для локального
   replay допустима копия установленных package vendors из исходного checkout без hardlinks,
   только с symlinks внутри scratch и обязательным `composer dump-autoload`. Это локальная
   проверка имеющегося dependency set; не доказательство новой установки или hosted CI (D47).
   Чистая установка каждого harness — по его Composer manifest с sealed Stancl staging.
6. P03.2 Files явно включает `phpunit.xml`, `fixtures/**`, PHPStan NEON и проекцию subjects/hashes
   архитектурных тестов. Validation включает mutation cohort, полный стек и regression tests инструмента.
7. Roadmap не делает фазовый аудит безусловным: последний GREEN item закрывает фазу согласно D56.5.
   Отдельный adversarial audit возможен только по принятому candidate/run-bound `audit-admission/v2`
   и маршруту Task frontier/xhigh. Это не дополнительный обязательный круг перед P04.

## Why

Прежний dry run без Rector не обнаруживал потерю коротких ссылок, stale NEON и fixture subjects.
Полный прогон воспроизвёл эти ошибки. Исправления сохраняют смысл прежних checks и dependency set,
а повторный replay из чистого дерева проверяет именно последовательность, которую получит исполнитель.

## Consequences

Карта 278/68, оба baseline 436/159, public contracts и frozen Golden сохраняются. Исправления
механики исполняются инструментом, не ручной правкой сотен файлов. Новый finish и независимый
review связываются с текущими digest; root остаётся единственным писателем плана. Product items
не получают GREEN из design replay и сохраняют свои проверки/независимое review.
