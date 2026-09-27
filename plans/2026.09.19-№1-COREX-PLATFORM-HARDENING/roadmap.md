# Roadmap исполнения — 2026.09.19-№1-COREX-PLATFORM-HARDENING

**Обновлён:** 2026-09-23 · **Соответствует plan.md:** D118 execution batches; D112/D114 сохраняются как история P1–P6

Живая карта «что запускать дальше». Не второй план: нормативные контракты живут в `plan.md`
и фазовых файлах, вердикты — в `findings/audit-verdicts.md`.

## 1. Правило исполнения этого плана

Три стадии строго по порядку (D14):

| Стадия | Что запускается | Сколько раз | Состояние сегодня |
|:--|:--|:--|:--|
| 1 — детализация | `task:plan-design <ID> Pn`, модель по §3.1a | 17 (по фазе) | P1–P17 готовы; стадия завершена |
| 2 — общий аудит | `task:plan-audit <ID> design` на frontier/high | 1 | RED 2026-09-20: DA01–DA14; design remediation и corrective pass завершены, independent repeat audit не запускался |
| 3 — исполнение | `task:plan-exec <ID> Pn.m` | по items; связанные items в одной сессии по `execution-batches.md` | P1–P4 и P6 закрыты; P5 содержит P5.6 с отклонениями; следующий P7.1 |

Единица запуска стадии 1 — **детализация одной фазы**. Детализация обязана произвести три
носителя:

1. `research/Pn-design.md` — нормативный текст, сигнатуры, алгоритмы и конкретные
   решения для каждого item'а; исполнитель сверяет их с живым деревом;
2. `research/Pn-design-rag.md` — внешние подтверждения фазы с первичными источниками;
3. `phases/Pn/Pn.m.md` — item-спеки в пределах бюджета 200K на item.

**Детализированная фаза не исполняется сразу.** Ни один `task:plan-exec` не запускается,
пока не детализирована последняя фаза и не закрыт общий аудит: сквозные контракты связывают
фазы между собой, а ни один проход детализации не видит плана целиком. Порядок детализации
свободен в пределах графа §2: фаза детализируется, когда детализированы фазы, чьи контракты
она потребляет.

## 2. Граф фаз

```text
P1 → P2 → P3
P3 → P4 → P5 → P6
P3 → P7 → P8
(P6, P8) → P9
(P4, P5, P7) → P10 → P11
(P5, P6, P9) → P12
(P4, P7, P11, P12) → P13 → P14
(P1, P2, P3, P6, P9, P10, P13, P14, P15) → P16
all → P17
```

Точные предшественники:

| Фаза | Требует | Почему именно эти |
|:--|:--|:--|
| P1 | — | Нормативный слой; без него любая правка локальна и противоречива (D1). |
| P2 | P1 | Инвентаризация классифицирует по таксономии и профилям, введённым P1. |
| P3 | P2 | Гейт фиксирует измеренное состояние, а не гипотезу; baseline нужен для allowlist'ов. |
| P4 | P3 | Смена формы `DataScope` должна сразу попадать под гейт сериализуемости. |
| P5 | P4 | Envelope и outbox несут скалярный scope, а не объект контекста. |
| P6 | P3, P5.18 | Зона `Messaging` собирается только после независимой joined qualification root/package gates. |
| P7 | P3 | Типизированные опции проверяются гейтом «нет `config()` вне composition». |
| P8 | P7 | Провайдер сначала получает, что регистрировать (Options), потом дробится. |
| P9 | P6, P8 | Tenancy опирается на сокращённое ядро и на registrar-модель. |
| P10 | P4, P5, P7 | Attributes consume tenancy/data-scope, durable outbox and typed configuration contracts. |
| P11 | P10 | Реестры замораживаются после того, как attribute-реестр получил окончательную форму. |
| P12 | P5, P6, P9 | Ошибки/OTel используют P5 envelope; новые core types входят в P6 zone gate; audit достраивает итоговую P9 impersonation structure. |
| P13 | P4, P7, P11, P12 | Внешний API стоит на скалярном scope, типизированных опциях, frozen-реестрах и таксономии ошибок. |
| P14 | P13 | Бюджеты, bulk и webhooks — надстройка над ядром операций; webhooks требуют outbox из P5. |
| P15 | P6, P9, P10, P13 | Имена меняются только после того, как семантика зафиксирована (D1, аудит «Фаза E»). |
| P16 | P1, P2, P3, P6, P9, P10, P13, P14, P15 | Structural profiles consume the normative rules, measured gates, final package semantics and P14-produced API/webhook structure before physical reconciliation. |
| P17 | все | Приёмка 1.0 доказывает результат всего плана. |

## 3. Карта стадии 1 — проходы детализации

