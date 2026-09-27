# Профили структуры пакетов CoreX

**Версия:** 1.0.0 · **Статус:** нормативный · **Уровень:** 0 (`corex/*`)

Три профиля группировки `src/` по **измеренному** объёму пакета. Нормативные статьи —
`STR-001`…`STR-006` в `constitution.md`; здесь — как их выполнять, без переписывания текста
статей.

## Пороги профилей

Пороги выведены из распределения 20 пакетов (`research/00-baseline-metrics.md` §1,
поправка P1-design §12). Внешний обзор стратегий (layer/feature/context-first) **не дал
числовых порогов** — эта часть `[UNVERIFIED]`.

| Профиль | Порог (файлов `.php` в `src`) | Стратегия группировки | Пакеты сегодня |
|:--|:--|:--|:--|
| A — компактный | < 40 | Слой-первая (`Contracts`, `Data`, `Internal`) допустима | access 34, media 36, transfer 39, search 29, recurrence 16, tasks-workflow 16, wakeup 20, workflow 15, panels 11, search-intl 8, search-meilisearch 4 |
| B — подсистемы | 40–100 | Верхний уровень — подсистемы; слои внутри подсистемы | attributes 63, notifications 44, crm 56, tasks 63, intl 67, modules 99 |
| C — bounded context | > 100 | Верхний уровень — bounded contexts; слой-первая не применяется | operations 165, core 169, tenancy 175 |

Порог — **основание пересмотреть структуру, а не приказ немедленно её менять**: переход
профиля выполняет названный item фазы (P9, P16), а не попутная правка (NON-001).

Профиль — **функция измеренного объёма**; отдельного решения владельца не требует.

`corex/attributes` классифицируется как B по producer-adjusted census (D107): пол ≥ 44 файлов
до физической раскладки P16; компактный профиль A для него запрещён.

Каталог `packages/import-export` — **тумбстоун** (D13, PKG-009): не входит в перечень живых
пакетов и не получает профиль.

## Процедуры по статьям STR-002…STR-006

- **STR-002 — один use case ≤ 2 верхних папок.** Назвать use case и перечислить папки с его
  кодом. Три и больше — группировка по технологии, а не по задаче.
- **STR-003 — >20 прямых детей корня `src`.** Сегодня нарушает только `tenancy` (26).
  Группировать по bounded context. Исключение: `exceptions/STR-003/tenancy-src-children.md`.
- **STR-004 — каталог из одного класса.** Допустим, если каталог выражает границу, которую
  заполнит названный item; иначе класс живёт уровнем выше.
- **STR-005 — `Internal/`.** Единица enforcement R2 графа пакетов. Новый `Internal/` внутри
  bounded context пакета не создаётся. `tenancy/src/Membership/Internal` (39 файлов) —
  исключение с условием снятия P9: `exceptions/STR-005/tenancy-membership-internal.md`.
- **STR-006 — `Data/`.** В профиле A остаётся. В B и C значения группируются по домену:
  под правило сегодня подпадает только `intl` (профиль B); `transfer` и `notifications`
  остаются как есть.

## Структурный долг `tenancy`

- 26 прямых детей корня `src` (STR-003) — см. исключение STR-003.
- `Membership/Internal` — 39 файлов, весь Membership (STR-005) — см. исключение STR-005.
- Целевая раскладка bounded contexts — P9; раскатка профилей по пакетам — P16.

## Назначение профиля по пакетам

Census пересчитан после producer-фаз и P16.1–P16.10 (2026-09-27). Итого **1129** файлов `.php`
в `src/` по **20** живым пакетам. Доказательства no-move для профиля A и attributes (B) —
`plans/2026.09.19-№1-COREX-PLATFORM-HARDENING/findings/P16-profile-a-receipts.md`; физические
перемещения B/C — `findings/P16-move-receipts.md` и `findings/P16-api-layout-receipt.md`.

| Пакет | Файлов в `src` | Профиль | P16 исход |
|:--|--:|:--|:--|
| search-meilisearch | 4 | A | no-move (P16.11) |
| search-intl | 8 | A | no-move (P16.11) |
| panels | 11 | A | no-move (P16.9, P16.11) |
| workflow | 15 | A | no-move (P16.11) |
| recurrence | 16 | A | no-move (P16.11) |
| tasks-workflow | 16 | A | no-move (P16.11) |
| wakeup | 20 | A | no-move (P16.11) |
| search | 29 | A | no-move (P16.11) |
| access | 34 | A | no-move (P16.11) |
| media | 36 | A | no-move (P16.11) |
| transfer | 39 | A | no-move (P16.11); карта роста ниже |
| notifications | 44 | B | P16.2 |
| crm | 56 | B | P16.8 |
| attributes | 63 | B | P10.8 layout; B no-move (P16.11, D107) |
| tasks | 63 | B | P16.3 |
| intl | 67 | B | P16.1 |
| modules | 99 | B | P16.7 |
| operations | 165 | C | P16.4–P16.6 (`CoreX\Api`) |
| core | 169 | C | P9 bounded contexts |
| tenancy | 175 | C | P9 bounded contexts |

## Карта роста `corex/transfer` (условная)

Сегодня **39** файлов — профиль **A**. Текущая раскладка: `Contracts/`, `Data/` (включая
`Data/Export/`), `Internal/{Codecs,Export,Transfer}` и корневой `TransferServiceProvider`.
Новые пустые каталоги **не создаются** до измеренного роста и до того, как именованный use case
пересечёт **две** верхние папки (D91, audit A58).

Продвижение в подсистемы Profile B допустимо только после пересечения порога 40 файлов **и**
доказанной пользы для named use-case span:

| Целевая подсистема (будущая) | Текущий владелец | Named use case |
|:--|:--|:--|
| `Definition/` | `Contracts/` (runner, source, sink, mapper ports) + часть `Data/` (limits, manifest) | Объявить перенос и лимиты до запуска |
| `Source/` | `Contracts/TransferSource`, `Contracts/ExportSource`, `Data/Source*` | Читать строки/курсор из источника |
| `Sink/` | `Contracts/TransferSink`, import decision types в `Data/` | Принять строку в целевую модель |
| `Mapping/` | `Contracts/RecordMapper`, `Data/Mapping*` | Сопоставить каноническую запись с целевым рядом |
| `Validation/` | `Data/Validation`, conflict rules | Отклонить или классифицировать конфликт до записи |
| `Execution/` | `Internal/Transfer/*`, `Contracts/TransferRunner` | Выполнить шаг переноса с отменой и лимитами |
| `Checkpoint/` | `Data/StepToken`, cursors, `TransferRef` | Возобновить перенос после сбоя |
| `Format/` | `Internal/Codecs/*`, `Internal/Export/CanonicalExportCodec` | Сериализовать страницу экспорта (CSV/NDJSON) |
| `Reporting/` | `Data/TransferReceipt`, `TargetResult`, export manifest types | Выдать итог и метрики прогона |
| `Laravel/` | `TransferServiceProvider` | Зарегистрировать контейнерные биндинги |

До выполнения условий выше пакет остаётся на layer-first профиле A без физических `git mv`.
