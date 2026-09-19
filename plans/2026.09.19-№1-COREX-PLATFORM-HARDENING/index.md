# Индекс плана

Карта файлов и провенанс-связи. Не носитель статуса (`status.md`) и не носитель «куда
дальше» (`handoff.md`).

**Обновлён:** 2026-09-19

## Карта директорий

| Путь | Назначение |
|:--|:--|
| `plan.md` | Стабильное ядро: Meta, Context, Execution Rules, §3 Routing, §7 Contracts |
| `status.md` | Генерируемая проекция статуса фаз и items |
| `phases/<Pn>/<Pn>.md` | Стабильный Phase Context и индекс items фазы |
| `phases/<Pn>/<Pn.m>.md` | Стабильная спецификация item'а (11 полей) |
| `phases/<Pn>/<Pn.m>.state.md` | Генерируемое состояние item'а |
| `bundles/<Pn.m>.json` | Упорядоченные входы исполнителя с digest'ами |
| `decisions/` | Append-only решения D1–D13 плюс генерируемые виды |
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