| Фаза | Batch | Запуск | Модель прохода | Гейт владельца | Состояние |
|---|---|---|---|---|---|
| P1 | solo | plan-design | Claude Opus 5 (high) | Q1–Q2 до P15 | ✅ детализирована 2026-09-19 |
| P2 | solo | plan-design | Grok 4.6 (high) | — | ✅ детализирована 2026-09-19 |
| P3 | solo | plan-design | Grok 4.6 (high) | — | ✅ детализирована 2026-09-19 |
| P4 | solo | plan-design | Claude Opus 5 (high) | ✅ G-API: смена `shared-data-foundation/1` (только P4.7) | ✅ детализирована 2026-09-19 |
| P5 | solo | plan-design | Opus 5 original; D111 remediation | ✅ G-DDL P5.4; P5.16 закрыт по D115 | 🟠 17/18 GREEN; P5.6 закрыт с отклонениями, P5.13–P5.18 закрыты |
| P6 | solo | plan-design | Grok 4.6 (high) | — (D32: G-API остаётся на P15) | ✅ детализирована 2026-09-19 |
| P7 | solo | plan-design | Grok 4.6 (high) | — | ✅ детализирована 2026-09-19 |
| P8 | solo | plan-design | Grok 4.6 (high) | — | ✅ детализирована 2026-09-19 |
| P9 | solo | plan-design | GPT-5.6 Sol | — | ✅ детализирована 2026-09-19 |
| P10 | solo | plan-design | GPT-5.6 Sol | ✅ G-DDL назначен только P10.3 | ✅ детализирована 2026-09-20 |
| P11 | solo; items B17/B18 + solo | plan-design | GPT-5.6 Sol (high; fallback Grok-слота D37) | — | ✅ детализирована 2026-09-20 · 0/8 исполнено |
| P12 | solo; items B19/B20 + solo | plan-design | GPT-5.6 Sol (high; fallback Grok-слота D37) | — | ✅ детализирована 2026-09-20 · 0/8 исполнено |
| P13 | solo; items B21/B22 + solo | plan-design | GPT-5.6 Sol (high; fallback Astra-слота D37) | ✅ G-DDL только P13.5 · ✅ G-API только P13.12 | ✅ детализирована 2026-09-20 · 0/12 исполнено |
| P14 | B23–B27 + solo | plan-design | GPT-6 Astra (high) | G-DDL P14.3; G-SECRET P14.19 — grants pending | ✅ детализирована 2026-09-20 · 0/21 исполнено |
| P15 | B28–B29 + solo | plan-design | GPT-5.6 Sol (high; fallback Grok-слота D37) | G-API-P15; G-PKG-API/G-PKG-AUTH только conditional items — grants pending | ✅ детализирована 2026-09-20 · 0/14 исполнено |
| P16 | B30–B33 + solo | plan-design | GPT-5.6 Sol (high; fallback Grok-слота D37) | — | ✅ детализирована 2026-09-20 · 0/13 исполнено |
| P17 | solo; items B34–B36 + solo/manual | plan-design | GPT-5.6 Sol (high; fallback Grok-слота D37) | G-RELEASE только P17.12, grant pending | ✅ детализирована 2026-09-20 · 0/12 исполнено |

Исторический переход: после детализации P17 был закрыт общий design audit;
исполнение началось с P1.1. Текущий переход — P7.1 по §3.1 и D118.

### 3.1 Живой execution frontier

| Item | Lifecycle route | Cursor | Codex | Context |
|:--|:--|:--|:--|:--|
| P7.1 | implementation/high | `grok-4.7-high` | `gpt-5.6-sol` | solo (E07a); следующий P7.12 после checkpoint P7.1 |

Полный batch → item → model маршрут, точные артефакты и межфазные проверки —
`execution-batches.md`. Проверенные Cursor launch commands —
`execution-commands.md`. Provider-specific selectors/effort — `plan.md` §3.3.
Каждый launch печатает только команду активного provider; owner gates не меняются.

## 4. Соответствие фазам аудита

| Фаза аудита A | Фазы плана |
|:--|:--|
| A. Нормативные правила | P1 |
| B. Inventory | P2 |
| C. P0 boundaries | P3, P4, P5, P6, P7, P8, P10 |
| D. Domain structure (package-by-package) | P9, P16 |
| E. Naming | P15 |
| F. Extension verification | P11 (contract suites), P17 (фикстуры) |
| G. Consumers | P17 |

Порядок аудита сохранён в одном принципиальном месте: **переименования и переносы идут после
семантических решений**. Отличие от аудита: инвентаризация не предшествует гейтам, а гейты не
предшествуют P0-исправлениям — гейт ставится сразу после измерения, чтобы каждое последующее
исправление проверялось машинно, а не глазами ревьюера.

## 5. Бюджет контекста

| Уровень | Лимит | Как обеспечивается |
|:--|:--|:--|
| Item | route-specific; Composer ceiling 200K | ≤14 Required Reads, ≤2500 строк, ≤8 product files; Grok/Sol не расширяют scope только потому, что их context больше |
| Детализация фазы (frontier/high) | одна сессия | фаза читает только свои нормативные входы + 1–2 пакета; `research/Pn-design.md` — единственный носитель готового кода |
| Верхний слой | этот файл + `plan.md` | скелеты фаз не содержат кода |

