# Индекс плана

Карта файлов и провенанс-связи. Не носитель статуса (`status.md`) и не носитель «куда
дальше» (`handoff.md`).

**Обновлён:** 2026-09-23

## Карта директорий

| Путь | Назначение |
|:--|:--|
| `plan.md` | Стабильное ядро: Meta, Context, Execution Rules, §3 Routing, §7 Contracts |
| `execution-batches.md` | Живой маршрут P7–P17: batch → items → model, прямые входы/выходы, контрольные точки D118 |
| `execution-commands.md` | Проверенный Cursor CLI launch list: 83 исполнения P7–P17 и команда архивирования после P17.12 |
| `status.md` | Генерируемая проекция статуса фаз и items |
| `phases/<Pn>/<Pn>.md` | Стабильный Phase Context и индекс items фазы |
| `phases/<Pn>/<Pn.m>.md` | Стабильная спецификация item'а (11 полей) |
| `phases/<Pn>/<Pn.m>.state.md` | Генерируемое состояние item'а |
| `bundles/<Pn.m>.json` | Упорядоченные входы исполнителя с digest'ами |
| `decisions/` | Решения D1–D118 плюс генерируемые виды; D118 заменяет future pins D112/D114 |
| `handoff.md` | Указатель «где мы»; перезаписывается после каждого закрытия |
| `roadmap.md` | Исполнительская карта: граф фаз, гейты владельца, отложенное |
| `journal.jsonl` | Append-only история запусков `task:plan-*` |
| `brief/` | Слой 0 «вход»: бриф владельца и его уточнения |
| `inputs/` | Провенанс внешних входов: пути и SHA256, без копий |
| `findings/` | Слой 1 «разбор»: матрица вердиктов, receipt'ы items |
| `research/` | Слой 2 «синтез»: измеренная база, эскизы, дизайн фаз и их RAG |
| `artifacts/` | Сырые внешние ответы с digest'ами, будущая инвентаризация кода |
| `open-questions.md` | Незакрытое: `Decision pending` и `Exploration` |

## Провенанс

