# Отчёт CoreX для FlexCRM — 14.09.2026

Работа выполнена **вне Task-планов**, с приоритетом владельческого требования: CoreX содержит
переиспользуемую платформу и чистые API; домен CRM и продуктовая композиция остаются у своих
владельцев. Исходный запрос `corex-owning-platform-api-request.md` принят в этой границе.

**Результат:** реализован и проверен базовый опциональный `corex/api` и совместимая точка
проверки workspace-членства по доверенному principal. Это позволяет CRM-модулю объявлять
операции, а FlexCRM — подключать свою аутентификацию, политики и контекст без копирования
CoreX и без обхода доменных Actions.

**Полная поставка CRM из исходного запроса НЕ квалифицирована GREEN.** Этот отчёт не разрешает
P17.1, установку CRM в рабочую БД, публикацию или production deployment. Разделы о CRM-схеме,
продуктовых адаптерах и полном lifecycle относятся к следующей работе соответствующих владельцев.

## Коммиты и граница изменений

Исходный checkout: `d3eb6608d8671273f15e35cf91dc41ddc28a7632`.
Это новее указанного в запросе `2b426ee`; начальные локальные изменения также сохранены.

- `e38ea0e0b4e6abf3ac705e33d9d3056a3adacbe6` — `corex/tenancy`: principal workspace SPI.
- `69ddf52f1365138c8d469c62a547c83ac6613918` — `corex/modules` + новый `corex/api`, тесты,
  CI, гейты и воспроизводимый installed probe.

В коммиты не включены чужие изменения feature flags, database-poll PubSub, domain-trust,
проверки шаблонов/provisioning и Task-планов. В общем tenancy provider и changelog сохранены
только собственные строки этой задачи; остальная работа остаётся локальной. Provider приведён
к Pint без изменения смысла соседних локальных правок. Старый `delivery/crm-parity-20260914`
не изменён и не переквалифицирован.

| Concern | Владелец | Решение и причина |
|---|---|---|
| Явное объявление API-операции и её module provenance | `corex/modules` | Добавлено в существующий `Api`; полезно любому модулю, без нового extender или зависимости на API-пакет. |
| Диспетчер, версии, активность модуля, envelope и ошибки | `corex/api` | Реализовано как базовая платформенная граница. Нет отражения произвольных контроллеров или auto-CRUD. |
| Principal, authorization, typed context, serializer/schema SPI | `corex/api` | Интерфейсы; default-deny; реализации заменяются через DI. Ни guard, ни role engine, ни пользователь продукта не выбраны. |
| Доступ адаптера Action к уже допущенному API-контексту | `corex/api` | `RequestContextProvider`; действует только во время операции, без singleton-состояния. |
| Physical tenancy и workspace membership | `corex/core` / `corex/tenancy` | Существующие tenancy engine/контракты переиспользованы; добавлен совместимый principal resolver, без новой tenancy-системы. |
| OpenAPI проекция | `corex/api` | Генерируется из того же compiled operation registry; каталог endpoint'ов не дублируется. |
| Набор company/contact/M2M/options операций и DTO mapping | `flex/crm` | Должен объявляться CRM-модулем и вызывать его Actions/queries. В этой работе не реализован. |
| Полная CRM-схема, lost reasons, activity targets, upgrade/lifecycle | `flex/crm` | Доменная работа по утверждённой CRM-спеке; в L0 не добавлялась. |
| AzGuard/guard/OIDC/session adapters, mapping прав, route prefix, claims | FlexCRM | Продуктовая композиция поверх публичных SPI. CoreX не владеет этой политикой. |
| Custom-domain trust и текущие dirty concurrency fixes | Их владельцы CoreX | Независимые concerns; этой поставкой не зафиксированы и не квалифицированы. |
| Полный immutable set из пяти пакетов и physical installed reuse | Owning delivery integration | Частично: проверены архивы core/modules/api и учебного модуля. Полный CRM/tenancy set не заявлен. |

## Что теперь умеет платформа

