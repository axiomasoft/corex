---
type: workflow
id: D6
date: 2026-09-12
status: accepted
item: P3.1
items: [P3.1]
supersedes: []
superseded_by: null
---

# D6 — Forward corrections: evidence accepted, owner choices pending

**Date:** 2026-09-12
**Status:** accepted
**Actor:** gpt-5.6-sol/high · plan-run
**Evidence:** RAG:— D1/D2/D5; current worktree based on `8b11672a1e1f14df0157404d041ce4e8f0aa2a23`, including predecessor changes present before this invocation. D2 ADR-001/003/004 and DB_SCHEMA hashes reverified 2026-09-12 without discrepancy. Additional Brain hashes: Architecture `094ab1e395a5cf8f53ce181ee17ad91ad2d5a12972b0b3ad8188227321ff4f47`; Process `f14bfcd88dfcd2b5652521061092fa24ef55541716fb1dda069d3cd5ca759fb5`; Governance `a9dc473ed6082d1e2970d7da9bf56843443d03d37fcca1f3736a81dfd5c82983`. RAG:✅ authenticated GitHub run/job API rechecked 2026-09-12; links below.
**Supersedes:** —
**Superseded By:** —

## Решение

Принять полноту доказательств и предложения для владельца. `accepted` здесь относится только к evidence/proposal; Dmitry Vostrikov не выбрал нормативную ветку. Никакого разрешения на изменение источников, floor, runtime или Contracts запись не даёт.

## Почему

Минимальная factual correction устраняет ложные execution premises, сохраняя отдельно policy target и проверенную compatibility boundary. Полная ревизия архива/ADR и повторное поднятие 55→80 не нужны.

## Source / old / current / proposed / proof / owner

| Источник из D2 | Старое или конфликтующее | Текущее repository evidence | Точная предлагаемая поправка | Проверка после будущего approval | Владелец |
|:--|:--|:--|:--|:--|:--|
| Architecture §1; Process §2/DoD; Governance §2 | policy 85; passport example 80 с ошибочной ссылкой на Process | `scripts/coverage-min-floor.txt` первая строка 80; `composer.json` test:coverage `--min=80` | Effective enforced baseline 80 и target 85 разнести; пример паспорта называть baseline с явной approved exception | Одинаковые определения metric/floor/target в трёх документах; floor и команды остаются 80 | Dmitry, CoreX normative owner |
| D2 ADR-001 поправка 2026-07-03 | core PHP 8.4–8.5 против current brief 8.3–8.5 | root/core/modules/auth/tasks `^8.3`; tenancy `^8.4` | L0 core baseline 8.3–8.5, L12–13; tenancy scoped 8.4–8.5; product target отделить | Manifest/CI/docs comparison, без удаления 8.3 job или constraint | Dmitry, ADR-001 owner |
| Architecture §1; Process §2 | PHP 8.2–8.4 × L11/12 | CI root 8.3/8.4/8.5 × L12/13; PHP8.3 removes tenancy | Матрица по пакетам/профилям вместо старого универсального примера | Auth L12 отдельно; tenancy L12/L13 local D4 отдельно от live proof | Dmitry, CoreX docs owner |
| D2 DB_SCHEMA §2/§3.1 | catalogue `root_` =18 | §3.1 содержит 20 headings; tenancy root migrations создают 17 `root_` tables | Catalogue 20; tenancy-owned implemented subset 17; ownership остальных явно назвать | Count headings и distinct Schema::create; schema/DDL без изменений | Dmitry как approval owner; FlexCRM frozen DB_SCHEMA source owner подтверждает correction |
| D2 DB_SCHEMA; core PubSub | `sys_pubsub_messages` отсутствует в catalogue | `packages/core/database/migrations` содержит driver-specific pubsub table | Отдельная driver-local optional table note, не универсальная обязательная tenant schema | Проверить конфиг драйвера и owning migration; никакой ad hoc migration | Тот же foreign frozen-schema gate |
| D2 ADR-004 §Границы с соседними ADR | TODO: ADR-003 ещё ULID | D2 ADR-003 уже accepted UUIDv7; `packages/core/src/Models/BaseModel.php` uses HasUuids | Удалить только stale TODO и сослаться на accepted UUIDv7 | Сверить ADR-003 и BaseModel; не переписывать tenancy ADR | Dmitry, ADR-004 owner |
| `docs/dev-doctrine.md` §6/§7 | floor55, ADR не переписаны, billing/no live CI | floor80; revised vault ADR; исторический run имеет реальные successful jobs | Обновить factual statements с датой/SHA и точными ограничениями | Не объявлять весь run GREEN: auth install failure; mutation skipped | Dmitry, repository doctrine owner |
| Doctrine §6; D1 | local matrix versus old live run | D4 новый tenancy matrix только local; D5 positive record proof только local | Отдельные local receipts и historical runner evidence | Новая CI конфигурация не выдаётся за completed runner execution | Dmitry |

