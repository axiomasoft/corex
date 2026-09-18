# HANDOFF — 2026-09-18 — after P7.9

**Next:** exec-items: task:plan-exec 2026.09.15-№1-COREX-FLEXCRM-REMAINING P12.1

| Параметр | Значение |
|---|---|
| Batch | solo |
| Model class | implementation |
| Effort | medium |
| Capabilities | — |
| Context | cold-start-root: design-root-terminal |
| Суть | Три P0: контракт модуля corex/intl, импорт реализации из другого пакета в контракте ядра, незаявленные cross-package зависимости. |

```
task:plan-exec 2026.09.15-№1-COREX-FLEXCRM-REMAINING P12.1
```

```session-continuity-decision/v1
{"evidence":["persisted-context:design-root-terminal"],"outcome":"cold-start-root","reason":"design-root-terminal","runnable":true,"schema_version":"session-continuity-decision/v1"}
```

**Done:** P12 детализирована на девять item по разбору двух независимых внешних аудитов. Из 29 проверенных утверждений 23 подтверждены чтением кода, 3 частично, 4 опровергнуты; один P0 обнаружен при сверке и отсутствует в обоих аудитах. Обязательный нейтральный RAG получен, шесть первичных источников получены напрямую (HTTP 200, байты и SHA256 сохранены), одно утверждение инструмента прямую проверку не прошло и отклонено. `scripts/validate-p12.py --check design` зелёный и проверен негативным прогоном.

**Ready:** P12.1 владеет тремя P0. Код берётся из `research/P12-rebaseline-design.md` §1; копировать PHP из `audits/*` запрещено — там невалидные примеры.

**Remaining:** P12.1–P12.9, затем P8.1–P8.7 и далее P9/P10/P11 по routing. Прежний next (P8.1) заменён: P12 — блокирующий предшественник P8, потому что переименования пакетов и схема резолва `SearchProvider` меняются в P12.

**Open risks:** `corex/intl` сейчас ломает discovery модулей в любом приложении, где установлен, — это не гипотеза, а следствие контракта `ModuleDiscovery`; дефект прошёл приёмку P7.8 незамеченным, что стоит учесть при review P12.1. Расщеплённый неймспейс `CoreX\Tenancy\` в P12 не разводится и остаётся объявленным просроченным исключением гейта. Отдельно: на `bfc05d0` **оба** гейта уже были красными до P12. `--check design` — из-за устаревших хешей `scripts/validate-p5/6/7.py` при неизменённых файлах; пересчёт для этих трёх входов — только коррекция актуальности, не новый review дизайна P5–P7. `--check lint` — из-за правленных руками генерируемых носителей `phases/P7/P7.*.state.md` (`✅ Done` вместо `🟢 Done`, `P7.8` как Not started при закрытом журнале, у `P7.4` вписанный 65-символьный псевдо-sha256); носители пересобраны генератором из журнала, фактический статус P7 — 9/9 🟢. `--check lint` остаётся красным: 14 унаследованных ошибок на не тронутых этой сессией файлах (10 в `journal.jsonl`, 3 по кодам write-site P6.6–P6.8, 1 bundle P8.1), владельцы — P1/P6/P8, вне scope P12. Полное раскрытие — `artifacts/design-finish.md`, разделы «Раскрытие дефекта печати», «Ручная правка генерируемых носителей состояния P7», «Остаточные унаследованные ошибки линта». Q-P2-052 и P8 invoke receipt сохраняют прежний статус.

**Sources of truth:** `journal.jsonl`; `decisions/D41-package-boundary-rebaseline.md`; `research/P12-audit-reconciliation.md`; `research/P12-rebaseline-design.md`; `research/P12-design-rag.md`; `artifacts/P12-design/integrity.json`; `artifacts/P12-design/primary-source-retrieval.json`; `phases/P12/P12.md`.

**Workarounds/Deferred/Open questions:**
- workarounds: `CoreX\Tenancy\` остаётся расщеплённым между `corex/core` и `corex/tenancy`; вносится в гейт P12.3 как именованное исключение с условием снятия, а не исправляется.
- deferred: девять подтверждённых дефектов вне выбранной глубины — presence-семантика `ValuePatch`/`ValueBag`, декомпозиция `DatabaseTransferRunner`, полная декомпозиция `DataScope`, сокращение `corex/core`, вынос `config()` из доменного слоя, tenant-зависимые `singleton`, перенос DTO из `core/src/Contracts/` (G-API), public-API census, ослабление пина SDK Meilisearch (G-DEPENDENCY). Оформляются ledger'ом в P12.9.
- open_questions: —
