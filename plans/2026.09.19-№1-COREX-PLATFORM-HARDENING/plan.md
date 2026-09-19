# 2026.09.19-№1-COREX-PLATFORM-HARDENING — План №1 — Платформенное доведение CoreX до коммерческого 1.0

## 0. Meta

| Поле | Значение |
|:--|:--|
| Plan ID | 2026.09.19-№1-COREX-PLATFORM-HARDENING |
| Short ID | PLAN2 |
| Title | План №1 — Платформенное доведение CoreX до коммерческого 1.0 |
| Layout | v2 |
| Version | 0.1.0 |
| Document Type | Executable Master Plan |
| Authoring Model | claude-opus-5/high (верхний слой, 2026-09-19, provider-attested) |
| Repository | corex |
| Related Packages | все 21 каталога `packages/*`; нормативные документы `docs/architecture/**`; корневые гейты `scripts/**`, `tests/Arch/**`, `.github/workflows/**` |
| Execution Mode | phase-first: верхний слой + скелеты 17 фаз; каждая фаза детализируется отдельным `task:plan-design <ID> Pn` перед исполнением |
| Target Operator Classes | frontier/high (детализация фаз, дизайн-решения, аудит) · implementation/medium–high = **Composer 2.5** (исполнение items) |
| Approval Owner | Dmitry Vostrikov |
| Home | repo:corex (локальный план; Brain sync не выполнялся) |
| visibility | private |
| supersedes | — |
| Paused By | — |

## 1. Context

Задача — превратить CoreX из «набора работающих пакетов» в **сознательно спроектированную
платформу уровня 0**, пригодную к коммерческой эксплуатации в продакшене и к длительному
использованию FlexCRM/FlexCMS как потребителями, не владельцами ядра.

Вход — два внешних аудита, приложенных владельцем: `audits/2026-09-19-perplexity-3.md`
(архитектурный overlay второй итерации) и `audits/corex-api-shopify-analysis.md`
(API крупных платформ). Оба разобраны построчно: каждая идея либо подтверждена чтением кода
и внешним первичным источником, либо принята с изменением, либо отвергнута. Матрица —
`findings/audit-verdicts.md`, измеренная база — `research/00-baseline-metrics.md`, внешние
подтверждения — `research/perplexity-2026-09-19.md` (19 успешных запросов; первичные источники
Shopify, Stripe, GitLab, Atlassian, Grafana, Salesforce, Symfony, Drupal, Shopware, Laravel,
PostgreSQL, Standard Webhooks, OpenTelemetry).

Аудиты имеют статус **non-normative сырья**: в них есть ложные предпосылки (например,
`RecordRules` в `corex/auth` не существует; `corex/search-intl` не владеет SQL-репозиторием
saved views; разделение provider/hydration в поиске уже выполнено) и предложения, вредные
именно для self-hosted продукта (date-based версии внешнего API как основной механизм
совместимости). Копировать код и формулировки из `audits/*` запрещено — нормативен только
текст, произведённый фазами этого плана.

Сверх аудитов план несёт собственные находки, которых нет ни в одном из них
(`findings/audit-verdicts.md` §5, префикс `N`): отсутствие инъектируемого Clock при 151 вызове
`now()`, неполный контур `composer test:packages` (17 пакетов из 21), корневой PHPStan на 7
пакетах из 21, `extra.corex` лишь в 9 пакетах и только с ключом `module`, полное отсутствие
outbox и webhook-поверхности, пакет `packages/api` без собственной регистрации маршрутов.

План завершается измеримым Definition of Done 1.0: назначение каждого пакета — одно
предложение, публичная поверхность под автоматическим BC-гейтом, у каждой таблицы владелец,
конфигурация типизирована и валидируется, durable-сообщения сериализуемы и версионированы,
внешний API — самостоятельный продукт с профилями версий, идемпотентностью, курсорной
пагинацией и стабильными кодами ошибок, а универсальность доказана внешними extension-фикстурами
и не-CRM потребителем.