| Источник | Слой | Питает | Суть |
|:--|:--|:--|:--|
| `inputs/sources.md` | inputs | весь план | Пути и SHA256 двух аудитов как non-normative сырья |
| `findings/audit-verdicts.md` | findings | все фазы | 165 разобранных пунктов A/S/N с вердиктом и целевой фазой |
| `research/00-baseline-metrics.md` | research | все фазы | Измеренная база 20 пакетов: объёмы, скрытые связи, гейты, владение таблицами |
| `research/perplexity-2026-09-19.md` | research | все фазы | Сводка 19 внешних подтверждений верхнего слоя |
| `artifacts/rag-2026-09-19/` | artifacts | все фазы | Сырые ответы верхнего слоя с `manifest.json` и SHA256 |
| `research/01-shapes-runtime.md` | research | P4–P8 | Эскизы форм durable-границы, конфигурации, composition roots |
| `research/02-shapes-platform.md` | research | P1–P3, P10, P11 | Эскизы схемы метаданных, инвентаря, гейтов, реестров |
| `research/03-shapes-api.md` | research | P12–P14 | Эскизы контракта операции, ошибок, нагрузочного контура |
| `research/P1-design.md` | research | `P1.1`–`P1.7` | Готовый нормативный текст 12 документов `docs/architecture/**` и `docs/rejected-optimizations.md` |
| `research/P1-design-rag.md` | research | `P1.1`, `P1.2`, `P1.5`, `P1.6` | Внешние подтверждения фазы P1 и граница того, что подтверждено не было |
| `artifacts/P1-design-rag/` | artifacts | `P1.1`–`P1.7` | Сырые ответы девяти запросов P1 с `manifest.json` и SHA256 |
| `findings/P1-receipts.md` | findings | `P1.1`–`P1.7` | Создаётся исполнением: вывод Validation каждого item'а фазы |
| `research/P2-design.md` | research | `P2.1`–`P2.6` | Схема инвентаря, готовый код сканера, правила графов, каталогов, вердиктов и scorecard |
| `research/P2-design-rag.md` | research | `P2.1`–`P2.6` | Внешние подтверждения фазы P2 (5 запросов) |
| `artifacts/P2-design-rag/` | artifacts | `P2.1`–`P2.6` | Сырые ответы запросов P2 с `manifest.json` и SHA256 |
| `findings/P2-inventory-receipts.md` | findings | `P2.1`–`P2.6` | Создаётся исполнением: вывод Validation и самопроверки сканера |
| `research/P3-design.md` | research | `P3.1`–`P3.7` | Готовый код гейтов: контур PHPStan, tokenizer-граф, R4/R4b/R5, Roave, DurablePayload, имена, config(), T1/T2/T4 |
| `research/P3-design-rag.md` | research | `P3.1`–`P3.7` | Внешние подтверждения фазы P3 (6 запросов) |
| `artifacts/P3-design-rag/` | artifacts | `P3.1`–`P3.7` | Сырые ответы запросов P3 с `manifest.json` и SHA256 |
| `findings/P3-gate-receipts.md` | findings | `P3.1`–`P3.7` | Создаётся исполнением: вывод Validation и негативных посадок |
| `research/P4-design.md` | research | `P4.1`–`P4.12` | Готовый код durable-границы: транзитная и финальная форма `DataScope`, restorer, codec, часы PSR-20, тест изоляции, гейт биндингов |
| `research/P4-design-rag.md` | research | `P4.1`–`P4.12` | Внешние подтверждения фазы P4 (6 запросов) и граница того, что подтверждено не было |
| `artifacts/P4-design-rag/` | artifacts | `P4.1`–`P4.12` | Сырые ответы запросов P4 с `manifest.json` и SHA256 |
| `findings/P4-receipts.md` | findings | `P4.1`–`P4.12` | Создаётся исполнением: вывод Validation, названные пределы D22/D23, текст уведомления потребителей |
| `findings/P4-binding-audit.md` | findings | `P4.12` | Создаётся исполнением: таблица времён жизни биндингов и allowlist гейта |
| `research/P5-design.md` | research | `P5.1`–`P5.12` | Исходный дизайн messaging-основы; defects корректируются P5.13–P5.18. |
| `findings/execution-audit-P1-P5-2026-09-22.md` | findings | P5 remediation | Воспроизведённые EA01–EA05, команды, GREEN/RED граница и owning items. |
| `findings/task-0204-lifecycle-compatibility-2026-09-22.md` | findings | current-plan tooling | Доказанная несовместимость legacy journal с Task 0.20.4; технический долг текущего активного плана. |
| `research/P4-P5-execution-remediation.md` | research | `P5.13`–`P5.18` | Нормативная форма пяти product repairs, joined qualification и checkpoint discipline. |
| `research/P5-design-rag.md` | research | `P5.1`–`P5.12` | Внешние подтверждения фазы P5 (7 запросов) и граница того, что подтверждено не было |
| `artifacts/P5-design-rag/` | artifacts | `P5.1`–`P5.12` | Сырые ответы запросов P5 с `manifest.json` и SHA256; фиксирует дефект транспорта (дублирование запроса) |
| `findings/P5-receipts.md` | findings | `P5.1`–`P5.12` | Создаётся исполнением: вывод Validation, названные пределы D28/D29/D30, долги плоскости управления (P9) и источника времени prune (P6) |
| `findings/P5-zone-map.md` | findings | `P5.11` | Создаётся исполнением: измеренная принадлежность файлов зоны `Messaging` и `PubSub` с отметкой «перенос — P6», плюс фактические импорты |
| `research/P6-design.md` | research | `P6.1`–`P6.6` | Готовая карта десяти зон, матрица направлений, литмус, arch-гейт R-Z0/R-Z1/R-Z2, staging N09, целевые FQCN для P15 |
| `research/P6-design-rag.md` | research | `P6.1`–`P6.6` | Внешние подтверждения фазы P6 (5 запросов) и граница того, что подтверждено не было |
| `artifacts/P6-design-rag/` | artifacts | `P6.1`–`P6.6` | Сырые ответы запросов P6 с `manifest.json` и SHA256 |
| `findings/P6-receipts.md` | findings | `P6.1`–`P6.6` | Создаётся исполнением: вывод Validation, посадки гейтов, долги Clock/N09/DATA-003/P15 |
| `findings/P6-departments.md` | findings | `P6.4` | Создаётся исполнением: смысл «отдел» у ядра, auth и tenancy |
| `research/P7-design.md` | research | `P7.1`–`P7.12` | Готовый код Options, policy, SecretRef, команды validate и блокирующего гейта; D100 witness отдельно в P7.12 |
| `research/P7-design-rag.md` | research | `P7.1`–`P7.11` | Внешние подтверждения фазы P7 (5 запросов) и граница того, что подтверждено не было |
| `artifacts/P7-design-rag/` | artifacts | `P7.1`–`P7.11` | Сырые ответы запросов P7 с `manifest.json` и SHA256 |
| `findings/P7-receipts.md` | findings | `P7.1`–`P7.12` | Создаётся исполнением: вывод Validation, D100 witness, посадки гейта, redaction |
| `research/P8-design.md` | research | `P8.1`–`P8.6` | Готовый код PackageRegistrar, registrar'ов tenancy/modules, доказательства биндингов и гейта размера |
| `research/P8-design-rag.md` | research | `P8.1`–`P8.6` | Внешние подтверждения фазы P8 (5 запросов) и граница того, что подтверждено не было |
| `artifacts/P8-design-rag/` | artifacts | `P8.1`–`P8.6` | Сырые ответы запросов P8 с `manifest.json` и SHA256 |
| `findings/P8-binding-catalog.md` | findings | `P8.1`–`P8.6` | Создаётся исполнением: список биндингов «до» после сверки с деревом P7 |
| `findings/P8-receipts.md` | findings | `P8.1`–`P8.6` | Создаётся исполнением: вывод Validation, посадки гейта, порядок bootstrapper'ов |
| `research/P9-design.md` | research | `P9.1`–`P9.8` | Атомарная карта tenancy, compatibility FQCN, behavioral fingerprint и structure ratchet |
| `research/P9-design-rag.md` | research | `P9.1`–`P9.8` | Первичные источники bounded context, layers, Composer PSR-4 и tenancy bootstrappers |
| `artifacts/P9-design-rag/` | artifacts | `P9.1`–`P9.8` | Сжатые source records с manifest/digest; фиксируют недоступность Perplexity и web transport |
| `findings/P9-tenancy-map.md` | findings | `P9.1`–`P9.8` | Создаётся P9.1: post-P8 row на каждый PHP-файл |
| `findings/P9-receipts.md` | findings | `P9.1`–`P9.8` | Создаётся исполнением: compatibility diff, оба DB lanes, zero DDL diff, relay risk |
| `research/P10-design.md` | research | `P10.1`–`P10.8` | Identity/revisions, composite authority, reversible evolution, resolver/index policy, write guards и outbox contract |
| `research/P10-design-rag.md` | research | `P10.1`–`P10.8` | Первичные источники UUIDv7, online evolution и PostgreSQL indexing; outbox evidence переиспользует P5 |
| `artifacts/P10-design-rag/` | artifacts | `P10.1`–`P10.8` | Сжатые source records и manifest с SHA256 |
| `findings/P10-receipts.md` | findings | `P10.1`–`P10.8` | Создаётся исполнением: G-DDL, rollback matrix, оба engines, восемь checks, outbox и final ratchets |
| `findings/P10-design-validation.md` | findings | P10 design | Green plan checks и дефект citation resolver для external-repo плана |
| `research/P13-design.md` | research | `P13.1`–`P13.12` | Нормативный operation contract: compiled semantics, shared invoker, profile/capabilities, routes, idempotency, cursor, authorization, error wire и snapshots |
| `research/P13-design-rag.md` | research | `P13.1`–`P13.12` | Perplexity comparative pass и standards boundary; vendor facts не становятся CoreX authority |
| `artifacts/P13-design-rag/` | artifacts | `P13.1`–`P13.12` | Пять Perplexity source records с manifest/digest и disclosed transport defects |
| `findings/P13-receipts.md` | findings | `P13.1`–`P13.12` | Создаётся исполнением: descriptor/profile hashes, routes, replay/cursor/auth/error parity, snapshots, planted gates и G-DDL/G-API |
| `findings/P13-design-validation.md` | findings | P13 design | Green plan checks, route attestation и disclosed defects external evidence transport |
| `findings/P13-design-delivery.json` | findings | P13 design | Машиночитаемый handoff receipt на design P14 |

