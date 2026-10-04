# P07.1 — Completion Notes

Item: собственный движок tenancy и граница контекста (D95, D98–D103). Route: `implementation/xhigh` (фактический xhigh, `route-run-receipt/v1` — run `88a6e4f2…`).
Base commit: `artifacts/P07.1-execution/base-commit.txt` (`57369e08`). Candidate SHA: см. итоговый коммит item (строка в журнале `plan-close`/`finalize`).

## Что сделано

- `DatabaseTenancyManager` (`src/Runtime/Internal/DatabaseTenancyManager.php`): `initialize`/`end`/`runFor` по D95 п.2 с поправками D98 п.2–3, D102 п.4:
  порядок bootstrappers вперёд / откат в обратном, откат всех начатых (включая бросивший), общий `resetScope()` на каждом переходе (`:213`),
  `TenantCleanupFailure` + PSR-лог обеих причин (`:179`), poisoned-режим (`:194`), `runFor` не восстанавливает poisoned runtime.
- SPI `TenantBootstrapper`, события `TenantContextInitialized`/`TenantContextEnded`, `TenantContextUnavailable` с `tenancy.context_unavailable`.
- Bootstrappers: `ConnectionBootstrapper` (URL разобран, routing-ключи отвергнуты, `tenant` снимается при revert), `CachePrefixBootstrapper`
  (redis/memcached/database prefix, file path+lock_path, SHA-256 account id), `SessionConnectionBootstrapper` (живой handler заменяется на том же `Store`,
  database/redis/memcached/file, каталог 0700, revert без удаления сессий).
- `CentralTemplateConnectionResolver` (`bindIf`), `QueueTenantContext` (LIFO-кадры по job object, единственная точка завершения — `JobAttempted`,
  payload `corex_tenant`), `HostAccountResolver` (кэш только id, без miss), `HostResolverCacheInvalidator`, `TenantFacilitiesValidator`
  (проверка cache/session конфигурации до первого переключения), `FrameworkConfig` (единственный шов к runtime-конфигу фреймворка),
  `Laravel/TenancyRuntimeFactory` (многострочные фабрики; closures регистраторов остаются single-return).
- HTTP: `ResolveTenantFromHost` (initialize → `$next` → `finally end`; на входе/выходе сбрасывает атрибуты уже созданных Store и guards),
  `ScopeSessionToTenant`, `AccountStatusGate` (пять required-аргументов, owner через `AccountRead::ownerIdentityId(id, central)`),
  приоритеты ядра (`HttpRegistrar::prioritiseHostMiddleware`): host до StartSession, scope после него, gate следом.
- `OidcLoginController`, `DatabaseImpersonationService`, `AuthenticationRegistrar`: `tenancy()` и stancl-тип заменены на `TenantContextResolver`.
- Удалены `StanclTenancyManager`, `WorkspaceContextBootstrapper`, `HostTenantResolver`, `RevertTenancyOnJobException`.
- Root `composer.json`: `extra.laravel.dont-discover: ["axiomasoft/stancl-tenancy"]` (промежуточно до P07.2); стаблы/Testbench без stancl-провайдера.
- Статья RUN-006 в `docs/architecture/constitution.md` (+ строка в `docs/architecture/README.md`).
- Конфиг `config/tenancy.php`: `bootstrappers` (было `bootstrapper_order`), `cache_stores` (null = default store), комментарии; `configuration-classes.json` и `configuration.md` регенерированы.
- BC/Golden: `scripts/bc/approved-deltas.json` (tenancy, 44 конечные строки Roave, decisions D95/D102/D103), `php-api.json`, `public-api.md`,
  `tests/Golden/baseline/{php-surface,wire}.json`, `tests/fixtures/public-error-contract.php`, `REBASELINE.md`; `golden-semantic-delta.json`; CHANGELOG пакета.

## Self-check (D95/D98/D100/D101/D102/D103 → реализация → тест)