В `Manifest::extends()` модуль может использовать `Api::operation(...)` с:
`id`, `method`, `path`, `handler`, `ability`, `requestSchema`, `responseSchema`, `rules`,
`version` и `successStatus`. Владелец операции берётся только из манифеста при компиляции.
Существующие `Api::entity()` / `extendResource()` остаются metadata и не открывают CRUD.

Компиляция проверяет декларации, повторные ID/эквивалентные method/path, повторные path params,
неоднозначные маршруты одинаковой специфичности и согласованность имён эквивалентных шаблонов
между HTTP-методами. Runtime дополнительно проверяет `OperationHandler`. Старые compiled
resource rows продолжают читаться с defaults новых свойств.

Диспетчер принимает только объявленную операцию нужной версии, разрешает principal,
получает доверенный контекст, проверяет активность установленного модуля и authorization,
валидирует input и вызывает явный handler. Активность модуля и контекст читаются при каждом
запросе, в том числе после предыдущего успешного вызова.

GET использует query input. Изменяющие операции принимают JSON-object body без query params.
Необъявленные поля дают 422. Path params передаются отдельно; их ID-формат и видимость
ресурсов проверяет модуль. Principal/account/connection никогда не выводятся из этих данных.
Request context отделён от input и доступен обработчику явно.

Пакет не auto-discover'ится. `ApiServiceProvider` регистрируется приложением вручную и сам
не создаёт routes, не включает модули и не запускает миграции. Default principal resolver
возвращает null; default authorizer запрещает всё. Physical context обязателен по умолчанию;
boxed API требует явного `corex-api.require_physical_tenancy=false`.

Ответы имеют стабильный `data/error/meta` envelope и `X-Request-ID`, `Cache-Control: no-store`.
Невалидный correlation ID заменяется сгенерированным. Основные статусы:
401 authentication; 403 permission/context/account; 404 неизвестная версия/операция,
неактивный модуль или скрытый/чужой ресурс; 409 явный доменный conflict; 422 validation;
500 безопасная ошибка с логированием исходного исключения. HTTP 429 сохраняется; другие
4xx также сохраняют статус с безопасным сообщением. Raw SQL/tenancy exception messages
клиенту не выдаются. `ErrorBoundary` нормализует также исключения окружающего Laravel pipeline.

## Как FlexCRM продолжать работу

1. Подключить совместимые `corex/modules` и `corex/api`, вручную зарегистрировать
   `CoreX\Api\ApiServiceProvider`. Выбрать собственный route prefix с именованными параметрами
   `version` / `path` и поставить `ErrorBoundary` перед tenancy/auth/workspace middleware.
   Проверить фактический Laravel middleware priority. Продуктовый `/w/` или context-version
   header в CoreX не зашиты.
2. Bind `PrincipalResolver` к доверенному session/token/OIDC/service adapter и
   `OperationAuthorizer` к политике приложения. Модуль задаёт ability key, приложение
   интерпретирует его. Account admission, session/account mismatch и principal provenance
   должны проверяться существующей доверенной цепочкой; поддельный actor из input не принимается.
3. Для token/service workspace использовать
   `CoreX\Tenancy\Contracts\PrincipalWorkspaceResolver::resolveFor($principalId, $slug)`.
   Он проверяет active membership в уже выбранной account DB, без implicit `auth()` и без cache.
   Затем вызвать `TenancyManager::setWorkspace()` и проверить effective context. Делать это
   после authentication для того же principal. Старый session `WorkspaceResolver::resolve()`
   остаётся совместимым. `NoDefaultWorkspaceException` преобразовать в безопасный 403 context error.
4. В **`flex/crm`**, либо отдельном модуле интеграции поверх него, объявить bounded initial set:
   companies list/card/create; contacts list/card/create; typed association create; options.
   Размещение в integration module означает, что gate относится к этому модулю: активность
   `flex/crm` при этом тоже должна сохранять проверку в его существующем `ContextGuard`.
   Предпочтительный owning-вариант — объявления непосредственно в `CrmModule`.
