# P07.1 — Validation summary (2026-10-04, candidate = tree of the P07.1 product commit)

Окружение: PHP 8.4.26 (`scripts/php84`), PostgreSQL 16 на host-порту **5432** (вместо 5434; `corex_test`, user `corex`), без `ext-redis`
(package-local vendor: `--ignore-platform-req=ext-redis`; 6 Redis-тестов skipped, как на baseline). Hosted CI игнорируется по указанию владельца.

| Carrier (Validation P07.1) | Результат |
|:--|:--|
| D103 `composer bc-check` (tenancy, exact decision-bound delta) | exit 0 — `bc-check-tenancy.log` (44 конечные строки, base `76f8bdde`) |
| `contract-guard.sh` без аргумента и с `base-commit.txt` | выполняется после product-коммита (CHANGELOG + php-api.json в одном diff) |
| `AccountStatusGateTest` + HttpRegistrar | 19 тестов зелёные (матрица статусов, null-owner, spy central, constructor/binding) |
| `composer --working-dir=packages/tenancy test -- --filter='TenancyEngine\|QueueTenantContext\|BootstrapperOrder\|HostResolution\|TenantSessionScope\|QueueTenancyRevert\|TenancyManager'` | зелёные в составе полного прогона пакета |
| `PGSQL_PORT=5432 … --group=pg --filter='ScopeBoundary\|ResidentTenantSwitch'` + мутация `P07-scope-boundary` | зелёные; мутант: 3 красных теста — `mutation-P07-scope-boundary.log` |
| `git grep` stancl runtime/`tenancy()` в `packages/tenancy/src` | пусто |
| Cache fixture, unsupported array, PackageManifest без stancl-провайдера, UUID/provisioning | `TenantCacheIsolationTest`, `TenancyEngineTest`, `StanclDiscoveryTest`, `ProvisioningTest` — зелёные |
| `composer error-registry` / `runtime-globals-gate` / `config-cache-check` | exit 0 (config-cache-check — в составе root `composer test`) |
| `composer --working-dir=packages/tenancy test` | **588 passed, 6 skipped** (2123 assertions) |
| `composer test:packages` | запускался только пакет tenancy (остальные пакеты не имеют package-local vendor в этой среде; access не зависит от tenancy в рантайме) |
| `composer test` (root, hermetic) | **1124 passed** + 17 (TasksWorkflowPackage) |
| `composer test:pg` (root) | **242 passed** (1156 assertions) |
| `php-api.php --write` дважды | идентично; `composer php-api`, `composer bc-check` — exit 0; Golden diff = только строки P07.1 (`golden-semantic-delta.json`: +17 / −4 / ~8 типов, wire +1) |
| `composer lint:check` | красный **только** на PHP-артефактах планов P06/P07 (foreign baseline, красные уже на `57369e08`); `pint --test packages/tenancy packages/core/src scripts` — passed |
| `composer analyse` | 22 конфигурации passed, `ceilings OK` (tenancy baseline снижен) |
| `bash scripts/anti-weakening-gate.sh` | выполняется после коммита (сравнение BASE..HEAD) |
| `php scripts/package-docs-gate.php` | OK |
| Прочие гейты | `config-composition`, `module-layout`, `package-graph`, `ownership`, `naming`, `rename-completeness`, `table-*`, `extension-contract`, `check-api-contract`, `quality-ceiling`, `manifest-check`, `configuration-reference` — exit 0 |

## Не зелёное / открытое

- `bash scripts/dependency-analyser.sh --all` → exit 1 **только на `packages/tenancy`**: `composer` не резолвит `axiomasoft/stancl-*` без staging-репозитория
  (`scripts/distribution/stage-stancl-providers.py` в CI запускается перед шагом; локально `composer.json` возвращён к pristine). Остальные пакеты — без замечаний.
- `composer.lock`: content-hash устарел после правки `extra` в root `composer.json` (файл закрыт deny-правилом владельца, не тронут).
- Терминальное закрытие item (journal terminal event, `plan-work finalize`, state/status) не выполнено в этой сессии — см. handoff.