## 2. Execution Rules

- Один item за раз; статус живёт в generated state, фаза закрывается по фактическим receipt'ам.
- Статусы — 6 канонических строк байт-в-байт (plan-protocol §4). GREEN только при зелёных
  Validation-носителях; пропущенная или недоступная проверка раскрывается, а не считается зелёной.
- **Исполнитель items — модель класса implementation = Composer 2.5.** Поэтому каждая item-спека
  ОБЯЗАНА называть точный параграф `research/Pn-design.md` с готовым нормативным текстом или
  готовым кодом. Спека без такого указателя — дефект дизайна, а не задача на импровизацию.
- **Бюджет контекста item'а — 200K токенов.** Жёсткие лимиты спеки: ≤14 Required Reads,
  ≤2500 строк обязательного чтения, ≤8 файлов в Files, один пакет (или один пакет + корневой
  гейт) на item. Не влезает — item делится при детализации фазы, а не при исполнении.
- Фаза детализируется отдельным вызовом `task:plan-design <ID> Pn` на frontier/high; скелет
  без детализации не исполняется. Детализация обязана произвести `research/Pn-design.md`,
  `research/Pn-design-rag.md` и item-спеки `phases/Pn/Pn.m.md`.
- **Perplexity (наследуется от PLAN1 D6):** CoreX — внутренняя разработка; у Perplexity не
  спрашивать про CoreX, его устройство, пути, гейты и состояние. Только общие практики,
  конкретные технические вопросы и публичные примеры, нейтральной формулировкой. Внешнее
  подтверждение несёт `RAG:✅`, факт о репозитории — `RAG:—`.
- Нормативные входы каждой фазы: `docs/architecture/**` (создаётся P1), `docs/dev-doctrine.md`,
  `findings/audit-verdicts.md`, `research/00-baseline-metrics.md`. Расхождение плана с кодом —
  эскалация, не импровизация.
- **DB-контракт `FROZEN v1.0`** (`~/Vaults/Brain/05-Projects/01-Products/FlexCRM/plans/flex/60_contracts/DB_SCHEMA.md`):
  ни один item не меняет существующие `packages/*/database/migrations/**` без отдельного owner-gate.
  Новые таблицы вводятся только там, где фаза это явно объявила, и сразу попадают в
  `docs/architecture/table-ownership.md`.
- Доктрина уровней (D70/D91) сохраняется: `corex/*` не зависит от `flex/*`, `corex/core` не
  зависит ни от одного пакета монорепо, flex-литералов в `packages/{core,modules,tenancy,auth,tasks}` нет.
- Закрытие code-item'а — оба лейна (D34): `composer lint:check && composer analyse && composer test`
  плюс `composer test:pg` для items, затрагивающих SQL-пути. Один sqlite-лейн = ложно-зелёное.
- Никаких массовых переносов каталогов до завершения P1–P3: сначала нормативы, измерение и гейты.
- Новые runtime-зависимости запрещены; dev-зависимости (BC-чекер, boundary-анализатор) вводятся
  только названными в решениях items и только в `require-dev`.
- После закрытия item/фазы — перезапись `handoff.md`; журнал append-only.

## 3. Routing

Верхний слой плана содержит **17 скелетных фаз и ни одного детализированного item'а**:
исполнение начинается только после детализации соответствующей фазы. Поэтому Routing
покрывает (а) все существующие единицы работы — 17 design-проходов, и (б) объявляет
обязательный route исполнения для items каждой фазы, который детализация обязана
воспроизвести построчно или ужесточить с указанием причины.

Ослабление объявленного route при детализации запрещено; ужесточение допускается
с записью причины в фазовый D-лог.

### 3.1 Существующие единицы работы — детализация фаз

