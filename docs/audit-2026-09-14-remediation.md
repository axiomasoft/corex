# Исправления по аудиту 2026-09-14

Основа: HEAD `5e396e7210fc17e1dc0e6917e70bef10b74becab`, ветка `local-library`, изменённое и untracked-дерево владельца. AGENTS.md, doctrine и аудит прочитаны; находки перепроверены по исходникам. Формальный план не создавался. Рабочий репозиторий не коммитился и не публиковался; изменения владельца не сбрасывались.

## Результат

Все **36 поведенческих находок Q/T/M/C/A/K/F/R подтверждены и исправлены** в пределах ниже. Публичные интерфейсы/namespaces не переносились. B01–B14 не трактовались как разрешение на архитектурные миграции: [конкретные предложения](audit-2026-09-14-proposals.md) вынесены отдельно. Membership reference остаётся незарегистрированным в runtime.

В таблицах тестовые пути относительны `packages/`. Сокращение `tenancy/Feature` означает `tenancy/tests/Feature`, аналогично для остальных пакетов.

### Изоляция тестов

| ID | Исправление | Доказательство |
| --- | --- | --- |
| Q01 | Private database/bootstrap paths до provider bootstrap, cleanup в finally. Trait подключён в CoreX, tenancy и отдельном auth Testbench base. Общий vendor не очищается. PGSQL_DATABASE без суффикса _test отвергается до bootstrap. | `core/Feature/PublishIsolationTest`: subprocess намеренно падает после publish; следующий SQLite application мигрирует; temp path удалён, hashes shared vendor migrations сохранены. Второй subprocess отвергает corex_dev до publish. Повторные auth cache tests не накапливают shared state. |

### Данные, tenancy isolation и авторизация

| ID | Исправление | Регрессионное доказательство |
| --- | --- | --- |
| T01 | Удалён fake writer. Без writer ready невозможен. Перед ready и DROP проверяются доступность/размер архива; remote storage требует verifier. | `tenancy/Feature/AccountLifecycleTest`: default не создаёт ready и не разрешает purge; недоступный архив отвергается, включая проверку после ready, физическая DB остаётся. |
| T02 | Cleanup резервирует pending как provisioning с marker до DROP; проигравший signup claim не трогает. Marker допускает retry; удаление строки и decrement атомарны. | `tenancy/Feature/PendingPoolTest`: второе соединение забирает аккаунт между scan и cleanup claim — DB сохранена; обычный cleanup проверяет строку, DB и счётчик. |
| T03 | Workspace восстанавливается во внешнем finally после Stancl revert. | `tenancy/Feature/TenancyManagerTest`: nested account/workspace, успех и исключение восстанавливают исходный context. |
| T04 | Status transitions инвалидируют host cache после outer commit. | `tenancy/Feature/AccountLifecycleTest`: warmed resolver сохраняет active после rollback, после commit возвращает suspended. |
| T05 | FSM проверяется внутри транзакции под row lock. | Тот же тест: независимый PG writer между validation и записью получает 55P03 при lock_timeout. |
| T06 | DROP предшествует deleted; затем root status/domains/audit атомарны. Deleted retry идемпотентен. Export/purge сериализованы per-account transaction advisory lock на отдельном соединении: DROP вне транзакции, session locks не используются. | Тот же тест: failed drop оставляет exporting и домен; успешный retry и повтор не дублируют delete/audit. |
| T07 | Clone сначала provisioning, pending публикуется только после physical provisioning. | `tenancy/Feature/PendingPoolTest`: PG-наблюдатель видит provisioning; отсутствие template не оставляет доступного pending. |
| T08 | Additive tenant migration добавляет users.identity_source_updated_at с µs. Source-version отделена от delivery time; writes сериализованы per-identity; membership перечитывается под lock, central synced отметка условна по прочитанным версиям. | `tenancy/Feature/IdentitySyncerTest`: задержанная v1 не скрывает v2; обратная доставка не перезаписывает свежую проекцию; исходные revoke/is_active сценарии сохранены. |
| T09 | ImpersonationService и AccountLifecycle — scoped; Eloquent guard регистрируется class listener вместо объекта, удерживающего первый request. | `tenancy/Feature/ImpersonationTest`: один container, две сессии в обоих порядках; проверены state и реальный eloquent.saving guard. |
| T10 | Invitation reload/row lock, общий workspace quota lock, active-only count; membership и accepted в одной connection transaction. | `tenancy/Feature/WorkspaceResolverTest`: stale revoked отказ; failed accepted откатывает membership; suspended не расходует quota; две invitations конкурируют за один slot на двух PG-соединениях. |
| T13 | Impersonation guard до pool claim/provision. | `tenancy/Feature/AccountLifecycleTest`: forbidden signup в пустом pool даёт authorization error, не provisioning failure; accounts остаётся пустой. |
| A01 | Memo включает role ID/version, account/workspace/panel, principal и record context. | `auth/Pg/AccessCheckerTest`: equally-versioned broad → narrow assignment; connection/context switch с одинаковыми IDs/counters и разными матрицами. Две отдельные схемы в corex_test моделируют две tenant DB. |
| A02 | Soft-deleted роли исключены из scope resolution. | Тот же тест: deleted broad плюс live narrow не дают broad scope. |
| A03 | Record/query используют одинаковую ScopeAvailability normalization и одинаковый приоритет direct grant. | Тот же тест: missing capability при существующем owner_id — fail-closed на обоих путях; active direct grant одинаково обходит scope. |
| A04 | System guard проверяет original/persisted state, включая matrix writes через мутированную loaded relation. | `auth/Pg/SystemRolesTest`: сброс is_system и изменение loaded role не обходят запрет. |
| A05 | Bump после outer commit с захваченными tenant key/store. Increment защищён cache lock на LockProvider backends: FileStore increment сам по себе не атомарен. | `auth/Pg/RoleMatrixCompilerTest`: outer rollback/commit. `auth/Feature/ScopeVersionConcurrencyTest`: context switch до callback; четыре процесса сохраняют все 400 bumps файлового cache. Custom backend без locks обязан иметь atomic increment. |

