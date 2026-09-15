# P6 — Координация реализации волны: пре-флайт → фундамент CoreX → пакеты/OSS → FlexCRM-app → спутники и облако

> **D49 — текущая граница всех открытых P6 items:** разрабатываем backend/пакеты/API. Frontend только запускаемая заглушка, авторизация и пустой dashboard; продуктовые экраны/UX/design и их визуальная acceptance вынесены за рамки FLEX-2. Исторический полный UI scope не исполнять автоматически, closed evidence сохранить. Подробности — decisions/D49-backend-first-frontend-out-of-scope.md.

> **Дельта D37 / 2026-09-10:** app-код теперь пишется в существующем flexcrm, стартовая полоса P9.1–P9.6. Новая app-репа и Inertia workspace отменены. Полная очередь ниже описывает продуктовую программу; она не задерживает compatibility/API/solution пилот отсутствующими маркетингом/социальной лентой. P6.17/P6.18 используют новую редакцию своих карточек.

## Phase Context

Программная фаза (protocol §11): items — **майлстоуны-делегаты**, а не код-задачи. Код живёт в
целевых репах (`corex`, новые репы OSS-пакетов, существующий flexcrm (D37/D38), спутники) и исполняется
ИХ командами (`task:plan-design` / `plan-exec` / `plan-audit` там). **`/task:plan-exec FLEX-2 P6.x`
запрещён для делегатов (P6.3–P6.26)** — D14; легален только для пре-флайт-items P6.1–P6.2
(vault-артефакты этого плана). Майлстоун закрывается `/task:plan-close` по факту GREEN-аудита
делегата (единый Validation-канон: `task:plan-audit <PLAN> <Pn>` = GREEN + composer-гейт целевой
репы + semver-тег/CHANGELOG затронутых пакетов).

Гейт «Запускай P6» **снят владельцем 2026-07-13** (D15). Скоуп — полное покрытие волны (D16):
фундамент B-10/B-11, OSS-движки B-12/B-15/B-18, пакеты волны 2 (B-27…B-32, B-35), продуктовый
слой B-13/B-24/B-14/B-31/B-36, спутники B-16, облачный контур B-33/B-34. Карта «блюпринт → репа →
пакет → зависимости» + pre-mortem — `findings/P6-implementation-map.md` (Required Read всех items).

Жёсткий порядок: **P6.1–P6.2 (пре-флайт) блокируют всё** (DB-контракт DRAFT ⇒ ни один migration-item
делегата не закрывается финально; Q52/Q70 не арбитрированы ⇒ дом двух модулей неизвестен) →
P6.3–P6.5 (фундамент, репа corex) → P6.6–P6.8 (OSS-движки; `flex/workflow` блокирует B-27/B-29/
B-30/B-31/B-36 — **свой cron/шедулер в модулях ЗАПРЕЩЁН**, только wakeup B-12 §3.5) → P6.9–P6.10
(инфра-пакеты + ядро CRM) → P6.11–P6.16 (модули волны 2; `corex/ai` — последний, потребляет §7-карты
тулзов всех модулей) → P6.17–P6.22 (app FlexCRM) → P6.23–P6.26 (спутники, control-plane, IdP).

## Scope Included

Майлстоун-трекинг реализации всех блюпринтов волны (B-10…B-18, B-27…B-36) в целевых репах;
пре-флайт-долги дизайна, блокирующие код (заморозка DB-контракта, арбитраж приземлений);
фиксация semver-тегов и GREEN-вердиктов делегатов на доске FLEX-2.

## Scope Excluded

Сам код (пишется в целевых репах их планами); гранулярные code-items (живут в делегатах);
FlexCMS/FlexStore-майлстоуны (stubs `05-Projects/01-Products/FlexCMS/plans/FLEXCMS`, `05-Projects/01-Products/FlexStore/plans/FLEXSTORE`, D4); модули deferred-квартета
(mail/HR/whiteboard/merge — волна 3, D7); биллинг-модуль `corex/billing` (внешний блокер P6.25,
свой план); пересмотр канона B-10…B-36 (правка блюпринта — только через D-запись, D11).

