# Исправления по аудитам 2026-09-17 (фаза P12)

Основа: ревизия `bfc05d060e3961a5881640b7f828503440bb2902`, исполнение фазы P12 (2026-09-18). Нормативные входы: `research/P12-audit-reconciliation.md`, `research/P12-rebaseline-design.md`, `decisions/D41-package-boundary-rebaseline.md`. Сводка сверки — [audit-2026-09-17.md](audit-2026-09-17.md). Отложенное — [audit-2026-09-17-deferred.md](audit-2026-09-17-deferred.md).

## Результат

Все **восемь исполнимых item'ов P12.1–P12.8 закрыты** фактическими receipt'ами в `findings/P12-rebaseline-receipts.md`. Публичные контракты не менялись, кроме одного защищённого изменения под G-API/D41: удаление `Closure $authorize` из `DataScope`. DDL не изменялся (`git diff --stat -- 'packages/*/database/**'` пуст на каждом item).

Отложенные дефекты (presence-семантика, TR2–TR4, полная декомпозиция `DataScope`, `config()` в domain, tenant singletons и др.) **не объявлены исправленными**.

## P12.1 — контракт модуля, утечка ядра, зависимости

| ID | Исправление | Доказательство |
| --- | --- | --- |
| NEW-1 | `IntlModule` наследует `ModuleDefinition`, `manifest()` вместо несуществующего `ModuleDescriptor` | `ModuleDiscovery` обрабатывает `corex/intl` без `InvalidManifestException` |
| A2b | Удалён `use CoreX\Tenancy\StanclTenancyManager` из контракта `TenancyManager` в ядре | `rg StanclTenancyManager packages/core/src/Tenancy/Contracts/` — пусто |
| A3 | В `require` добавлены `corex/modules` (intl), `corex/workflow` (tasks-workflow); wakeup — `suggest` с guarded binding | `composer.json` пакетов; allowlist P12.3 |

## P12.2 — контур тестов 8 → 20

| ID | Исправление | Доказательство |
| --- | --- | --- |
| F1 | `composer test:packages` расширен на все 20 пакетов | корневой `composer.json` |
| F2 | `packages/core` и `packages/modules` получили `test`/`analyse`/`lint:check` | прогон через корневой `phpunit.xml` |

## P12.3 — гейт графа пакетов

| ID | Исправление | Доказательство |
| --- | --- | --- |
| A2a | Исполняемый гейт R1–R3; legacy split `CoreX\Tenancy\` с `expires_when` | `scripts/package-graph-gate.php`, `tests/Arch/PackageGraphTest.php`, `artifacts/P12-package-graph/report.json` |

## P12.4 — `corex/import-export` → `corex/transfer`

| ID | Исправление | Доказательство |
| --- | --- | --- |
| TR1, TR5 | Hard rename: PSR-4 `CoreX\Transfer\`, `src/Data/`, конфиг `corex-transfer.*` | repo-wide sweep §4.2 пуст (кроме `audits/`) |
| TR2–TR4 | **Не исправлено** — отложено в ledger | см. deferred |

## P12.5 — реестр драйверов поиска

| ID | Исправление | Доказательство |
| --- | --- | --- |
| C1, C2 | `SearchManager` + `extend()`; fail-closed конфиг; снята коллизия `SearchProvider` binding | observable checks null/postgres/meilisearch; `CoreX\Search\Meilisearch\` host-reserved |

## P12.6 — `corex/intl-search` → `corex/search-intl`

| ID | Исправление | Доказательство |
| --- | --- | --- |
| E1 | Hard rename; bridge-политика в `docs/dev-doctrine.md` | repo-wide sweep §6 пуст |
| E3 | Владение saved views подтверждено в ядре, не в search-intl | read-only paths в receipt §P12.6 |

## P12.7 — table-map и optional `corex/modules`

| ID | Исправление | Доказательство |
| --- | --- | --- |
| B1, B2, B4 | `AttributeTables`; обязательный `FieldCatalogue`; `corex/modules` → `suggest` + allowlist | `packages/attributes` test/analyse/lint/test:pg; migration diff пуст |
| B5 | **Не исправлено** — presence-семантика отложена | см. deferred |

## P12.8 — сериализуемый `DataScope` [G-API]

| ID | Исправление | Доказательство |
| --- | --- | --- |
| A1 (частично) | Удалён `Closure $authorize`; контракт `DataScopeAuthorizer` + `DataScopeAccessDecision` | serialize/unserialize test; 16 call-site parity |
| A1 (полная декомпозиция) | **Не исправлено** | см. deferred |

## Инварианты фазы (§9 дизайна)

| Инвариант | Статус |
| --- | --- |
| DDL не изменён | PASS |
| Гейты не ослаблены | PASS (`anti-weakening-gate.sh`) |
| Слои не нарушены | PASS (`LayerTest`, `PackageGraphTest`) |
| Legacy split объявлен | PASS (report.json + stdout гейта) |

## Проверки печати P12.9

| Команда | Итог |
| --- | --- |
| `composer analyse` | PASS |
| `composer test:arch` | 7 passed |
| `composer test:packages` | 472 passed, 7 skipped (pg-only) |
| `php scripts/package-graph-gate.php` | OK |
| `bash scripts/anti-weakening-gate.sh` | OK |

## Осталось / границы доказательства

- Девять отложенных пунктов ledger — [audit-2026-09-17-deferred.md](audit-2026-09-17-deferred.md).
- `IntlModule.tablePrefix('int')` — временный компромисс D37; выравнивание префикса со схемой — отдельная работа.
- Пакеты без каталога `tests/` (если остаются) раскрыты в receipt P12.2 как явные дыры покрытия.
- P8–P11 разблокированы печатью P12; автоматический запуск P8 не выполнялся.
