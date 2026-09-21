# PLAN2 — повторный аудит исправлений дизайна — 2026-09-20

**Вердикт: RED — осталось одно замечание DA09, средний приоритет.**

Проверены исправления DA01–DA14, решения D98–D108, owning items, нормативные
research-разделы и затронутые consumer-контракты. 13 замечаний сняты на уровне
дизайна. Это не исполнение будущих runtime-проверок. Stage 2 остаётся RED;
items и фазы не исполнялись и не закрывались.

Независимый от автора исправлений root: GPT-6 Astra/high, provider-thread-state.
Исходный RED-отчёт и remediation evidence сохранены. Полный research и дополнительный
агентный проход для этой ограниченной дельты не повторялись.

## DA09 — CoreOptions классифицирован вопреки собственным зависимостям

**Владельцы:** P6.2/P7.1; решение D101.

D101 относит CoreOptions к Foundation; это перенесено в P6.2 Implementation Rules.
`research/P6-design.md:245` задаёт `Foundation => []`, строки 275–277 относят
CoreOptions.php к этой зоне. R-Z1 разрешает только свою зону и ALLOWED.
Но нормативный код P7.1 в `research/P7-design.md:265` и далее импортирует
CoreX\Audit\QueuedAuditLogger, CoreX\Settings\DatabaseSettingsRepository,
CoreX\Departments\DatabaseDepartmentRepository и другие классы чужих зон.
Они используются как defaults в fromArray(), а не являются неиспользуемыми imports.

Детерминированная проверка блока §5.1 обнаружила 12 импортов из Audit, Settings,
Departments, Features и TenancyContracts. Даже без учёта dump-контрактов этого
достаточно, чтобы combined P6+P7+P8 CoreZoneTest не мог пройти при исполнении
написанного дизайна. R-Z0 исправлен, но нарушение R-Z1 осталось.

**Минимальная корректировка:** согласовать CoreOptions с ролью существующего
composition holder Config: точечно классифицировать его как COMPOSITION в
D101/P6.2/нормативной карте либо убрать межзонные зависимости. Первый вариант
меньше по объёму и сохраняет кодовый эскиз. Не расширять ALLOWED[Foundation]
и не разрешать весь Support/**.

**Проверка исправления:** одинаковая классификация во всех owning carriers;
CoreOptions допускает объявленные defaults, а неизвестный Support/Probe и
Foundation → SavedViews/Internal по-прежнему отклоняются. Будущий runtime witness
остаётся в combined P6+P7+P8. Достаточен адресный просмотр этой дельты;
повтор остальных 13 проверок без изменения их входов не нужен.

## Снятые замечания

| DA | Проверенные носители | Основание снятия на уровне дизайна |
|:--|:--|:--|
| DA01 | D100; P7.1/P7.12; P7 §5.3; P9.2 | Default разрешается при операции; explicit override фиксирован; lifecycle witness. |
| DA02 | D104; P13.1/P13.5/P13.6; P13 §7.1; P14.5 | Effect/outcome в одной tenant-транзакции; blind takeover запрещён; crash witness. |
| DA03 | D105; P13.6/P13.8/P13.12; P13 §7.1 | Pure replay authorization; denial/pruning важнее saved outcome; handler не повторяется. |
| DA04 | D99; P5.7/P5.12; P5 §6.1; P14.12 | Durable acceptance; lossy default снят; offline/restart witness обязателен. |
| DA05 | D102; P10.3/P10.4; P10 §3.1 | История отделена от current-key reservations; атомарная публикация; evolution witness. |
| DA06 | D103; P11.4/P11.5/P11.8; P11 §9–10 | Fingerprint исключён из replay identity; legacy algorithm сохранён; drift witness. |
| DA07 | D98; P3.4; P3 §6; P17.10 | Package CWD и actual candidate commits; exit 3 отдельно от infrastructure RED. |
| DA08 | D101; P8.1/P8.4/P8.5 | Два точных public symbols и owning composer write; реальные consumers проверяют R4. |
| DA10 | D106; P13.8/P13.12; P17.3 | Codec покрывает UUIDv7/UUID/ULID/integer и отрицательные сценарии. |
| DA11 | D99; P5.6/P5.12; P5 §5.11 | Exhausted expired lease → dead; corrupt reconstruction в bounded failure path. |
| DA12 | D107; P10.8/P16.11/P16.13/P17.10 | Attributes — B; physical owner P10.8; P16 потребляет post-producer no-move proof. |
| DA13 | D100; P7.1/P7.12; P7 §5.1 | array_key_exists сохраняет missing/null/zero/positive; четыре witness cases. |
| DA14 | D108; P1.2 | Явное конечное исключение: 23 пути, включая 20 manifests; subtree-only validation. |

Roadmap содержит исправленные зависимости P10 от P4/P5/P7 и P16 от P14.
Старые общие фразы эскизов читаются с явными corrections D98–D108 и owning
Implementation Rules; они не выделялись в самостоятельные runtime-дефекты.

## Доказательства и пределы

- plan-lint выбранного плана: 0 ошибок, 0 предупреждений.
- design-reaudit-proof.json: 12 запрещённых импортов и 23-path manifest P1.2.
- design-reaudit-evidence.json: хэши кандидата, finding и точный возврат.
- PHP/PG-тесты будущего продукта не запускались: это read-only design audit.
- Новых внешних утверждений нет; version-pinned доказательства первого аудита
  переиспользованы. Внешний research не повторялся.
- Route capture успешно выполнен с marketplace root в PYTHONPATH по ранее
  зафиксированной особенности Task runtime. Нового сбоя среды не наблюдалось.

**Продолжение:** локальная корректировка DA09 в D101/P6.2/P7.1 и соответствующих
research-носителях. Рекомендация по дизайну не разрешает исполнение items.