### Точки расширения и module lifecycle

| ID | Исправление | Регрессионное доказательство |
| --- | --- | --- |
| T11 | Provider использует tenancy.provisioning.pipeline. | `tenancy/Feature/ProviderRemediationTest`: custom step вызван реальным TenantCreated. |
| T12 | Root publish target — database/migrations/root. | Тот же тест: root-файлы опубликованы, общий migration directory пуст. |
| M01 | Listeners сохраняются в compiled artifact, регистрируются без дублей, проверяют activity и актуальную декларацию при dispatch. | `modules/Unit/ExtensionRemediationTest`: compile/serialize/restore, повтор registration, active/inactive dispatch. |
| M02 | ActivationGate до migrations/hooks. | `modules/Feature/LifecycleRemediationTest`: deny не меняет schema/mod_modules. |
| M03 | Claims используют отдельный Migrator и копию dispatcher; глобальные listeners не удаляются. | Тот же тест: чужие MigrationStarted/Ended работают после success и failure. Root Event fake сужен до предметного ModuleEnabled, migration events не скрываются. |
| M04 | Cold production compile/optimize не требуют существующего compiled artifact; обычный production bootstrap остаётся fail-loud. | `modules/Unit/ExtensionRemediationTest`: cold compile создаёт файл, обычный console bootstrap без него бросает ошибку. |
| M05 | Compiler отвергает cycles с цепочкой; lifecycle дополнительно защищён от рекурсии. | Тот же тест: A → B → A отвергается до compilation. |
| M06 | Все graph transitions/cascades берут общий DB-local transaction lock. | `modules/Feature/LifecycleRemediationTest`: разные модули на двух PG-соединениях сериализованы; после commit следующий enable проходит. |
| M07 | Flags определяются внутри enable transaction до ModuleEnabled. Idempotent define использует savepoint-safe firstOrCreate. | Тот же тест: definition failure откатывает enable; listener видит flag; re-enable не дублирует его. Store должен участвовать в той же DB transaction. |
| M08 | Entity list получает active snapshot один раз на operation, без memo между operations; compatibility callback — раз на модуль. | `modules/Unit/ExtensionRemediationTest`: 20 entities → один lookup; следующий operation видит изменение активности. Один epoch hasTable/count на operation остаётся. |
| F04 | CRM — corex-module с CrmModule manifest. Runtime provider не auto-loads migrations; test harness загружает их явно. | `crm/Pg/ModuleLifecycleTest`: composer metadata → discovery → compile → enable с migrations/claims → disable без потери таблиц → re-enable. Добавлен CRM PG CI lane. Legacy adoption не выполняется автоматически. |

