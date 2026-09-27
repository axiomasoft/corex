# PLAN3 — design audit P00 — 2026-09-27

РЕЖИМ: независимый design audit; read-only над дизайном и продуктом, запись только результатов аудита.
ВХОД: завершённый design finish, fingerprint `9fb2620f37106df31c873ca2e296aba73ab9ece2fbff91311aac2edc1078f634`.
СКОУП: единственная детализированная фаза P00, P00.1–P00.3, их зависимости, досье/примеры, D21/D24, execution-sheet admission 1; архитектурные предпосылки этих items.
НЕ ТРОГАТЬ: продукт, нормативные спецификации/решения, PLAN2 и его исходные незакоммиченные изменения; не исполнять P00.

Вердикт: **ATTENTION** (`audit-attention`): 4 Major, 1 Minor. Текущий дизайн не получает GREEN.

Автор: `codex/gpt-6-astra/high`, независим от автора `claude-opus-5-5/high`.
Actual route получен через Task `route_evidence_codex.capture_current`, `provider-thread-state`;
модель и effort указаны фактические, не заявляется запуск xhigh.
Checkout: `b624936` с существующими изменениями PLAN2; все выводы ниже — `RAG:—`.

## A1 · Major · issue — разрез F9 не учитывает обратную зависимость engine → webhooks