| Норма | Реализация | Тест |
|:--|:--|:--|
| D95 п.2 initialize/end/runFor, event-ы | `DatabaseTenancyManager.php:67,122,143` | `TenancyEngineTest` (порядок, события, runFor вложенность/исключение) |
| D95 п.3 граница scoped = tenant | `resetScope()` `:213`, RUN-006 | `ScopeBoundaryPgTest` (3 перехода) + мутация `P07-scope-boundary` (`mutation-P07-scope-boundary.log`: красные 3 теста) |
| D95 п.4 / D98 п.1 bootstrappers, A1 порядок/ацикличность | `Bootstrappers/*`, `TenancyRuntimeFactory::manager` | `BootstrapperOrderTest`, `TenancyEngineTest` (реальный контейнер, нет зависимости от manager) |
| D98 п.2 A2–A3 откат начатых, poison, run/finally | `DatabaseTenancyManager.php:67–211` | `TenancyEngineTest` (partial bootstrap, revert failure, poisoned, двойной отказ, runFor missing/resolver/listener) |
| D95 п.5 / D98 п.3 A4 очереди | `QueueTenantContext.php:39,69`, `TenancyRuntimeFactory::payloadHook` | `QueueTenantContextTest`, `PhysicalContextConformanceTest`, `QueueTenancyRevertTest` |
| D98 п.4 A5 кэш (redis/memcached/database prefix, file path+lock, array отвергнут) | `CachePrefixBootstrapper`, `TenantFacilitiesValidator` | `TenantCacheIsolationTest` (file central/A/B/A + lock), `TenancyEngineTest` (отказы/принятые драйверы) |
| D95 п.6 / D100 п.2 HTTP, сессия, приоритеты | `ResolveTenantFromHost`, `ScopeSessionToTenant`, `HttpRegistrar::prioritiseHostMiddleware` | `TenantSessionScopeTest`, `TenantSessionScopeHttpTest` (весь стек, A→B, central cookie, residual state, исключение), `HostResolutionTest` |
| D98 п.6 A7 dont-discover, projections, UUID/provisioning без провайдера | root `composer.json`, `RuntimeRegistrar::boot` | `StanclDiscoveryTest`, `ProvisioningTest`, `PendingPoolTest` |
| D98 п.7 / D99 A8 `tenancy()`/Stancl-тип сняты | `DatabaseImpersonationService`, `OidcLoginController`, `AuthenticationRegistrar` | `ImpersonationTest`, `OidcLoginTest`; `git grep` по stancl-runtime пусто; G2 baseline не менялся |
| D100 п.1 URL/credentials/options, routing-ключи, replicas | `ConnectionBootstrapper`, `CentralTemplateConnectionResolver` | `ScopeBoundaryPgTest` (URL central, resolver override, routing keys, replica overlay) |
| D101 каталог сессий 0700, реальные чтение/запись/GC | `SessionConnectionBootstrapper` | `TenantSessionScopeHttpTest` (реальный файл 0700, GC), `TenantCacheIsolationTest` (непишемый путь) |
| D102 п.2 AccountStatusGate | `AccountStatusGate.php`, `HttpRegistrar` | `AccountStatusGateTest` (матрица статусов, null-owner, spy central, конструктор) |
| D102 п.3 `ScopeSessionToTenant` через `TenancyManager::context()` | `ScopeSessionToTenant.php:28` | `TenantSessionScopeTest` (е) |
| D102 п.4 логгер/диагностика | `DatabaseTenancyManager.php:179`, `TenancyRuntimeFactory::manager` | `TenancyEngineTest` (двойной отказ, мёртвый логгер, binding PSR logger) |
| D103 п.1 stable BC admission | `scripts/bc/approved-deltas.json` | `scripts/php84 composer bc-check` → exit 0 (`bc-check-tenancy.log`) |
| D103 п.3 QueueTenantContext 4 параметра, outer restoration | `QueueTenantContext.php` | `QueueTenantContextTest` (вложенные sync success/failure) |

review: self-check GREEN

## Отклонения и решения вне Files (прозрачно)

- **Окружение.** PG на host-порту 5432 (креды те же, БД `corex_test`) вместо 5434; в PHP 8.4 нет `ext-redis` — package-local `composer install` с
  `--ignore-platform-req=ext-redis` (6 Redis-тестов skipped, как на baseline); `scripts/distribution/stage-stancl-providers.py` запущен локально
  (меняет три `composer.json`; изменения возвращены `git checkout`, после чего добавлен только `dont-discover`). Hosted CI игнорируется по указанию владельца (D106).
- **Правки гейтов, не названные в Files (нужны для зелёных гейтов):** `scripts/composition-boundary.json` (+1 `allow_paths` — шов `FrameworkConfig`),
  `scripts/extension-contract-gate.php` (классификация `DatabaseTenancyManager`), `scripts/configuration-classes.json` (ключи/поля Options),
  `packages/core/config/corex.php` (комментарий), `phpstan-baseline.neon` пакета (удалены записи удалённых файлов и устаревшие, +1 ложное
  срабатывание `forgetScopedInstances` — как у tasks-workflow; суммарно потолок снижен 322 → ≈271).
- **BC-строки Roave.** Одобрены 44 конечные строки (88 с дублями): помимо `AccountStatusGate` (D102/D103) в них — `TenancyOptions` (конструктор/свойства),
  `ResolveTenantFromHost`, `TenantCouldNotBeIdentifiedByHostException` (предки), три удалённых класса и `TenancyErrorCode::ContextUnavailable`. Все символы названы
  в Scope/Files P07.1; других строк нет, подстановок нет.
- **R5/S3.** `HostResolverCacheInvalidator` лежит в `Laravel/Support` (AccountLifecycle — другой модуль); многострочные фабрики вынесены в `Laravel/TenancyRuntimeFactory`.
- **Листенеры stancl `TenantSaved/TenantDeleted` не нужны:** `eloquent.saved/deleting` на `Account`/`Domain` срабатывают (проверено `HostResolutionTest` ж); временные подписки не добавлялись.
- **`composer.lock` content-hash.** Root `extra` изменён → hash устарел; `composer.lock` закрыт deny-правилом владельца, поэтому не тронут — см. «Открытое».
- **`lint:check` корня красный только на PHP-артефактах планов P06/P07 (foreign baseline, уже красные на `57369e08`);** собственные файлы чисты (`pint --test` пакетов/ядра/скриптов — passed).

## Открытое (владельцу)

- Обновить `content-hash` в `composer.lock` после правки root `composer.json` (одна строка; `composer update --lock` без staging-репозитория не резолвится).
- G2 baseline остаётся 19 (D99); полный `{}` — P07.3. P07.2 удаляет остаток stancl (модели, provisioning, пакеты, lock, CI).

## Evidence

`artifacts/P07.1-execution/`: `base-commit.txt`, `mutation-P07-scope-boundary.log`, `golden-semantic-delta.json`, `bc-check-tenancy.log`, `validation-summary.md`, `apply-changelog.py` (применён).