## DoR (Definition of Ready)

- [x] Все маркеры уточнений сняты (0 в фазе; литерал маркера здесь не пишется — линтер ловит его как живой).
- [x] Scope Included / Scope Excluded фазы заполнены.
- [x] Alternatives для несущих решений зафиксированы в D-логе (D14–D19).
- [x] Open Questions разнесены по статусам `open-questions.md` (Q52/Q64/Q70/Q74/Q75/Q80 → маршрут P6.2).
- [x] Несущие факты несут RAG-маркеры (`findings/P6-implementation-map.md` §5).
- [x] Оркестрация НЕ заявлена (D19): items исполняются командами ЧУЖИХ реп, workflow-скрипт FLEX-2 их запустить не может; свои workflow объявляют планы-делегаты.

## Актуальный следующий срез — D41 / 2026-09-13

P6.1–P6.4 закрыты. Делегат `2026.09.13-№1-COREX-TENANCY-IDENTITY` уже создан. Ближайший шаг
по D42 — waiting: новые owning DI/AP receipts и scoped D2 release;
без нового evidence повторный design не назначается; D2 не released for implementation. Свежая сверка —
`findings/P6.5-delegate-progress.md`; старый COREX-CORE P3 не запускается. Новые CoreX plans имеют собственный scope/authority gates.
Central writer/switcher server и provisioner остаются P6.26/P6.25.
P9–P13 подтверждают первый базовый контур и дают partial evidence P6.17/P6.18,
но не заменяют acceptance остальных доменов. Статус — owned `status.md` и
`P6.m.state.md`; этот design не закрывает milestone.

> **Phase Assurance | v1.**
> Impact: security-external
> Integration Seams: corex-package-central-consumer · flex2-delegate-provenance · nuxt-api-account-workspace-isolation
> Live or Irreversible: no
> Audit Intent: direct-close-eligible
> Rationale: программа принимает scoped delegate receipts и negative isolation checks; текущий design меняет только план. D14 acceptance gates сохраняются, отдельный audit требует qualified admission, production/release authority сюда не переносится.

## Item Index

| Item | Specification |
|:--|:--|
| P6.1 | [P6.1](P6.1.md) |
| P6.2 | [P6.2](P6.2.md) |
| P6.3 | [P6.3](P6.3.md) |
| P6.4 | [P6.4](P6.4.md) |
| P6.5 | [P6.5](P6.5.md) |
| P6.6 | [P6.6](P6.6.md) |
| P6.7 | [P6.7](P6.7.md) |
| P6.8 | [P6.8](P6.8.md) |
| P6.9 | [P6.9](P6.9.md) |
| P6.10 | [P6.10](P6.10.md) |
| P6.11 | [P6.11](P6.11.md) |
| P6.12 | [P6.12](P6.12.md) |
| P6.13 | [P6.13](P6.13.md) |
| P6.14 | [P6.14](P6.14.md) |
| P6.15 | [P6.15](P6.15.md) |
| P6.16 | [P6.16](P6.16.md) |
| P6.17 | [P6.17](P6.17.md) |
| P6.18 | [P6.18](P6.18.md) |
| P6.19 | [P6.19](P6.19.md) |
| P6.20 | [P6.20](P6.20.md) |
| P6.21 | [P6.21](P6.21.md) |
| P6.22 | [P6.22](P6.22.md) |
| P6.23 | [P6.23](P6.23.md) |
| P6.24 | [P6.24](P6.24.md) |
| P6.25 | [P6.25](P6.25.md) |
| P6.26 | [P6.26](P6.26.md) |

## Phase Handoff

Rolling recommendation — `handoff.md`; phase result — generated `status.md`.

## Независимые app-срезы — D43

Жёсткий порядок выше применяется к приёмке зависимых пакетов и полного
продукта. По D43 локальный код P6.18 на уже принятых contracts развивается
параллельно внешней сессии CoreX. Tasks workbench использует существующий
P12-контур; он не требует новых B-33/B-34 contracts и не закрывает P6.5/P6.18.
