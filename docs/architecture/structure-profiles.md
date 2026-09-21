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
| A — компактный | < 40 | Слой-первая (`Contracts`, `Data`, `Internal`) допустима | attributes 36, media 35, auth 34, transfer 32, search 29, recurrence 16, tasks-workflow 16, wakeup 16, workflow 14, panels 11, search-intl 8, search-meilisearch 4 |
| B — подсистемы | 40–100 | Верхний уровень — подсистемы; слои внутри подсистемы | modules 88, intl 67, tasks 59, crm 56, api 55, notifications 44 |
| C — bounded context | > 100 | Верхний уровень — bounded contexts; слой-первая не применяется | tenancy 159, core 109 |

Порог — **основание пересмотреть структуру, а не приказ немедленно её менять**: переход
профиля выполняет названный item фазы (P9, P16), а не попутная правка (NON-001).

Профиль — **функция измеренного объёма**; отдельного решения владельца не требует.

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

| Пакет | Файлов в `src` | Профиль |
|:--|--:|:--|
| search-meilisearch | 4 | A |
| search-intl | 8 | A |
| panels | 11 | A |
| workflow | 14 | A |
| wakeup | 16 | A |
| recurrence | 16 | A |
| tasks-workflow | 16 | A |
| search | 29 | A |
| transfer | 32 | A |
| auth | 34 | A |
| media | 35 | A |
| attributes | 36 | A |
| notifications | 44 | B |
| api | 55 | B |
| crm | 56 | B |
| tasks | 59 | B |
| intl | 67 | B |
| modules | 88 | B |
| core | 109 | C |
| tenancy | 159 | C |

Числа — из `00-baseline-metrics.md` §1 (итого 888 файлов в `src`).