**Место:** [`research/P00-design.md:34`](../research/P00-design.md#2-развилки-p001-и-рекомендации), F9;
P00.1 Scope Included / Validation; [`backlog/P02.md`](../backlog/P02.md).

По предложенной раскладке `OpenApi/*` остаётся в engine, `Webhooks/*` уходит в webhooks.
Но `packages/operations/src/OpenApi/Internal/ContractSnapshotBuilder.php:8` импортирует
`CoreX\Api\Data\WebhookTopic` из `Webhooks/Data/WebhookTopic.php`; это runtime-зависимость:
builder читает `WebhookTopic::CLASSES` и имеет метод `topic(WebhookTopic $topic)`.
В обратную сторону `Webhooks/Laravel/WebhookRouteRegistrar.php:11` импортирует
`OperationRegistry` из `Definition/OperationRegistry.php`.
При буквальном разрезе F9 получается **operations → webhooks → operations**.

Принятая норма `research/02-target-architecture.md` §2 запрещает циклы, §1.3 требует
самодостаточности пакета. Досье предлагает проверить только `Tools/Webhooks → engine`,
сохранить рёбра в package-map, но схема package-map не содержит рёбер, а алгоритм
`check-maps.py` §5 не проверяет зависимости. Поэтому namespace-проверки не доказывают,
что выданная P00 карта пригодна для механического P02. Откладывание развязки до P06
не устраняет противоречия с границами/гейтами P02.

**Исправление дизайна:** обязать P00.1 явно решить обратные рёбра (включая владельца
агрегации snapshot), сохранить результирующий граф и проверить отсутствие циклов;
при необходимости дать файловые исключения в карте или назначить развязку до split.
Конкретное архитектурное решение остаётся frontier-исполнителю P00.1; аудитор его не навязывает.
Это статический вывод о предложенной раскладке, не утверждение о выполненном split.
**Owning continuation:** design P00 / P00.1. Evidence: JSON `A1`.

## A2 · Major · issue — longest-match противоречит обязательному негативному примеру

**Место:** [`research/P00-design.md:73`](../research/P00-design.md#5-схемы-карт-и-алгоритм-check-mapspy-p001-p002),
шаги 2–3; [`examples.md:40`](../artifacts/P00-design/examples.md) и строка негативного контроля :55.

Пример требует строку `packages/operations/src/` для корневого `ApiServiceProvider.php`.
После удаления более специфичной строки `packages/operations/src/Tools/` алгоритм
longest-match всё ещё покрывает `Tools/ToolServiceProvider.php` родительской строкой.
Ожидаемого `uncovered` не возникает: файл незаметно получает владельца engine.
Оставшиеся строки `Tools/Contracts/` сохраняют наличие tools в карте, поэтому проверка
«каждый пакет владеет ≥1 строкой» также не ловит потерю границы.

Дополнительно шаг 3 проверяет `seg(new_prefix)` строки, но пример ядра содержит
`new_prefix=CoreX\` для всего остатка `core/src/`: у него нет второго сегмента из
закрытого списка модулей. Если такой wildcard разрешить без проверки конечных FQCN,
гейт не обнаружит чужой сегмент внутри остатка; если запретить — положительный пример невалиден.

**Исправление дизайна:** согласовать правила fallback и negative fixture; проверять
вычисленное конечное имя/владельца каждого файла, явно описать root provider и
namespace-less fixtures/migrations. Негативный контроль должен доказывать потерю
границы split, а положительный пример — проходить тот же алгоритм.
`check-maps.py` пока отсутствует: воспроизведён именно заданный алгоритм, не будущая реализация.
**Owning continuation:** design P00 / P00.1. Evidence: JSON `A2`.

## A3 · Major · issue — проверки P00.2 отвергают корректный результат

**Место:** [`research/P00-design.md:83`](../research/P00-design.md#5-схемы-карт-и-алгоритм-check-mapspy-p001-p002),
шаг 6; [`P00.2.md:30`](../phases/P00/P00.2.md), Code Guidance и Validation :37.

P00.2 требует одну таблицу с 58 статьями constitution **и** строками политик с ID `§N`
и новых правил с `NEW-<n>`. Алгоритм `--doctrine` сравнивает множество ID constitution
с **всей** второй колонкой этой таблицы. Уже обязательная строка политики `§1` делает
равенство ложным; пример `NEW-1` даёт ту же проблему. Сравнение не ограничено документом
constitution и существующими статьями.

Вторая проверка `grep -c '≈' <architecture>` на корректном документе печатает `0`,
но возвращает **exit 1**. Без явной обработки ожидаемого отсутствия совпадений такой
Validation carrier не зелёный по принятому Task terminal-evidence контракту.

**Исправление дизайна:** проверять полноту только строк существующих статей
`constitution.md`, отдельно валидировать ключ `(документ, ID)`, политики и новые правила;
заменить grep на проверку с exit 0 при отсутствии `≈` и exit 1 при наличии/ошибке чтения.
Зафиксировать positive/negative worked examples для смешанной таблицы и точные exit codes.
**Owning continuation:** design P00 / P00.1 (скрипт) и P00.2 (контракт).
Evidence: JSON `A3` (58 ID, дополнительные `§1`/`NEW-1`, grep `stdout=0`, `exit=1`).

## A4 · Major · issue — P00.3 требует воспроизвести уже устранённый crash PLAN2

**Место:** [`P00.3.md:24`](../phases/P00/P00.3.md), Code Guidance и Validation :32.

Acceptance жёстко требует последнюю строку
`task.plan_decisions.DecisionError: идентификаторы решений повторяются`.
На текущем дереве `task.plan_decisions.load_decisions(PLAN2)` успешно загружает **138 решений**;
исходные изменения пользователя уже заменили конфликтующий D128 на D138.
Корректные три правки P00.3 не должны возвращать исправленный дефект.
Кроме того, последняя строка traceback не проверяет корректность новых Meta/Next:
падение загрузчика прекращало lint до соответствующих проверок.

**Исправление дизайна:** сравнивать свежую структурированную baseline/delta PLAN2,
допускать улучшение baseline и отдельно проверять семантику изменённых Meta/Next/ACTIVE.
Не фиксировать старую аварийную строку как желаемый результат и не возвращать дубликат D128.
Полный lint PLAN2 остановлен аудитором примерно через 4 минуты без вывода;
его общий результат **не установлен**. Отсутствие прежнего duplicate-ID crash
доказано прямым вызовом того же загрузчика, а не выводом о GREEN всего PLAN2.
**Owning continuation:** design P00 / P00.3. Evidence: JSON `A4`.

## A5 · Minor · issue — сохранённые zone-consumer facts уже не совпадают с checkout

**Место:** [`facts/zone-consumers.tsv`](../artifacts/P00-design/facts/zone-consumers.tsv), Storage и Tenancy.

Объявленная команда `zone-consumers.py --check …/facts/zone-consumers.tsv` возвращает exit 1:
Storage 36 → 37 импортов, Tenancy 412 → 414; наборы пакетов-потребителей не изменились.
Это не новый архитектурный blocker: досье §6 уже предусматривает обновление фактов.
Перед повторным finish обновить snapshot и зависимые числа через владельца дизайна;
аудитор не меняет проверяемые Inputs. Evidence: JSON `A5`.

## Покрытие и проверки

- Finish fingerprint совпал; design-gate до начала: `PLAN_DESIGN_AUDIT_MISSING` (ожидаемо до первого аудита).
- `plan-lint.py <PLAN3>`: exit 0, 0 ошибок, 0 предупреждений. Это структурная проверка;
  она не опровергает семантические противоречия A1–A4.
- Task Routing compiler: B00-A, P00.1 → P00.2 → P00.3, frontier/high, manual, review full;
  совпадает с execution-sheet admission 1. P00.2 потребляет будущие результаты P00.1 —
  нормальная зависимость, их отсутствие сейчас не finding.
- P01–P09 — разрешённый D21/D24 backlog, не незавершённые исполнительные phase carriers;
  требование спроектировать их заранее не предъявляется.
- Проверены read-only reproductions A1–A5; сырой вывод —
  [`design-audit-2026-09-27-evidence.json`](design-audit-2026-09-27-evidence.json).
  Воспроизведение из корня репозитория:

  ```bash
  python3 plans/2026.09.27-№1-COREX-KERNEL/findings/design-audit-2026-09-27-repro.py --task-root /home/vostrikov/projects/packages/swissknifeman/packages/task
  ```

Границы: PHP/DB-тесты не запускались (нет изменений продукта); актуальность внешних
публикаций/версий не перепроверялась — findings не зависят от них. Нет утверждения
о полноте API-аудита будущих P01–P09. Неизменённые материалы исследования переиспользованы.

## Продолжение

Владелец исправления — дизайн P00: устранить A1–A4, обновить A5, затем finish
и проверка изменённых рисков design audit по новому fingerprint.
Повторная оценка не требует полного пересмотра неизменённой архитектуры.
Текущая команда заканчивается артефактом аудита; исполнение P00 не начато.

Runtime-ограничение: текущий `plan_readiness.validate_design_handoff_target(P00)`
возвращает `PLAN_DESIGN_NOT_NEEDED` для любых детализированных незавершённых items,
не учитывая design-audit ATTENTION. Поэтому машинный Next использует поддержанный
`manual` с точным заданием исправления дизайна. Это не допуск исполнения и не повод
менять Task runtime или удалять поля спецификаций ради прохождения delivery gate.