| Batch | Items | Model class/effort | Exec | Review | Почему |
|:--|:--|:--|:--|:--|:--|
| solo | P1 | frontier/high | plan-design | full | Нормативный слой, от которого зависят все 16 остальных фаз; ошибка конституции воспроизводится в каждом последующем item'е. |
| solo | P2 | frontier/high | plan-design | light | Машинная инвентаризация: дизайн выбирает схему артефактов и сканеры, риск локален и обратим. |
| solo | P3 | frontier/high | plan-design | full | Исполняемые гейты: ошибочный гейт даёт ложно-зелёное по всему репозиторию — ровно этот дефект уже случался в PLAN1 P12.3. |
| solo | P4 | frontier/high | plan-design | full | Durable-граница и контекст исполнения: смена формы `DataScope` — публичный контракт и риск потери tenant-изоляции. |
| solo | P5 | frontier/high | plan-design | full | Новая messaging-основа (envelope, outbox/inbox) с гарантиями доставки; ошибка даёт молчаливую потерю событий. |
| solo | P6 | frontier/high | plan-design | full | Сокращение `corex/core`: 109 файлов, девять сторонних возможностей, публичные неймспейсы потребителей. |
| solo | P7 | frontier/high | plan-design | light | Типизированная конфигурация: механическая замена 152 вызовов `config()` по единому шаблону. |
| solo | P8 | frontier/high | plan-design | light | Декомпозиция composition roots без смены поведения; проверяется существующими пакетными сьютами. |
| solo | P9 | frontier/high | plan-design | full | Tenancy — 159 файлов, самый рискованный пакет; смешанная ось структуры и физическая DB-per-account. |
| solo | P10 | frontier/high | plan-design | full | Attributes: сменный источник определений, стабильная идентичность поля и протокол эволюции схемы поверх живых данных. |
| solo | P11 | frontier/high | plan-design | full | Модель расширения: compile → frozen реестры влияют на детерминизм воркеров и durable-решений. |
| solo | P12 | frontier/high | plan-design | light | Таксономия ошибок и observability: аддитивный слой поверх существующих контрактов. |
| solo | P13 | frontier/high | plan-design | full | Ядро внешнего API как продукта: версии, идемпотентность, пагинация, коды ошибок — публичный контракт с необратимой стоимостью ошибки. |
| solo | P14 | frontier/high | plan-design | full | Нагрузочный контур API: бюджеты стоимости, bulk-операции, webhooks — внешние необратимые эффекты и подписи. |
| solo | P15 | frontier/high | plan-design | light | Переименования по уже утверждённому реестру P1; риск сведён к полноте sweep'а. |
| solo | P16 | frontier/high | plan-design | light | Раскатка профилей структуры по остальным пакетам по уже утверждённым правилам P1. |
| solo | P17 | frontier/high | plan-design | full | Доказательства универсальности и release-гейты 1.0: приёмка, от которой зависит коммерческое обещание. |

`Batch = solo` для каждого design-прохода: фазы имеют разные нормативные входы и разные
источники подтверждения, одна сессия на две фазы не экономит чтение и переполняет бюджет
контекста. Каждый проход — отдельный корень по `cold-start-root: design-root-terminal`.

### 3.2 Объявленный route исполнения items (обязателен для детализации)

| Фаза | Model class/effort items | Exec | Review по умолчанию | Исключения, требующие `full` |
|:--|:--|:--|:--|:--|
| P1 | implementation/medium | plan-exec | light | Конституция и api-policy — `full`. |
| P2 | implementation/medium | plan-exec | light | Ни одного: артефакты read-only. |
| P3 | implementation/high | plan-exec | full | Все items: гейт по определению может дать ложно-зелёное. |
| P4 | implementation/high | plan-exec | full | Все items. |
| P5 | implementation/high | plan-exec | full | Все items, кроме документа политики сообщений. |
| P6 | implementation/high | plan-exec | full | Перенос неймспейсов и публичных символов. |
| P7 | implementation/medium | plan-exec | light | Секреты и redaction — `full`. |
| P8 | implementation/medium | plan-exec | light | Порядок bootstrapper'ов tenancy — `full`. |
| P9 | implementation/high | plan-exec | full | Все items. |
| P10 | implementation/high | plan-exec | full | Все items, затрагивающие живые значения атрибутов. |
| P11 | implementation/high | plan-exec | full | Freeze-семантика и fingerprint в durable payload. |
| P12 | implementation/medium | plan-exec | light | Публичные коды ошибок — `full`. |
| P13 | implementation/high | plan-exec | full | Все items. |
| P14 | implementation/high | plan-exec | full | Все items; webhook-подписи и ротация секретов — плюс security-review. |
| P15 | implementation/medium | plan-exec | light | Переименование класса модуля с миграцией `mod_*` — `full`. |
| P16 | implementation/medium | plan-exec | light | Ни одного, если поведение не меняется; доказательство неизменности обязательно. |
| P17 | implementation/high | plan-exec | full | Все items. |

