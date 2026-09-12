# Бриф — 2026.09.12-№1-COREX-SUCCESSOR

## Исходная задача

Создать исполнимый Layout v2 преемник завершённого COREX-CORE на основании подтверждённого недоставленного delta. Восстановленный вход закреплён в D1 (scope reconciliation), D2 (source/authority boundaries) и D9 (исходный закрытый список carriers). Архивные P1–P3 сохраняют свои результаты; их повторное исполнение и ретроспективная миграция исключены.

CoreX остаётся агностичным L0. Допустимая реализация ограничена package modules-lint, tenancy Laravel12/13 package CI и department record-parity tests. Нормативные, architecture и distribution вопросы оформляются decision-ready proposals, без изменения public Contracts, frozen schema, Brain нормативных документов или внешнего состояния.

## Уточнение владельца: завершить дизайн

<a id="finish-20260912"></a>

Точный запрос владельца в текущей Codex-сессии:

```text
$ task:plan-design 2026.09.12-№1-COREX-SUCCESSOR finish
```

Это запуск target, указанного в предыдущем waiting handoff для завершения carrier qualification. D10 фиксирует разрешение необходимого авторинга: добавить `brief/00-brief.md`, `roadmap.md` и generated `open-questions.md`, затем regenerate/check/lint и сохранить переносимый план. Отдельного словесного approval сообщения не было; основание — этот запрос на действие, а не утверждение агента о выданном runtime approval.

## Ожидаемый результат

- Полный план с тремя Phase Assurance blocks, шестью спецификациями items, Routing, наблюдаемыми Validation/Deliverables и сохранёнными owner gates.
- Свежие owned status/state/decision views и проверенные bundles только для ready P1.1/P1.2. P2/P3 bundles собираются после появления настоящих D3/D4/D5 outputs.
- Focused plan-lint без ERROR; optional index не создаётся. Plans синхронизированы с Brain через `brain sync plans CoreX`.
- Один semantic Next: рекомендовать отдельный routed запуск B1. Текущая design invocation заканчивается возвратом результата владельцу.

## Границы разрешения

Запрос `finish` разрешает завершение дизайна и его обязательных carriers. Он не запускает реализацию, не принимает будущие P3 choices, не разрешает public API/schema delta, docs merge, audit-phase, release/tag/push, credentials или DNS. До v1 public Contracts этот план также не меняет. Исходные ограничения D1/D2 продолжают действовать; расширение закрытого carrier списка ограничено тремя файлами D10.
