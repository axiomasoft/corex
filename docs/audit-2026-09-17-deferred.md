# Отложенное и сознательно отклонённое по аудитам 2026-09-17

Ledger фазы P12.9. Нормативная матрица — `research/P12-audit-reconciliation.md` §5–§6. Исправления P12 — [remediation](audit-2026-09-17-remediation.md). Сводка — [audit-2026-09-17.md](audit-2026-09-17.md).

> **Эти пункты не считаются решёнными.** Спека и ADR по правилам репозитория живут в Brain-vault; ниже — репозиторно-локальный ledger и формулировки ADR-предложений для vault.

## Отложенная работа (9 пунктов)

| # | Дефект | Матрица | Почему отложено | Требуемый гейт | ADR-предложение для vault |
| --- | --- | --- | --- | --- | --- |
| 1 | Explicit `SetAttribute`/`RemoveAttribute`, presence-тип, различение missing/null/remove в `ValuePatch`/`ValueBag` | B5 | Меняет семантику хранилища шире table-map P12.7 | — | ADR-ATTRIBUTES-PRESENCE |
| 2 | Разделение `DatabaseTransferRunner`; таксономия ошибок; вынос внешнего вызова из retryable-транзакции; снятие `catch (Throwable)`; семантика dry-run | TR2, TR3, TR4 | P12.4 — hard rename без смены поведения; смешивание сделало бы дифф непроверяемым | — | ADR-TRANSFER-RUNNER-REFACTOR |
| 3 | Полная декомпозиция `DataScope` на `AccessContext`/`StorageContext` | A1 | P12.8 снял только `Closure`; остальное меняет `shared-data-foundation/1` шире выданного G-API | G-API (major) | ADR-DATASCOPE-DECOMPOSITION |
| 4 | Сокращение `axiomasoft/corex` | §2 reconciliation | Извлечение 6–8 пакетов — отдельная программа вне глубины P12 | G-API, G-SCHEMA | ADR-CORE-REDUCTION |
| 5 | Вынос `config()` из domain/repository/engine в типизированные options-объекты | G1 | ≥20 call-sites; вне выбранной глубины P12 | — | ADR-TYPED-CONFIG-INJECTION |
| 6 | Перевод tenant-зависимых `singleton` в `scoped`/resettable | G3 | Lifetime change может сломать tenancy composition | — | ADR-TENANT-SCOPED-SERVICES |
| 7 | Перенос DTO из `core/src/Contracts/` в `core/src/Data/` | B7 | Публичный перенос — breaking | **G-API** | ADR-CONTRACTS-DTO-CANON |
| 8 | Публичный API census + snapshot (`roave/backward-compatibility-check`) | — | Нет двух потребителей для freeze | — | ADR-PUBLIC-API-SNAPSHOT |
| 9 | Ослабление пина `meilisearch/meilisearch-php` (актуальная ветка SDK — `2.0.0-beta.*`) | C3 | Dependency decision | **G-DEPENDENCY** | ADR-MEILISEARCH-SDK-PIN |

### Снятие legacy-исключения `CoreX\Tenancy\`

Условие снятия **совпадает** с `expires_when` в `scripts/package-graph-gate.json`:

```text
axiomasoft/corex reduction lands; core defines zero classes under CoreX\Tenancy\
```

До выполнения ADR-CORE-REDUCTION (или эквивалента) гейт продолжает печатать именованное просроченное исключение на каждом прогоне.

## Сознательно отклонено (5 идей)

| Отклонено | Почему | Матрица |
| --- | --- | --- |
| Каталоги `packages/kernel/`, `capabilities/`, `integrations/`, `products/` | Физическое перемещение 20 пакетов без закреплённых правил зависимостей; правила задаёт гейт P12.3 при плоской структуре. Аудит №2 сам допускает отложение физического перемещения | §6 |
| Freeze всей функциональности («Этап 0» аудита №1) | Противоречит плану: P8–P11 остаются исполнимыми после P12 | §6 |
| `Deptrac` / `deptrac/deptrac` как второй движок | Пакет заброшен; гейт P12.3 — repo-local скрипт по образцу `anti-weakening-gate.sh` | §4, §6 |
| Отдельный пакет на каждый storage/search-провайдер | Запрещено `docs/dev-doctrine.md` и bridge-политикой аудитов | §6 |
| Немедленный public-API freeze | Аудит №1 ставит условием два потребителя (CRM + CMS); второго нет | §6 |

## Границы выданных разрешений (D41)

D41 выдал **только** G-API на удаление `Closure $authorize` из `DataScope`. Ни один пункт ledger **не** получил разрешение через P12:

- G-DEPENDENCY (пин Meilisearch SDK)
- G-API (перенос DTO, полная декомпозиция `DataScope`, сокращение core)
- G-SCHEMA / G-STORAGE

Повторное рассмотрение — только через отдельный owner receipt и vault ADR.

## Связанные документы

- Прецедент отложенных архитектурных предложений: [audit-2026-09-14-proposals.md](audit-2026-09-14-proposals.md) (B01–B14)
- `decisions/D41-package-boundary-rebaseline.md` — Consequences
- `findings/P12-rebaseline-receipts.md` §P12.9 — печать фазы