| `research/P14-design.md` | research | P14, P15–P17 | Стоимость, bulk, webhook-delivery; D74–D84, 21 item |
| `research/P16-design.md` | research | `P16.1`–`P16.13` | Producer-adjusted профили, физическая раскладка без FQCN-миграции, panels и Testing boundary |
| `research/P16-design-rag.md` | research | `P16.1`, `P16.9`, `P16.10` | Узкий Perplexity-pass по терминологии admin-экосистем и Composer PSR-4 |
| `artifacts/P16-design-rag/` | artifacts | P16 design | Два сырых Perplexity-ответа с manifest/SHA256 |
| `findings/P16-design-bundle-checks.json` | findings | P16 design | Digest-bound dry render всех 13 item bundles; future bundles не сохранены |
| `findings/P16-design-validation.md` | findings | P16 design | Проверки плана, route attestation и границы design-only |
| `findings/P16-continuity.json` | findings | P16→P17 | Typed design-root-terminal continuity с checkpoint P16 |
| `findings/P16-delivery.json` | findings | P16→P17 | Сгенерированный handoff-recommendation receipt на design P17 |
| `research/P17-design.md` | research | `P17.1`–`P17.12` | 25+2 DoD, candidate/fixture/profile/upgrade schemas and sealed release procedure |
| `research/P17-design-rag.md` | research | `P17.1`, `P17.6`, `P17.7`, `P17.11`, `P17.12` | One bounded Perplexity pass plus direct Composer/Laravel/GitHub primary-source verification |
| `artifacts/P17-design-rag/` | artifacts | P17 design | Query capture, source boundary and manifest/SHA256 |
| `findings/P17-design-bundle-checks.json` | findings | P17 design | Digest-bound dry render of all 12 item bundles; future bundles not persisted |
| `findings/P17-design-validation.md` | findings | P17 design | Plan checks, route attestation, 25/27 correction and design-only boundary |
| `findings/P17-continuity.json` | findings | P17→Stage 2 | Typed design-root-terminal continuity to whole-plan independent audit |
| `findings/P17-delivery.json` | findings | P17→Stage 2 | Handoff recommendation receipt for `task:plan-audit ... design` |
| `findings/design-da09-delta-review-2026-09-20.md` | findings | Stage 2 closure | Independent bounded GREEN review of the DA09-only carrier delta and disclosed route-evidence limitation |
| `findings/design-da09-continuity.json` | findings | Stage 2→P1.1 | Typed design-root-terminal continuity and P1.1 frontier binding |
| `findings/design-da09-delivery.json` | findings | Stage 2→P1.1 | Generated handoff recommendation receipt for the first ready execution batch |
