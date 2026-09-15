---
title: "P6 — карта реализации: блюпринт → репа/пакет → план → зависимости"
type: finding
plan: FLEX-2
phase: P6
created: 2026-07-13
authoring_model: opus/high
---

# P6 — карта реализации волны (findings)

Свод рекогносцировки 2026-07-13 (plan-design FLEX-2 P6). Источник для `Inputs`/`Required Reads`
всех items фазы P6: исполнителю НЕ нужно реконструировать «где живёт код» по девяти блюпринтам.

## 1. Факт-база (состояние на 2026-07-13)

| Факт | Значение | Источник |
|:--|:--|:--|
| Репа `corex` | 4 пакета: `core`, `crm`, `tasks`, `auth` (частичная реализация) | `~/projects/packages/corex/packages/` |
| План `COREX-CORE` | 26 items, **0/26 исполнено**; P1 (B-10) 0/13, P2 (B-11) 0/13. План детализирован (D1–D26, pre-mortem, аудит-фиксы F6–F16) | `corex/plans/COREX-CORE/plan.md` |
| Теги corex | `git tag -l` пусто (нет ни одного релиза) | repo |
| PHP-констрейнт corex | `^8.3` | `corex/composer.json` |
| `DB_SCHEMA.md` | `Status: DRAFT`, §2 покрывает только волну 1; конфликты C1–C6 не разрешены | `05-Projects/01-Products/FlexCRM/plans/flex/60_contracts/DB_SCHEMA.md:5` |
| Репы OSS-выносов | `flex/workflow`, `flex/attributes`, `flex/import-export` — **не существуют** | `~/projects/packages/` |
| App-репа FlexCRM | не существует; `~/projects/flexcrm` — legacy-референс (read-only, E1) | `~/projects/flexcrm` |
| Спутники | `chatom`, `vaulter` — репы есть (Vaulter: `@vaulter/vue` Drive-компоненты shipped) | `~/projects/packages/` |

## 2. Карта приземления: блюпринт → дом кода

| B | Модуль | Дом кода | Префикс / таблиц | Статус дома |
|:--|:--|:--|:--|:--|
| B-10 | примитивы + модульная система | `corex/core` + новый `flex/modules` (репа corex) | `sys_` 6 · `mod_` 3 | план COREX-CORE P1 |
| B-11 | tenancy + auth | новый `corex/tenancy` (обёртка stancl v4) + `corex/auth` | `root_` 17 · `wsp_` 3 · `aut_` 3 | план COREX-CORE P2 |
| B-12 | workflow-движок | OSS-репа `flex/workflow` (**не создана**) | `wfl_` | нет плана |
| B-13 | CRM + задачи (ядро) | пакеты `corex/crm` + `corex/tasks` (репа corex, каркасы есть) | `crm_` ~24 · `tsk_` 8 | нет плана |
| B-14 | commerce-DB | app-слой FlexCRM ИЛИ общий пакет — решает B-14 | `com_` 50 | нет плана |
| B-15 | кастом-поля / EAV | OSS-репа `flex/attributes` (**не создана**) | `fld_` 9 (+`cst_<slug>`) | нет плана |
| B-16 | satellite-контракты + инфра-слой | `corex/panels`, `corex/notifications`, `corex/media` (**не созданы**) + стыки Vaulter/Chatom в app | `pnl_` 1 · `ntf_` 4 · `med_` 3 · `vlt_` 8 · `cht_` 6 | нет плана |
| B-18 | import/export + миграторы | OSS-репа `flex/import-export` (**не создана**) | — | нет плана |
| B-27 | tasks-collab | **аддитивная дельта** `corex/tasks` (существующий DDL не переопределяется, B-27 §8) | `tsk_` +7 | — |
| B-28 | соц-контур | новый пакет `corex/social` (вендор-нейминг открыт, B-02 §4 п.6) | `soc_` 11 | — |
| B-29 | календарь | новые `corex/calendar` + `corex/rrule` (общий RRULE-обвяз с `corex/tasks`) | `cal_` 12 | — |
| B-30 | маркетинг | **ОТКРЫТО** (Q52): §1 — проприетарный домен FlexCRM, AC-14 — `corex/marketing` | `mkg_` 12 | арбитраж P6.2 |
| B-31 | process-studio | проприетарный модуль FlexCRM (`FlexCrm\Studio`/`FlexCrm\SmartProcess`) + контракт `SavedViewRepository` → `corex/core` | `wfl_process_templates` · `sys_saved_views` | — |
| B-32 | agentic surface | новый пакет `corex/ai` (дом норматива N1–N12 + runtime) + app-слой MCP | `ai_` 11 | — |
| B-33 | provisioning / multi-DB / файлы | дельта `corex/tenancy` (**0 новых таблиц**, 1 expression-unique-индекс) + **control-plane вне packages/corex** | — | дельта COREX-CORE + ops-майлстоун |
| B-34 | кросс-БД identity | дельта `corex/tenancy`/`corex/auth` (**0 новых таблиц в v1**) + **central IdP-приложение** (проприетарное, COREX-CORE D7) | — | дельта COREX-CORE + IdP-майлстоун |
| B-35 | i18n / гео / валюты | новый пакет `corex/intl` — **Decision pending Q70** | `geo_` 3 · `fx_` 1 | арбитраж P6.2 |
| B-36 | учёт рабочего времени | app-модуль FlexCRM `flex/attendance` (**build-new**, не `corex/*` — Rule of Three не набран, B-36 §3.0) | `att_` 7 | — |