### Остальные поведенческие дефекты

| ID | Исправление | Регрессионное доказательство |
| --- | --- | --- |
| C01 | Audit job реализует ShouldQueueAfterCommit. | `core/Feature/DeliveryRemediationTest`: queue after_commit=false, nested rollback не сохраняет audit, outer commit сохраняет один. |
| C02 | Publish берёт per-channel transaction lock до seq; commit-order соответствует cursor-order канала. | Тот же тест: второй publisher блокируется за некоммиченным первым; после retry subscriber получает оба. Цена — сериализация одного канала, включая outer transaction вызывающего кода. |
| K01 | Общая TaskCompletion используется Complete и Move в Done: authorizeComplete, запрет Cancelled, timestamp/event. | `tasks/Feature/TasksContractTest`: move не обходит authorization/Cancelled; успешный move вызывает completion event. |
| K02 | Same-stage move — no-op; уже Done не переписывает completed_at/event. | Тот же тест: retry сохраняет timestamp, события не повторяются. |
| F01 | MoveDealToStage reload/lock deal и stage на model connection, проверка pipeline. | `crm/Feature/DealFlowTest`: чужая воронка отвергается без изменения deal/dispatch. |
| F02 | ConvertLead блокирует свежий lead; Converted возвращает существующий Contact без дублей company/deal/event. | `crm/Feature/LeadConversionTest`: stale retry. `crm/Pg/ConversionConcurrencyTest`: два соединения, lock_timeout, retry даёт тот же contact и одно событие. |
| F03 | Activity morph использует configured IdColumns; additive migration расширяет legacy string до 36, down не сужает с потерей данных. | `crm/Pg/ActivityIdTest`: PG insert/morph resolution uuid7/uuid/ulid/int, legacy varchar(26) upgrade затем UUID insert. |
| R01 | PairStore.invalidate сбрасывает admitted/applied operation IDs. Старые apply/regrant запрещены; commit revoked пары возвращает false. | `tenancy/Feature/MembershipPairStoreTest`: admit → apply → revoke → stale operations не открывают доступ. Исходные FencedCredentialIssuance/Expiry suites проходят. Reference не подключён к runtime. |

## Безопасные упрощения

Общая TaskCompletion; общая record/query normalization; transaction-safe FSM helper; удалены fake writer, shared vendor cleanup и избыточный catch вокруг firstOrCreate. Комментарии flags/locks/compiled slices приведены к текущему порядку. Doctrine исправляет test topology/floor 80, CRM README — lifecycle/dependencies/test commands. Крупное дробление lifecycle/provider и BC-изменения constructor DI ради сокращения строк не выполнялись.

## Опровергнуто

Поведенческих находок, исчезнувших до начала исправлений, не обнаружено. B-пункты не объявлены runtime bugs. B14 исправлен в проверяемой части doctrine/CRM README; ULID-формулировки AGENTS/composer descriptions остаются отдельным предложением.

## Проверки

Основной CLI PHP **8.4.1**. Docker PostgreSQL **16**, `corex_pgsql_test`, `127.0.0.1:5434`: healthy, pg_isready и current_database() = corex_test подтверждены. PG suites запускались последовательно. Tenant fixtures создавали/удаляли только свои тестовые DB. Конкуренция — независимые соединения, контролируемый interleaving и SET LOCAL lock_timeout внутри тестовой транзакции, не stress benchmark.

Итого основные наборы: **814 passed, 2238 assertions** (без повторного подсчёта coverage-прогонов).