5. Каждый handler реализует `OperationHandler`. Create handlers преобразуют validated input
   в `CompanyData` / `ContactData`, затем вызывают `CreateCompany` / `CreateContact`.
   Association вызывает `AssociateContactCompany` с именованными аргументами. Read handlers
   используют scoped queries и явный response projection; null/foreign/hidden ID дают 404.
   Не добавлять PATCH/DELETE или прямую запись мимо Actions.
6. Связать существующий `Flex\Crm\Contracts\CrmExecutionContext` с доверенной identity/context
   композиции. API-адаптер может inject `CoreX\Api\Contracts\RequestContextProvider` и получить
   **тот же** `RequestContext`, который допущен диспетчером. Из него берутся tenant и principal;
   connection name выбирается из initialized manager/connection resolver, actor name/source —
   из доверенной identity, account-level admission — из явной политики. Service actor требует
   уже предусмотренного CRM admitted run-as user. `ContextGuard` продолжает проверять
   account/PDO/principal/membership/module и повторно проверять их под lock внутри Actions.
7. Доменные duplicates/second-primary в текущем association Action являются
   `ValidationException` и автоматически дадут **422**. Если CRM API должен отдавать **409**,
   owning handler/domain должен явно классифицировать эти конфликты и создавать безопасный
   `ApiError('conflict', 409, ...)`; CoreX не угадывает conflict по тексту SQL или validation.
8. Сгенерировать OpenAPI через
   `app(CoreX\Api\OpenApi::class)->document('v1', $chosenVersionedServerUrl)`.
   Добавить собственную authentication security scheme в профиль и генерировать клиента
   из результата. CoreX даёт installed-code catalogue, а не список доступных текущему tenant прав.
   Сам OpenAPI endpoint или client generator не установлен.
9. Отдельно завершить approved CRM storage/lifecycle и qualification, затем P17.1.
   Сохранить отказ populated-upgrade до мутации и explicit export/provenance recovery.
   Исторические migrations и старую receipt не переписывать.

Подробные контракты, параметры и пример декларации: `packages/api/README.md`.
`RequestContextProvider` действует только внутри операции и требует текущий Laravel Request;
для ручного dispatch другого Request нужна его container binding либо свой provider.
Default context resolver доверяет уже проверенному workspace pipeline; приложению нужно
согласовать principal этого pipeline с API identity, в особенности для token/service adapters.

`requestSchema` / `responseSchema` служат JSON Schema документации; default runtime использует
явные Laravel string `rules`, а не полный JSON Schema validator. Их соответствие и domain
constraints надо проверять в owning module tests. Serializer/schema SPI предназначены для
расширения; breaking envelope/schema требует явной границы версии/профиля. Middleware,
возвращающее собственный response, должно само соблюдать envelope; custom exception handler
должен сохранять стандартную Laravel `withException()` metadata для `ErrorBoundary`.

## Проверки и immutable installed evidence

| Проверка | Фактический результат |
|---|---|
| API package `composer test` | **40 passed**, 177 assertions. Compilation/roundtrip, routing/version, default deny, context/profile, activation freshness, forged/malformed input, envelope/errors, OpenAPI, HTTP middleware/context handoff. |
| API coverage, PHP 8.4.25 + Xdebug 3.5.3 | **96,9%**, floor 80 не понижен. |
| Root SQLite `composer test` | **151 passed**, 489 assertions; API suite включён с per-package размещением. |
| Root architecture `composer test:arch` | **6 passed**; `api` включён в literal/import scan и запрет dependency вверх. |
| Root + API PHPStan | **PASS**. В package log есть предупреждение herd PHP о turbo extension, exit 0 и анализ без ошибок. |
| Root Pint | **PASS**. |
| Standalone modules-lint на тестах | **FAIL**: два findings в существующих CRM tests; stale shared cache устранён, API tests включены, suppression не добавлялся. |
| Anti-weakening gate | **PASS**; counts выросли, floor/exceptions не ослаблены. |
| Contract guard от исходного commit | **PASS**, новые Contracts сопровождаются package changelog. |
| `bash scripts/mutation-check.sh --all` | **34/34 killed**, включая 4 новых API mutants; откат target hashes проверен runner'ом. |
| Principal workspace на чистом `e38ea0e` checkout | **1 passed**, 23 assertions: две actual account DB, два workspace, same ID/slug, A → B → A, revocation, default/null/foreign/empty/unauthenticated resolution. |
| Legacy workspace resolver regression | **21 passed**, 39 assertions; также повторён на чистом owning commit. |
| Root PG `composer test:pg` | **103 passed / 4 failed**. Все четыре failures воспроизведены на чистом исходном `d3eb660`; полный lane НЕ GREEN. |
| Root combined coverage command | **249 passed / 4 failures / 5 Audit morph-map errors**; общий coverage floor не квалифицирован. |
| Fresh installed ZIP reuse, Laravel 13.31.0 | **PASS**, два независимых consumer, identical locked set, independent app keys, installed vendor autoload без symlinks. |
| Fresh installed ZIP reuse, Laravel 12.69.2 | **PASS**, те же архивы; два новых consumer с отдельным locked dependency set для Laravel 12. |

