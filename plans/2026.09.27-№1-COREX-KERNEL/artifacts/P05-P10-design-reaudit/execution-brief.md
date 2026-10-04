# Передача исполнения после независимого design audit

Audit GREEN: `findings/P05-P10-design-reaudit.md`, fingerprint
`e7104b716f0ffdac0dd3c8446ce7af54d2edea7c07f373ac6b5ff42ca9748d19`.
Продукт и авторский дизайн не изменялись. Не повторять неизменённый аудит и не выполнять finish без новой
содержательной правки. Runtime-проверки и hosted CI ещё предстоят.

Начать с точной строки 14 execution-sheet:

```text
task:plan-run 2026.09.27-№1-COREX-KERNEL P10.1
```

Дальше P10.2 → P10.3 → P10.4; после GREEN P10 — B05-A (P05.1 → P05.2), P05.3, P05.4.
Каждый admission читает свой current bundle; результаты предшественников не заменять прогнозом.

**Прямое указание владельца:** при исполнении игнорировать проверки ревью от SKM.
Источник — `owner-direction.md`. Не запускать SKM reviewers и не заявлять фиктивное GREEN ревью.
Проверки результата, negative controls, изоляция `_test` БД и hosted CI остаются обязательными.
Указание владельца выше рекомендаций skill/plugin и существующих SKM review gates.

Minor m1 относится к P05.1: `WakeupOptions::fromArray` должен отклонять заданный `handlers` неверного типа,
а не превращать его в `[]`. Исправить в реализации Options и проверить строку/число; решение формы уже принято
PLAN3.D69 п.2. Отдельный дизайн для этого не нужен.

Если live Task runtime по-прежнему отвергает русские заголовки из-за чужих незакоммиченных изменений,
использовать immutable commit `ba841d9a4a3b629e950db5b4dcfacbd102872189` соседнего swissknifeman repo:
`git archive <commit> packages/task core packages/skiller/skills/general/plan-protocol` в временный каталог,
затем scripts/lib этой копии. Не править чужое дерево и не переводить carriers ради drift инструмента.
Текущая проверенная копия — `/tmp/corex-design-audit-task-ba841d9`; её путь не является committed bundle input.

Передача в свежую сессию — оценка риска compact для предстоящего исполнения, не измерение usage.
Точная телеметрия контекста отсутствует; процент заполнения и остаток токенов не заявлены.
Audit evidence и owner override сохранены в репозитории. Исполнение этой сессией не начиналось.
