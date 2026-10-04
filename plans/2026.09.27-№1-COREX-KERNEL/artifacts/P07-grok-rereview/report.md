Начинаю независимое техническое ревью дизайна P07 только чтением: сначала нормативные документы плана, затем сверка с кодом, схемами и гейтами. Авторские вердикты не принимаю заранее.Технический вердикт: дизайн P07 после D98–D101 к исполнению не готов. Два Major остаются в нормативных файлах. Это не `task:plan-audit` и не GREEN. Продуктовые items P07.1–P07.6 не начаты. Сканер G2 и доказательства автора я не запускал.

Шесть первоначальных findings 1–4 и 6–7 в текущем тексте закрыты. Число finding 5 / D99 не меняю: в конфиге сканера `tenancy` нет, в baseline по-прежнему `"G2": 19`.

## Шесть первоначальных findings

| # | Было | Сейчас |
|---|---|---|
| 1 Critical, `url` | `DatabaseManager` снова прогоняет конфиг через `ConfigurationUrlParser`, и URL побеждает поля | Закрыт в примерах. `CentralTemplateConnectionResolver` и `ConnectionBootstrapper` вызывают `parseConfiguration`, снимают `url`, подставляют `db_name` и запрещают routing-ключи в `options`. Повторный разбор уже не видит `url`. |
| 2 Major, сессия | Живой handler оставался на central, первая привязка не сбрасывала auth | Закрыт в примерах. Handler пересобирается на том же `Store`, первая привязка делает `invalidate()` и `regenerateToken()`, priority ставит `ResolveTenantFromHost` перед `StartSession`. API ядра это позволяет. |
| 3 Major, зеркало аудита | `QueuedAuditLogger` только ставит job и при выключенном аудите молчит; цикл с `ImpersonationService` | Закрыт для sink/writer. Отдельный `SynchronousAuditWriter` пишет `saveQuietly` до login и не зависит от `AuditLogger`. Тело `start()` задано порядком в D100, не отдельным методом. |
| 4 Major, `unreleased` | Гейт знал только четыре stability | Закрыт для `package-graph-gate.php`, `taxonomy.md` и сниппета `manifest-check.py`: пятое значение и `UNRELEASED` только для моста. |
| 5 Major, G2 19→17 | Обещание не совпадало со сканером | Не меняю. `scripts/runtime-globals.json:13-34` не содержит `tenancy`. `scripts/runtime-globals-baseline.json:4` держит `"G2": 19`. Сканер не запускался. |
| 6 Minor, approver | `is_staff` проверялся только у заявителя | Закрыт в SQL примера: отдельный `whereExists` по `approver.is_staff`. Ячейка non-staff есть в матрице. PostgreSQL не гонялся. |
| 7 Minor, digest | Фабрика всегда резолвила `WakeupScheduler` | Закрыт: при выключенном digest фабрика передаёт `null` и binding не резолвит. Включённый `null` обязан падать до записей. |

## Доказанные дефекты

### 1. Major. `CoreX\Scheduling` не входит в закрытый список гейта раскладки

`scripts/module-layout.json:4-19`, правило `scripts/module-layout-gate.py:163-164`. Файлы P07.5 этого json не содержат (`phases/P07/P07.5.md:13`), а Validation требует `composer module-layout-gate` (`phases/P07/P07.5.md:24`). Список модулей в `scripts/ownership-map.json:177-193` P07.5 обновляет, гейт раскладки читает другой файл. D29 по-прежнему фиксирует 15 модулей без `Scheduling`; D96 добавляет модуль только в glossary.

Сценарий: `git mv` контрактов в `packages/core/src/Scheduling/`. Каталог не входит в `kernel_modules`, поэтому R1 даёт новую запись «kernel root holds only CoreServiceProvider and kernel modules». Ratchet на новой записи завершается с exit 1. Свой Validation P07.5 не выполняется внутри объявленных Files.

Норма: D57 (ядро только из закрытого списка) и D96 п.8 (модуль ядра `CoreX\Scheduling`).

Ремонт: добавить `Scheduling` в `scripts/module-layout.json` между `Observability` и `Settings`, включить файл в Files P07.5 и явно поправить закрытый список D29. Прогнать `module-layout-gate`.

### 2. Major. Статус-гейт теряет владельца аккаунта

`packages/tenancy/src/Laravel/Http/Middleware/AccountStatusGate.php:36-38` и `:83-87`. Замена в досье — только `TenantContextResolver` (`research/P07-design.md:28` и `:127`). `AccountRef` несёт `id`, `slug`, `status`, `features`, `limits` (`packages/core/src/Context/Data/AccountRef.php:22-28`) и не несёт `owner_identity_id`. Этот идентификатор уже умеет читать `AccountRead::ownerIdentityId` (`packages/tenancy/src/Account/Internal/SqlAccountRead.php:13-17`), но P07.1 его для гейта не называет. Примеры гейт не задают.

Сценарий: suspended или grace. `isOwner()` сравнивает `auth()->id()` с `owner_identity_id` и пускает владельца на payment/export. Буквальная замена `tenancy()->tenant` на `current()->account` не компилируется: метод ждёт модель `Account`. Если проверку владельца убрать, владелец тоже получает 403 и не может оплатить или выгрузить аккаунт. Если её заменить на `return true`, любой пользователь аккаунта проходит на эти маршруты.

Норма: текущий fail-closed B-11, который P07.1 обязан сохранить, меняя только источник `tenancy()`.

Ремонт: статус брать из `AccountRef`, владельца — из `AccountRead::ownerIdentityId` на central. Зафиксировать матрицу: владелец на настроенном маршруте проходит, остальные 403. Добавить тест suspended/grace.