## 4. Update Log

| Дата | Кто (role/model) | Что |
|:--|:--|:--|
| 2026-09-19 | plan-designer/claude-opus-5/high | План создан: верхний слой, 17 скелетных фаз, матрица вердиктов по двум аудитам, измеренная база по 21 пакету, 19 внешних подтверждений, решения D1–D9. Items не детализированы, исполнение не начато. |

## 5. Decision Log

| D# | Дата | Решение | Почему |
|:--|:--|:--|:--|
| D1 | 2026-09-19 | Сначала нормативы и измерение (P1–P3), только потом любые переносы и переименования. | Без конституции локальные улучшения расходятся; аудит прямо требует не переносить каталоги раньше семантических решений. |
| D2 | 2026-09-19 | Внешний API версионируется семантическим мажором в пути (`/api/v1`) плюс capability-negotiation; date-based `YYYY-MM` как основной механизм совместимости отклонён. | Self-hosted сервер и клиенты обновляются независимо; первичные источники (GitLab `/api/v4`, Atlassian REST API policy, Grafana `/apis`) показывают мажор + capabilities, а date-based нужен Shopify как SaaS с центральным контролем раскатки. |
| D3 | 2026-09-19 | Таксономия пакетов декларируется метаданными в существующем `extra.corex` (`package-type`, `stability`, `public-api`), без каталогов `packages/capabilities|integrations`. | `extra.corex` уже используется в 9 пакетах (ключ `module`); физический перенос усложняет path-репозитории и CI без практической выгоды. |
| D4 | 2026-09-19 | Публичная поверхность фиксируется машинно: `extra.corex.public-api` + `@api`/`@internal` под PHPStan + release-to-release BC-дифф, а не только рукописным списком символов. | Рукописный список расходится с кодом; PHPStan 2.1.13+ умеет enforcement `@internal`, а Roave BC-check (8.22.0, 2026-09-16) даёт реальный диф API между ревизиями. |
| D5 | 2026-09-19 | Реестры расширений компилируются в immutable snapshot с fingerprint'ом; дубликат идентификатора — ошибка компиляции, порядок детерминирован (priority DESC, id ASC). | В `packages/modules` уже есть compile (`CompiledRegistry`), но нет freeze и нет запрета дубликатов; Symfony/Drupal/Shopware показывают, что гарантии дают правила реестра, а не примитивы фреймворка. |
| D6 | 2026-09-19 | `DataScope` становится набором скаляров с версией плюс отдельным codec'ом; восстановление `TenantContext` — отдельная ответственность. | Сейчас `DataScope` несёт объект `TenantContext` (подтверждено чтением кода), то есть durable-граница пересекается container-shaped объектом. Радиус правки мал: производственная точка конструирования одна. |
| D7 | 2026-09-19 | GraphQL-транспорт не проектируется и не входит в 1.0: ни схемы, ни адаптера, ни зависимости. Задел исчерпывается транспорт-нейтральностью контракта операции, которая нужна и без GraphQL. | Владелец уточнил, что GraphQL может не использоваться вообще. Аудит сам запрещает делать его canonical; Lighthouse меняет vendor-пространство, ограничители сложности в `webonyx/graphql-php` выключены по умолчанию — цена поддержки не оплачена ни одним измеренным требованием. |
| D8 | 2026-09-19 | Немедленное выделение десяти пакетов из `corex/core` отклонено в пользу внутренних dependency-зон с arch-тестами; выделение пакета требует отдельного release-lifecycle обоснования. | Часть «возможностей» в ядре — один-два файла (`Money` = 59 строк, `Features` = 121 строка): отдельный пакет на них дороже пользы. Аудит и сам предлагает зоны как первый шаг. |
| D9 | 2026-09-19 | Bulk/long-running операции получают единый контракт (submit → poll → result → cancel → expiry) в слое операций, а исполнение делегируется `corex/transfer`; движок исполнения не дублируется. | Совпадает с границей из аудита и с публичными контрактами AIP-151, Salesforce Bulk API 2.0, Microsoft Graph LRO и Shopify bulk operations. |