Проверенные команды:

```bash
composer --working-dir=packages/api test
composer --working-dir=packages/api analyse
XDEBUG_MODE=coverage /usr/bin/php8.4 packages/api/vendor/bin/pest -c packages/api/phpunit.xml --coverage --min=80
composer test
composer test:pg
composer test:arch
composer lint:check
composer analyse
composer analyse:tests-lint
bash scripts/contract-guard.sh d3eb660
bash scripts/anti-weakening-gate.sh
bash scripts/mutation-check.sh --all
XDEBUG_MODE=coverage /usr/bin/php8.4 vendor/bin/pest --coverage --min=80
# Следующие selectors также выполнены в clean owning-SPI worktree на e38ea0e:
composer --working-dir=packages/tenancy test -- --filter=PrincipalWorkspace
composer --working-dir=packages/tenancy test -- --filter=WorkspaceResolver
# Чистый исходный d3eb660, до API изменений:
vendor/bin/pest packages/modules/tests/Feature/LifecycleRemediationTest.php --group=pg
```

Основной CLI PHP — herd 8.4.1; coverage CLI — system PHP 8.4.25. PostgreSQL — **16.14**,
Docker service `pgsql`, host `127.0.0.1:5434`, control target `corex_test`.
Captured owning-SPI test account DB:
`corex_principal_a_26ac00330d91_test`, `corex_principal_b_26ac00330d91_test`.
Обе создавались только тестом и удалены в `finally` через guarded DROP DATABASE.
API harness использует SQLite `:memory:` и platform stubs. Рабочие продуктовые базы не мигрировались.

Installed probe собирает ZIP непосредственно из **committed tree**, без dirty source:
`corex/core`, `corex/modules`, `corex/api`, учебный `example/notes`.
Последний — fixture для доказательства reuse, без CRM-domain и без persistence.
Composer сначала работает с `--no-scripts`; затем explicit application bootstrap/discovery/compile.
Root package repositories содержат ZIP dist с commit reference и SHA1 checksum;
path repositories, aliases и replaces для этого набора не используются.
Оба приложения выполняют HTTP statuses **200 → 404 undeclared → 422 forged → 404 disabled after warm**.

| Архив | SHA256 |
|---|---|
| `corex/core` | `e9bb492fc99badab52c3a49a2fd705ea282127ba873ca47b0855c9a5890aeed5` |
| `corex/modules` | `99e6417f16e30165c9c759873268a34fd63560dae11cdde214287e88d436114c` |
| `corex/api` | `2a53af4f344af87d21145f59d8a4b62c1608e1d2495103f8623d6f2314de2f75` |
| `example/notes` fixture | `bfc293ecf43ea389c52c6671d8ceb50697ef429b43cd586959397e4266c02c19` |