### 3. Minor. `ScopeSessionToTenant` ждёт `null` там, где контракт бросает другое исключение

`artifacts/P07-design/examples.md:579`. `TenantContextResolver::current()` объявлен как `TenantContext`, не `?TenantContext` (`packages/core/src/Context/Contracts/TenantContextResolver.php:18`). Штатный адаптер при отсутствии контекста бросает `CentralContextException` (`packages/tenancy/src/Runtime/Internal/TenancyManagerContextResolver.php:24-27`). Оператор `??` мёртв. PHPStan на скопированном методе расходится с сигнатурой, а HTTP-ошибка будет не `tenancy.context_unavailable`.

Ремонт: читать `TenancyManager::context()` и при `null` бросать `TenantContextUnavailable::notInitialized()`.

### 4. Minor. Ошибка cleanup подменяет исходный отказ и не сохраняет обе причины

`artifacts/P07-design/examples.md:97-107` и `:173-176`. Если `revertAll()` бросает, исключение выходит из `try`, а `throw $failure` после `finally` не выполняется. `previous` не ставится. Строка 199 обещает сохранить обе причины в логе. Код этого не делает. Runtime при этом помечается poisoned, так что центральное состояние безопасным не объявляется.

Ремонт: логировать исходный отказ и вешать его на исключение cleanup через предыдущее исключение.

## Непроверенные риски

Прямой grant в `aut_direct_grants` по D96 не имеет workspace. Роль в чужом workspace даёт false, grant на `deals.view` открывает строки всех workspace аккаунта. Это записанный контракт, не расхождение.

`initialize` при том же account возвращается без `resetScope`. Смена базы при этом не происходит. Захвата workspace в конструкторе scoped-сервиса я не нашёл.

`SessionConnectionBootstrapper` во время `runFor` или sync job переназначает handler уже открытого HTTP `Store` и специально не чистит attributes. Обычный `StartSession` сохраняет сессию после восстановления handler. `session()->save()` внутри `runFor(B)` записал бы attributes A в хранилище B.

`Filesystem::ensureDirectoryExists` не меняет права существующего каталога и не отвергает symlink. `FileSessionHandler::write` возвращает true даже после неудачного `put`. D101 закрывает отсутствие каталога, не этот случай. При общем OS-пользователе произвольная запись в каталог сессий и так равна компрометации процесса.

`scripts/distribution/release_candidate.py:142` принимает только `experimental|stable`, а `:149` отбирает имена `corex/*`. Пакеты называются `axiomasoft/corex-*`. Канал `packages/access-azguard` в `release-1.0-policy.json` не предусмотрен. Это baseline: P07 его не лечит. После починки фильтра мост `unreleased` снова упрётся в релизный контур, а P09 только обещает исключение.

`release_candidate` и полный provider boot со stancl `dont-discover` я не воспроизводил.

## Ограничения

Bash, тесты, `six-findings-proof.php`, `p07-facts.sh`, сканер G2, PostgreSQL и subagents не запускались. Совпадение URL, session handler, `JobAttempted` и `SortedMiddleware` с Laravel проверено чтением vendor, не прогоном. Авторский `six-findings-proof.json` (43 checks, sqlite memory, без PG commit/interval и без Redis/memcached daemon) доказательством полноты не считался.

`handoff.md:15` всё ещё говорит «current open findings: 1 Critical, 3 Major, 2 Minor», а `:17` уже относит 1–4 и 6–7 к D100. Норма — D100 и примеры, не эта строка.

Hosted CI по `findings/01-hosted-ci-debt.md` давно красный (H1–H12). D73 как закрытие P07 этим ревью не подтверждён. Командный HEAD `4137d241` не перепроверялся: читалось текущее дерево workspace.

## Покрытие чтения

Читал `AGENTS.md`, `plans/ACTIVE.md`, `plan.md` (маршруты P07), `handoff.md`, `phases/P07/P07.md` и P07.1–P07.6, D94–D101, `research/P07-design.md`, `findings/P07-design-audit.md`, `findings/P07-grok-review.md`, triage и followup-заголовок, `artifacts/P07-design/examples.md` целиком, remediation (`remediation.md` по заголовкам, `six-findings-proof.php`, `six-findings-proof.json`, `file-session-directory.php`).

Дальше по ссылкам: `ConfigurationUrlParser`, `DatabaseManager::configuration`, `Worker::process`, `SyncQueue::executeJob`, `SortedMiddleware`, priority ядра, `SessionManager`, `Store`, `FileSessionHandler`, `Filesystem::ensureDirectoryExists`, `AuditEntry`, `AuditLog`, `HasConfiguredId`, `QueuedAuditLogger`, `RecordAuditEntry`, миграции `sys_audit_log` и `root_account_events`, `SqlDigestBuilder`, `NotificationsDigestRegistrar`, `DefaultAccessChecker`, `DefaultScopeResolver`, `ActiveEntityFilter`, `WakeupHandlerDefinition`, `WakeupBindingsRegistrar`, `AccountStatusGate`, `OidcLoginController`, `AccountRef`, `SqlAccountRead`, `HttpRegistrar`, `package-graph-gate.php`, `taxonomy.md`, `manifest-check.py`, `runtime-globals.json`, `runtime-globals-baseline.json`, `module-layout.json`, `module-layout-gate.py`, `ownership-map.json`, `release_candidate.py`, `release-1.0-policy.json`.

`research/P07-design-rag.md` и сырые captures заново не перечитывал: внешние факты W1–W7 сетью не проверялись.