Каталог решений: `decisions/`.

## 6. Contracts

### Consumed

| Контракт | Донор-план | Pinned | Замечено | Реакция |
|:--|:--|:--|:--|:--|
| `DB_SCHEMA.md` (FlexCRM `60_contracts`) | внешний, FlexCRM | FROZEN v1.0 | — | Существующие миграции не меняются; новые таблицы только с явным объявлением фазы. |
| `shared-data-foundation/1` | 2026.09.15-№1-COREX-FLEXCRM-REMAINING (P12.8) | v1 | Форма `DataScope` | P4 меняет контракт под отдельный owner-gate, с уведомлением потребителей. |
| Доктрина уровней D70/D91 | plans/archive/COREX-CORE | — | — | Сохраняется без изменений; `tests/Arch/LayerTest.php` остаётся обязательным гейтом. |

### Exported

| Контракт | Версия | Статус | Потребители | Уведомлены |
|:--|:--|:--|:--|:--|
| `docs/architecture/constitution.md` | v1.0.0 | draft (P1) | все фазы плана, FlexCRM | — |
| `docs/architecture/api-policy.md` | v1.0.0 | draft (P1) | P3, P13, P14, потребители внешнего API | — |
| `operation-contract/1` | v1 | planned (P13) | FlexCRM, внешние клиенты, MCP-инструменты | — |

## Обсуждение

### 1 — Имя пакета `corex/api`

- **(рекоменд.) Вариант A.** Оставить `corex/api` до конца P13 и переименовать в
  `corex/operations`, только если сформулированная одной строкой ответственность действительно
  «реестр и безопасный вызов операций», а не «HTTP API». Плюсы: имя меняется по факту, а не по
  гипотезе; один rename вместо двух. Минусы: до P13 имя остаётся неточным.
- **Вариант B.** Переименовать сразу в P1. Плюсы: словарь фиксируется раньше. Минусы: пакет
  опубликован в split-зеркале, а ответственность ещё не заморожена.

**Статус:** Decision pending (нужен владелец) — `open-questions.md` Q1.

### 2 — Имя пакета `corex/auth`

- **(рекоменд.) Вариант A.** `corex/access`: пакет содержит `AccessChecker`, `ScopePredicate`,
  матрицу ролей и record-level доступ, то есть отвечает в основном на вопрос «можно ли».
- **Вариант B.** Оставить `auth`: там же живёт `PrincipalUserProvider` (аутентификация), а
  доктрина уже фиксирует, что движком ролей владеет AzGuard, `corex/auth` — адаптер.

**Статус:** Decision pending (нужен владелец) — `open-questions.md` Q2.

### 3 — Судьба тумбстоуна `packages/import-export`

- **(рекоменд.) Вариант A.** Оставить каталог с одним `CHANGELOG.md` до 1.0 как исторический
  след ренейма, исключить из всех контуров и объявить это в конституции.
- **Вариант B.** Удалить каталог, перенеся запись ренейма в корневой `CHANGELOG.md`.

**Статус:** Exploration — `open-questions.md` Q3.