Package subtrees at `69ddf52`:
core `62ad7daeeb13d1274598816f01b88e4809bdf017`;
modules `10f03b48de20ae026c7d52b62c41913dfb18563f`;
api `571293a619470210cbdb94bac70c0586a1fd9fe5`.
Tenancy owning-SPI subtree at `e38ea0e`: `c19cee926e819d02b2d8d273b16554bfd8ceba8d`.

Generated OpenAPI SHA256 в обоих Laravel profiles и всех consumers:
`0f21927cfcb563b277c579bddc3f23771117cf8f6cd49414a14e6c02ae196572`.
Laravel 13 consumer lock SHA256:
`0fa38590e2234131f24ff51dd3ab8d334f1450b1ce17e6e3b2021c48e2842720`;
Laravel 12 consumer lock SHA256:
`077ba27459a82c4f4ca8944f8fdabea0c1c9d9a5d89a45dddc054c7d68f5c2a4`.
Actual locks, documents, install logs и test outputs сохранены в `evidence/`;
SHA256 каждого файла — в `evidence/sha256.json`.

Воспроизведение isolated installed subset:

```bash
python3 delivery/corex-api-extension-20260914/verify-installed.py --ref 69ddf52f1365138c8d469c62a547c83ac6613918
python3 delivery/corex-api-extension-20260914/verify-installed.py --ref 69ddf52f1365138c8d469c62a547c83ac6613918 --laravel '12.*'
```

Скрипт создаёт только новые `/tmp/corex-api-installed-*_test` directories. Сначала разрешает
и фиксирует выбранный Laravel profile, затем второй consumer устанавливает тот же lock.
Re-resolution upstream dependencies в новом запуске может изменить consumer lock;
записанные locks фиксируют именно проведённую проверку, package commit/dist hashes стабильны.

## Что остаётся до полной GREEN CRM-поставки

- CRM operation declarations/handlers/read projections и product identity/policy/context adapters.
- Approved full CRM schema catalogue, `crm_lost_reasons`, `crm_activity_targets`, fresh enable /
  disable / re-enable / populated-upgrade refusal; complete storage introspection не выполнена.
- API happy-path CRM Actions, typed-association race/second-primary/duplicates и rollback/event
  invariants за новым HTTP API; не квалифицировать их по generic echo fixture.
- Physical API host/session admission, forged account/connection handling и два independent
  physical applications — reuse probe здесь **boxed/service fixture**, не physical acceptance.
- Четыре исходных lifecycle defects: denying activation gate до migrations/hooks,
  dependency-graph locking, сохранение migration listeners, atomic feature-flag definitions.
  Они блокируют полный root PG GREEN; их устранение не подменяется новым upper API gate.
- Дополнительные Audit morph-map errors в combined coverage run: не устранены и не объявлены
  чужим baseline без полной квалификации. Attempt полного baseline all-suite остановлен из-за
  relation lock на DROP SCHEMA; отдельный lifecycle selector даёт надёжное исходное evidence.
- Коммит/тесты/версии существующих dirty feature flags, PubSub, domain-trust/template changes;
  они не являются частью опубликованного immutable dependency set.
- Полный exact set `corex/core + modules + tenancy + api + flex/crm`, package/root locks и
  physical installed qualification. Root монорепо lock остаётся developer path lock. В tenancy
  сохраняется исходный stancl dev-master alias; no-alias full-set требование этим отчётом не закрыто.
- Standalone tests-lint: `packages/crm/tests/Pest.php:10` (`$this` вне известного PHPStan scope)
  и `packages/crm/tests/Pg/ParityContractTest.php:273` (session-level `SET` без разрешённого исключения).
  В этой работе не подавлялись и CRM tests не менялись. Для tests-lint добавлен отдельный
  project-local tmpDir, чтобы кэш не ссылался на старый checkout.
- Full affected package coverage floors, whole-suite/CI GREEN и полный consumer admission.
  Добавлен API CI job для Laravel 12/13 с 80% floor; удалённый CI run не запускался.

Платформенные API и способы расширения доступны для owning продолжения. Публикация, tag,
split mirrors, product migrations и deployment в этой задаче не выполнялись.