| Команда/область | Итог |
| --- | --- |
| Root pest --exclude-group=pg | 110 passed, 311 assertions |
| Root pest --group=pg | 107 passed, 356 assertions |
| APP_ENV=testing packages/tenancy/vendor/bin/pest -c packages/tenancy/phpunit.xml | 411 passed, 1067 assertions, включая missing-archive/DROP |
| APP_ENV=testing packages/auth/vendor/bin/pest -c packages/auth/phpunit.xml | 147 passed, 354 assertions, SQLite + PG |
| APP_ENV=testing products/flex-crm/vendor/bin/pest -c products/flex-crm/phpunit.xml | 17 passed, 66 assertions, SQLite + PG |
| Tasks: --exclude-group=pg / --group=pg | 20 / 2 passed, 65 / 19 assertions |
| Root PHPStan, phpstan-tests-lint.neon; PHPStan auth/crm/tasks/tenancy | PASS, 0 errors. В существующем CentralApiClientTest добавлен отсутствовавший import Str. Новых suppressions нет. |
| Root pint --test; git diff --check | PASS |
| bash scripts/mutation-check.sh --all, изолированный snapshot | **30/30** sabotage detected, strict apply, snapshot clean после rollback. P1.18 enable-swallow реанкорен после изменения вложенности без изменения смысла sabotage. |
| Root coverage, --coverage --min=80 | **88.6%**, 217 tests passed |
| CRM coverage, --coverage --min=95 | **95.4%**, 17 tests passed |

Coverage: `XDEBUG_MODE=coverage /usr/bin/php8.4 vendor/bin/pest --coverage --min=80` из root, аналогичная команда с --min=95 из products/flex-crm. Использован уже установленный PHP **8.4.25 / Xdebug 3.5.3**: основной Herd PHP не поддерживает dynamic loading. PHP configuration не изменена. Это локальное доказательство, не current CI GREEN или вся PHP/Laravel matrix.

Mutation runner не запускался на дереве владельца: его fallback содержит reset/checkout. Использована копия `/tmp/corex-audit-mutations-1zcybq` со своей .git, rebased vendor symlinks и snapshot commits **только внутри копии**. Package-файлы сверены побайтово. Логи сохранены в `/tmp/corex-remediation-*.log`; coverage — root-coverage/crm-coverage, suites — sqlite/root-pg/tenancy/auth-all/crm-all/tasks-sqlite/tasks-pg, sabotage — mutations.

## Инцидент Composer/vendor

Offline Composer update, задуманный только для CRM path metadata, неожиданно начал синхронизацию всего vendor со старым lock: installed dependencies и lock исходно расходились. Операция остановилась на недоступных archives после частичного изменения vendor. Это ошибка выбранной проверочной операции, не запрошенный dependency upgrade.

Рабочий composer.lock восстановлен: итоговый diff — **только CRM type/extra.module**, без обновления внешних зависимостей. Root autoload восстановлен. Окружение восстановлено отдельным temporary Composer project с закреплёнными исходными installed versions, включая Laravel 13.31 и Stancl commit `553f57a8adb62ff33e54c99281e7fafd516aab4f`.

**Точное восстановление vendor не заявляется:** исходный symfony/error-handler 8.1.6 оказался недоступен; локально установлен **8.1.5**, добавлен его symfony/polyfill-php82 **1.38.1**. Итоговые проверки выполнены уже после восстановления. Receipt/manifest: `/tmp/corex-vendor-recovery-nvwXXs`.

Cache, созданный новым auth concurrency-тестом до подключения auth base к изоляции, перемещён из shared vendor в `/tmp/corex-remediation-auth-cache-DgFv1L/version-cache` (восстановим). Предсуществовавшие девять опубликованных core PG migrations в shared vendor сохранены.

## Осталось / границы доказательства

- Решения [B01–B14](audit-2026-09-14-proposals.md): переносы и major-contract changes не одобрены/не выполнены.
- Production writer/verifier и restore drill принадлежат deployment-потребителю. Их отсутствие теперь безопасно блокирует offboarding. Наличие/размер файла не доказывают восстанавливаемость backup.
- Исторические CRM tables/claims требуют adoption перед purge; новые установки проверены через lifecycle.
- Неудачный clone остаётся provisioning, не pending. Автоматическая recovery-policy недостроенных clones не добавлена; cleanup marker относится только к pending cleanup.
- M08 устраняет умножение SQL на entities, но одно epoch-чтение на operation остаётся. Изменение epoch storage — отдельное предложение.
- AccountPurged сохраняет документированную семантику события физического DROP; root metadata фиксируется затем координатором. Exactly-once external effects/распределённая атомарность не заявляются.
- Production restore, нагрузочный тест, вся version matrix и удалённый CI не запускались. Membership reference не означает runtime adoption.
- Перед релизным доказательством нужна воспроизводимая installation environment; локальное отличие восстановленного vendor описано выше.