Признак, что item надо делить при детализации: спека требует читать больше одного пакета
целиком, либо Files выходит за 8 путей, либо Validation требует и sqlite-, и pg-лейна, и
mutation-прогона одновременно.

## 6. Гейты владельца (сводно)

| Где | Что утверждает | Блокирует |
|---|---|---|
| P1 (Q1–Q2) | Имена пакетов `api` и `auth`; proposed D89/D90 требуют явного owner outcome | только P15.11/P15.12 и зависящие от результата P16 paths |
| P4.7 | Смена контракта `shared-data-foundation/1` (форма `DataScope`); объявлен полем `Authority` item'а | P5, P13 |
| P5.4 | Новые таблицы outbox/inbox вне FROZEN v1.0; объявлен полем `Authority` единственного DDL-item'а | P12, P14 |
| P5.16 | G-DDL-P5R: коррекция двух новых messaging migrations до configurable table identity; grant pending | P5.18, P6 |
| P15.1/P15.7/P15.8/P15.10 | G-API-P15: конечные публичные FQCN sets D85–D88 | соответствующие items и потребители |
| P10.3 | `sys_attribute_definitions` и `sys_attribute_backfills`; additive migration, без foreign DDL | P10.4 и миграция живых значений |
| P13.5 | Новая tenant-таблица `sys_api_idempotency` | P13.6 и mutation replay |
| P13.12 | Заморозка `operation-contract/1` | P14, внешние клиенты |
| P14.3 / P14.19 | Новые shared-budget/tenant таблицы; activation policy webhook secrets. Live sends/rotation отдельно, не разрешены дизайном | P17 |
| P17.12 | G-RELEASE: exact `1.0.0` publication packet, split/root refs and releases | завершение плана |

## 7. Отложенное (вне 1.0)

| Пункт | Почему отложено | Условие возврата |
|:--|:--|:--|
| GraphQL-транспорт (D7) | Владелец уточнил: упора нет, возможно не будет использоваться вообще. Задел — только транспорт-нейтральность контракта операции, отдельной работы нет | Внешний потребитель, которому недостаточно REST+bulk, и оплаченная стоимость поддержки схемы |
| Выделение `operations-http`/`operations-tools` в пакеты (S34) | Нет dependency pressure | Тяжёлая транспортная зависимость, которую не хочет ставить часть установок |
| Выделение `corex/money`, `corex/features`, `corex/settings` и др. в отдельные пакеты (A30/D8) | Один-два файла на пакет. Money — отдельная строка P6.4; Features/Settings пакетом не становятся | Отдельный release lifecycle или внешний потребитель без остального ядра |
| Выделение `corex/money`: условие — внешний или boxed-потребитель, которому нужен `corex/core` без `brick/money`, и оплаченная стоимость split-зеркала. | №3 (`CastsAttributes`); `brick/money` уже в `require` ядра | внешний или boxed-потребитель, которому нужен `corex/core` без `brick/money`, и оплаченная стоимость split-зеркала |
| Вынесение Departments на уровень 1 (FlexCMS): условие — второй продукт без оргдерева, либо подтверждённое расхождение смысла «отдел» между auth и CRM. До условия зона остаётся в ядре. | №2, №3; оргдерево с path/cycle/workspace; не пакет (2 файла, D8) | второй продукт без оргдерева, либо подтверждённое расхождение смысла «отдел» между auth и CRM; до условия зона остаётся в ядре |
| Каталоги `packages/capabilities|integrations` (A24) | Дороже пользы при 20 path-репозиториях | Радикальный рост числа пакетов |
| Остаток сравнительного прохода по API (GitHub, Slack, Twilio, HubSpot, Notion, Contentful) | Несущие решения уже подтверждены семью платформами | Research-item внутри P13 |
| Гарантия порядка доставки сообщений и колонки `ordering_key`/`sequence_no` (D28) | `SKIP LOCKED` выбран ради пропускной способности; per-key порядок — отдельная машинерия с собственными pg-тестами на конкуренцию | Первый контракт, для которого переупорядочивание меняет бизнес-исход |
| Плоскость управления relay по многим аккаунтам: каталог, fairness, backoff, ограниченная глобальная конкурентность (D27, D48) | P9 назначил owner `Tenancy/Runtime`, но строить scheduler/DDL без SLO и измеренного operator pressure было бы спекулятивно | Наблюдаемое голодание, нарушение delivery SLO или ручной fan-out выше operator budget |
| Jitter в backoff outbox | На аккаунт приходится один relay, сходящегося стада нет; детерминизм проверяется тестом | Первый деплой с несколькими relay-воркерами на один аккаунт |
| Шифрование полей сообщений, crypto-shredding, tombstone-события стирания | Стирание без жизненного цикла ключей и инвентаря потоков данных — обещание сильнее факта | Требование стирания с назначенным владельцем ключей |