Три таблицы field-level catalogue без owning create migration в tenancy: `root_plans`, `root_subscriptions` (billing), `root_wfl_wakeups` (workflow B-12). `root_usage_snapshots` семантически принадлежит billing по DB_SCHEMA, но её create migration фактически есть в tenancy; creation ownership и domain ownership следует подписывать раздельно. Это observation о текущем дереве, не разрешение переносить таблицу или billing в L0 и не изменение замороженной схемы.

Historical runner: [run 30329663500](https://github.com/axiomasoft/corex/actions/runs/30329663500), SHA `f95371624e90b921753295a3f58bb47f5d0b2af3`; root six legs/PG/coverage/quality/layer/tenancy/crm/tasks/anti-weakening successful. Run conclusion **failure**: [auth job 90183015440](https://github.com/axiomasoft/corex/actions/runs/30329663500/job/90183015440) failed Install dependencies. Mutation job `90183015842` skipped. Это не доказательство GREEN текущего worktree. Coverage 88.3% — исключительно historical P1.27 measurement; текущий coverage здесь не измерен. D5 не решает role writer или release install.

## Exact owner choices

1. **NORM-COVERAGE:** «Одобряешь effective floor80 с отдельным target85 и явным обоснованием exception в Governance, либо нормативно меняем target на80?» Рекомендация — первое. Оба варианта меняют только normative wording. Повышение executable floor до85 — третья, отдельная будущая задача с новым measurement и approval; здесь запрещено. Affected: Architecture/Process/Governance/doctrine. Acceptance: единые определения и нулевая executable delta. Rollback: revert только approved docs patch, floor80 сохраняется.
2. **NORM-L0-STACK:** «Одобряешь PHP8.3–8.5/L12–13 для core L0, scoped PHP8.4 для tenancy и отдельную product target matrix, либо сохраняем ADR core8.4 и заводим отдельный compatibility-change spec?» Рекомендация — первое, current brief сохраняет 8.3 execution limit. Auth L13 не обещать. Gate: Dmitry/ADR-001; если выбран drop8.3, до отдельной разрешённой задачи ничего не менять. Acceptance: package/profile table совпадает с constraints и proof classes. Rollback: docs-only revert; никакого upgrade/downstream break.
3. **NORM-FORWARD-SOURCES:** «Разрешаешь перечисленные factual forward corrections в doctrine, stale ADR-004 TODO и DB_SCHEMA catalogue20/tenancy17 + driver-local pubsub note, с подтверждением FlexCRM frozen-source ownership и разрешением docs conflicts, либо оставляем их зарегистрированными до отдельного согласования источников?» Рекомендация — approved minimal correction. Gate: Dmitry плюс source ownership для frozen DB_SCHEMA; CoreX approval не заменяет foreign schema consent. Acceptance: точный diff по строкам таблицы, counts и source hashes, `brain status`/конфликт-решение только в отдельном authorized docs invocation. Rollback: revert approved source edits и соответствующего docs sync; code/schema не затрагиваются.

## Deferred private diagnostics

Private B/AC references в runtime exceptions/PHPStan diagnostics — optional отдельный consumer/DX choice, не обязательная correction и не новая фича. D1 отличает их от уже выполненного docblock sweep. Если владелец запросит wording cleanup, будущая задача должна перечислить exact-message tests и release semantics; альтернативой остаётся сохранение текущего wording. Здесь нет запроса разрешения на такой delta и нет изменения диагностик. Registry why/MCP, Move/Rename и год-2 features не становятся deliverables.

## Acceptance and residuals

Три точных запроса находятся в `queue.jsonl`, schema/provenance проверяются `task.plan_requests.states()` и owned generators. Нет owner response: все implementation gates остаются pending. D2 meanings/hashes сохранены; normative/product sources и historical archive неизменны относительно pre-invocation baseline. Техническое закрытие P3.1 означает decision-ready evidence, не policy amendment и не runtime delivery. После owner response нужен новый owner-provenance D# и отдельный детальный executable item.
