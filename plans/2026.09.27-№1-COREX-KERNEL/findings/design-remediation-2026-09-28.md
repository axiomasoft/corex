# PLAN3 — P01 design remediation B1–B3 — 2026-09-28

Поручение: manual autofix по `design-audit-2026-09-28.md`, затем `task:plan-design 2026.09.27-№1-COREX-KERNEL finish`.
Граница: дизайн P01 и завершающие carriers P00–P01; продукт, PLAN2, plan-run и исполнение items не затронуты.
Прежние незакоммиченные finish/audit-артефакты сохранены; journal только дополняется.

## Исправления

- **B1:** D40 supersedes D38. Запись baseline = `normalize(apply(map, current))`; сравнение проецирует обе стороны.
  Досье, examples §3 и P01.2 согласованы; добавлен позитивный контроль до/после generated rename и идемпотентности.
  Историческое тело D38 сохранено; изменены только reciprocal metadata status/superseded_by.
- **B2:** `phpSurface()` собирает public declared properties (name/type/static/readonly), constants name→{value,type},
  фактические defaults параметров с различием «нет ключа» / null. Enum-значения сохраняют FQCN, case и backed value;
  значения arrays/default-objects представлены без object-id. Поля обязательны, не ADAPT. Негативы по каждому полю
  добавлены в P01.2 и досье §4.2.
- **B3:** пять Validation-носителей P01.1/P01.3/P01.4 явно проверяют отсутствие нарушений или нулевой счёт.
  Exit 0 на корректном дереве, exit 1 при нарушении/ошибке чтения; pin-проверка досье §4.4 совпадает с P01.4.

## Проверка дельты

```bash
python3 plans/2026.09.27-№1-COREX-KERNEL/findings/design-remediation-2026-09-28-check.py
```

Скрипт извлекает **фактические PHP-блоки и bash-команды** из examples/items, запускает их в удаляемом временном дереве
на PHP 8.4 и сохраняет stdout/stderr/exit в JSON. Генератор rename-map вызывается без записи продукта.

- B1: все 666 class + 16 package правил — GREEN до/после rename, повторная проекция идемпотентна; изменение поведения
  остаётся различием, похожий FQCN без точного правила не переписывается.
- B2: values/types/properties/defaults присутствуют в снимке; изменение property type, constant value/type и default
  меняет соответствующие поля; null, отсутствие default, variadic, array/enum defaults проверены.
- B3: все пять команд — clean exit 0, violation exit 1, missing carrier exit 1. Исторический repro аудита оставлен
  неизменным как доказательство прежнего дефекта.

Досье P00 и внешние captures P01 не менялись; новый внешний поиск не нужен: B1–B3 — repository-grounded дефекты.
Это проверка ограниченной дельты дизайна, не продуктовый suite и не независимый design audit.
Результат finish, новый fingerprint, plan-lint и delivery receipt — `design-remediation-2026-09-28.json`.

## Provenance и продолжение

Фактический provider `turn_context`: **codex/gpt-6-sol/high**; журнал, Meta и Update Log используют эту модель.
Это implementation/high вместо рекомендованного Task design frontier/high. Новый процесс/дочерний автор не запускался.
CLI `design-context --finish` отверг формат invocation, встроенного владельцем в предложение (`ROUTE_LAUNCH_MISMATCH`).
Использован read-only `task.plan_work.design_context(..., finish=True)` — тот же компактный фасад без execution admission;
провайдерский журнал не изменялся, фиктивная route attestation не создавалась. Finish вызывается штатным CLI.

После finish следующий шаг — независимый `task:plan-audit 2026.09.27-№1-COREX-KERNEL design` нового fingerprint,
с повторной оценкой B1–B3 и переиспользованием неизменённого P00. Выполнение этого аудита не входит в текущее поручение.
Исполнение P01 остаётся закрытым до GREEN этого нового fingerprint; P02–P09 остаются backlog по D24.