## 3. Что НЕ покрыто планом COREX-CORE (требует дельта-фазы)

Проверено по `corex/plans/COREX-CORE/phases/P1.md` + `P2.md` (заголовки 26 items):

**B-33 — не покрыто:** контракт `TenantStorage` и tenant-storage-абстракция (§3.9, Q63); saga-обвязка над каноном P2.3 — идемпотентность по `signup_key` + expression-unique-индекс (§2.4) + компенсации по шагам (§3.2); control-plane целиком (ACME/lego §3.4, docker-ячейки §3.5, autovacuum + soft-limit 700 БД §3.6, матрица носителей §3.8, выбор RU-S3 §3.10, бэкап/`files_archive`/dual-write §3.11, перенос tenant'а между ячейками §5.6, MCP `tenancy.*`/`storage.*`/`tls.*` §7, ledger `usageBytes` Q61).
**B-34 — не покрыто:** `MembershipService` + central-API мутаций membership (`client_credentials`, `actingIdentityId`, `CentralApiClient` §4.2); `SwitcherService` + `id.flex.ru/api/me/accounts` + versioned-кэш `switcher:version:{identity_id}` (§3.4/§4.1); события `MembershipGranted/Revoked` + kill tenant-сессий ≤60 с (Q67); нормативы Н1–Н4 (запрет авто-merge по email/телефону — кандидат в канон B-11 §7.3, Q64); permission key `wsp.member.remove` (§3.10); сам IdP-сервер.
**B-35 — покрытие нулевое** (grep `geo_`/`fx_`/`intl`/`translat` по phases/ пуст). Фундамент есть: `sys_settings` (P1.7), golden-шаблон (P2.3), fan-out fleet-джобов (P2.12), `brick/money` уже в `packages/core/composer.json` (`^0.9||^0.10`) + `MoneyCast`.
**B-36 — покрытие нулевое и не требуется** (продуктовый модуль вне `packages/corex/**`, правило разделения COREX-CORE §1).

## 4. Блокеры и гейты (жёсткий порядок)

```
P6.1 DB_SCHEMA → FROZEN ─┐  (migration-items ядра не финальны при DRAFT — COREX-CORE §1)
P6.2 арбитраж Q52/Q70/Q64 ┤
                          ↓
COREX-CORE OQ-5 (stancl master требует php ^8.4, corex держит ^8.3) → P2.1 PoC-гейт stancl v4 (7 сценариев, GO/NO-GO)
                          ↓
P6.3 COREX-CORE P1 (B-10) → P6.4 COREX-CORE P2 (B-11) → P6.5 дельты B-33/B-34
                          ↓
P6.6 flex/workflow (B-12) ── блокирует B-27, B-29, B-30, B-31, B-36 (wakeup + FSM; свой cron ЗАПРЕЩЁН)
P6.7 flex/attributes (B-15) ── блокирует B-30 (сегменты), B-31 (`create_entity` → `cst_`)
                          ↓
P6.9 corex/panels+notifications+media (B-16 инфра) ── блокирует B-28, B-29, B-36
P6.10 corex/crm + corex/tasks (B-13 ядро) ── блокирует B-27, B-30, B-31, B-36
                          ↓
модули волны 2 (B-27 → B-28 → B-29 → B-30/B-31 ∥) → B-32 corex/ai (потребляет §7-карты тулзов ВСЕХ модулей — последний)
                          ↓
app-слой (setup → B-13/B-24 → B-14 ∥ → B-31 → B-36 → MCP v1) → спутники (Vaulter → Chatom)
```

Мягкий цикл B-31↔B-32 (студия «мягко зависит» от `corex/ai`: ai-узлы не публикуются без него) разрывается тем,
что B-32 §7 — карта тулзов (документ), а ai-узел в студии отключается валидатором.

## 5. Внешние факты (RAG-статус)

| Факт | Статус |
|:--|:--|
| `stancl/tenancy` v4 — стабильного тега нет, пин `dev-master#<sha> as 4.0.0`, jobpipeline `2.0.0-rc7`; форк-зеркало обязательно (COREX-CORE D25) | RAG:✅ R-31 §2.1 (2026-07) + COREX-CORE `findings/P1-P2-rag-verification-2026-07-12.md` |
| stancl master требует `php ^8.4`, corex держит `^8.3` — предусловие PoC-гейта | RAG:✅ COREX-CORE OQ-5 (2026-07-12) |
| `laravel/ai` + `laravel/mcp` — пин v0.8.2 (без elicitation) | RAG:✅ B-32 §П6 (`B-32:482`) |
| pgvector `vector(1024)` HNSW cosine; embeddings USER-bge-m3 (TEI) | RAG:✅ B-32 §3 (`B-32:220`) |
| `@vue-flow/core` MIT, ветка 1.4x (канва студии, за адаптером) | RAG:✅ B-31 (`B-31:208`) |
| `brick/money` (moneyphp отвергнут), `symfony/intl` + ICU, `spatie/laravel-translatable` (jsonb) | RAG:✅ R-33 §2.1/§3.1 + B-35 |
| `rlanvin/php-rrule`; `sabre/dav` (CalDAV RFC 6578 v1); Google/MS Graph — v1.1; Nylas/Cronofy — отказ | RAG:✅ R-26 + B-29 |
| Unisender (ESP v1) + SMTP self-hosted (boxed-дефолт), RFC 8058 one-click | RAG:✅ R-27 + B-30 |
| PG-расширения волны: `btree_gist` (анти-double-booking B-29), `pgvector` (B-32) — требование к боксовой поставке | RAG:✅ B-29/B-32 |
| Точные версии пакетов B-35 в блюпринте НЕ пинятся (только имена) — верифицировать на scaffold-item'е | [UNVERIFIED] — освежить `verify-claims` при старте P6.14 |
| Лимиты бакетов/ABAC RU-S3-провайдеров (Q33); объём ГАР верхних уровней (Q62/`geo_cities`) | [UNVERIFIED] — PoC-гейт B-11 Прил. A / P6.25 |

## 6. Pre-mortem (Klein, high-stakes: tenant-isolation × деньги × ядро экосистемы)

«2027-01: реализация FLEX-2 провалилась — почему?» Причины → кластеры → митигации (owner = item):

| # | Причина провала | Кластер | Митигация (owner) |
|:--|:--|:--|:--|
| A1 | stancl v4 так и не зарелижен, pin-sha протух force-push'ем, PoC отложили «на потом» — tenancy переписывается на середине волны | supply-chain | COREX-CORE D25 (форк-зеркало) + P2.1 как ПЕРВЫЙ блокирующий item; P6.4 не стартует без GO (**Validation P6.4**) |
| A2 | php `^8.4` vs `^8.3` вскрылся на этапе кода, а не гейта — вся волна ждёт bump'а зависимостей | supply-chain | OQ-5 закрывается ДО P6.4 (предусловие в Implementation Rules P6.4) |
| B1 | DB_SCHEMA остался DRAFT: каждый пакет заморозил свой префикс сам → 3 разных `tsk_`-семантики, миграции не сходятся в golden-шаблоне | контракт | **P6.1 блокирующий**: FROZEN ядро-периметра + реестр префиксов волны 2 до старта кода |
| B2 | Q52/Q70 не арбитрированы: маркетинг начали в app, потом переносили в `corex/marketing` (или наоборот) — двойная работа | контракт | **P6.2** арбитрирует дом ДО scaffold'а (Validation P6.14/P6.16 — ссылка на D-запись) |
| C1 | Каждый модуль завёл свой cron/шедулер (запрет B-12 §3.5 проигнорирован) — рекуррентность и напоминания расходятся | архитектура | Code Guidance P6.11–P6.13/P6.21: свой cron ЗАПРЕЩЁН, только sweep-хуки `flex/workflow`; арх-тест в каждом пакете |
| C2 | `corex/ai` начали до §7-карт модулей — норматив N1–N12 задним числом ломает уже написанные тулзы | архитектура | Порядок: P6.15 (corex/ai) — ПОСЛЕ модулей волны; Validation — карты §7 всех модулей существуют |
| D1 | Программная фаза «исполнялась» как обычная: оператор пытался `plan-exec FLEX-2 P6.x` и правил чужие репы из vault | процесс | D14: `plan-exec` запрещён на делегатах (protocol §11); каждый item несёт готовую launch-команду целевой репы |
| D2 | Майлстоуны помечались 🟢 по «почти готово» без GREEN-аудита делегата — доска врёт, зависимые items стартуют на песке | процесс | Единый Validation-канон делегата: `task:plan-audit <PLAN> <Pn>` = GREEN + composer-гейт + semver-тег |
| E1 | Ядро тегнули 1.0 до обкатки потребителем — breaking-правки контрактов стали дорогими | релиз | COREX-CORE D17: pre-1.0 (0.y.z) до обкатки app'ом; теги только на GREEN-закрытии фазы |
| E2 | Control-plane (ACME/S3/ячейки) не сделали — облачный онбординг клиента невозможен, при «готовом» продукте | скоуп | P6.25 — явный майлстоун на доске (не «инфра как-нибудь потом»); зависимость от `corex/billing` названа блокером |
</content>
</invoke>
