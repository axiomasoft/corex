# Дизайн фазы P9 — bounded-context-first структура tenancy

**Статус:** нормативный вход items P9.1–P9.8. Это готовая карта
переносов, список неизменных швов и протокол доказательства, а не эскиз.
Каждый item ссылается на конкретный параграф этого документа.

**Не делает:** не меняет миграции, DDL, таблицы, порядок bootstrapper'ов,
маршруты, middleware aliases, состав биндингов, публичные
контракты и имена классов. Не строит multi-account relay control plane: §10.
Единственная runtime-дельта — D49: concrete `TenantContextRestorer` получает
сверку active connection при том же `scoped` lifetime.

**Входы:** `research/P1-design.md` STR-001–STR-006 и §11; `research/P8-design.md`
§2, §5–§9; `research/P9-design-rag.md`; D12, D27, D32, D43–D48; фактический
листинг `packages/tenancy/src` на 2026-09-19.

---

## 0. Обязательные инварианты

1. Один item меняет только `packages/tenancy` и корневой гейт/документ.
2. `packages/tenancy/src/Contracts/**` и namespace `CoreX\Tenancy\Contracts\**`
   байтово не меняются: это public-contract boundary.
3. `TenancyServiceProvider` остаётся корневым Laravel entry point;
   registrar'ы P8 остаются в `src/Laravel` (D44).
4. Физический путь меняется сейчас; стабильный FQCN сохраняется
   точечными PSR-4 maps. `class_alias`, forwarding shells и deprecated-дубли запрещены.
5. Не сохраняются только чисто внутренние namespaces:
   `Membership\Internal\**`, `Wakeup\Internal\**`, `Central\**`, `Database\**`,
   `Oidc\**`, `Workspaces\**`, `Lifecycle\**`, `Migrations\**` и другие
   горизонтальные группы, когда они не входят в матрицу §3.
6. Ни один файл `packages/tenancy/database/**` не меняется.
7. Разница между baseline и финальным fingerprint допускает только
   физические пути, внутренние FQCN из карты §2 и одну binding-дельту D49.
8. После каждого item: package lint, analyse, SQLite suite и корневой
   PostgreSQL lane. Финал добавляет package graph, arch и consumers.
9. Релизная запись — только в корневом `CHANGELOG.md` под `[Unreleased]`.

## 1. Baseline и атомарная карта

P9.1 создаёт `findings/P9-tenancy-map.md` и
`findings/P9-compatibility-baseline.json`. Baseline снимается после P8, перед
первым переносом. Каждый PHP-файл имеет ровно один `from`, `to`, `owner`,
`fqcn_before`, `fqcn_after`, `compatibility_reason` и `item`.

Покрытие атомарно: множество `from` равно всем PHP-файлам `src`
после P8; `to` уникальны; каждый ряд принадлежит P9.2–P9.7 или
помечен `stationary`. Пересчёт «на глаз» не принимается.

## 2. Целевая карта 26 исходных каталогов

| Исходная группа | Целевой владелец | Правило |
|:--|:--|:--|
| `Actions/ApplyMembershipProjection` | `Membership/Projection` | namespace context-first |
| `Actions/ClaimAccountSignup` | `Provisioning` | namespace context-first |
| `Bootstrappers` | `Laravel/Bootstrappers` | FQCN сохранить |
| `Central` | `Membership/Transport` | central — топология, владелец — membership |
| `Commands` | `Laravel/Commands` | FQCN сохранить |
| `Contracts` | `Contracts` | stationary, байтово неизменен |
| `Database` | `Provisioning/Database` | namespace context-first |
| `DomainTrust` | `DomainTrust` | стационарный context |
| `Events` | `Account|DomainTrust|Membership|Impersonation/Events` | FQCN сохранить |
| `Exceptions` | `Authentication|Runtime/Exceptions` | FQCN сохранить |
| `Http/Controllers` | `Authentication/Http/Controllers` | FQCN сохранить |
| `Http/Middleware` | `Laravel/Http/Middleware` | FQCN сохранить |
| `Identity` | `Identity` | стационарный context |
| `Impersonation` | `Impersonation` | стационарный context |
| `Jobs` | `Provisioning|Membership|Identity/Jobs` | FQCN сохранить; queue payload-safe |
| `Lifecycle` | `Account/Lifecycle` | namespace context-first |
| `Listeners` | `Laravel/Listeners` | FQCN сохранить |
| `Membership/Internal` | `Membership/{Credentials,Expiration,Identity,Inbox,Persistence,Projection,Refresh,Switcher,Timing,Transport}` | убрать `Internal`; `Expiry`→`Expiration` только в пути/namespace |
| `Migrations` | `Provisioning/Migrations` | namespace context-first; не database migrations |
| `Models` | `Account|Provisioning|DomainTrust|Impersonation|Identity|Workspace/Models` | FQCN сохранить |
| `Oidc` | `Authentication/Oidc` | namespace context-first |
| `PHPStan` | `PHPStan` | stationary tooling boundary |
| `Provisioning` | `Provisioning` | стационарный context |
| `Resolvers` | `Laravel/Resolvers` | FQCN сохранить |
| `Scopes` | `Laravel/Scopes` | FQCN сохранить |
| `Storage` + root runtime adapters | `Runtime/Storage` + `Runtime` | public contract types не трогать |
| `Wakeup/Internal` | `Integration/Wakeup` | убрать `Internal`; host-first boundary |
| `Workspaces` | `Workspace` | namespace context-first |

После переноса в корне `src` ровно 13 каталогов:
`Account`, `Authentication`, `Contracts`, `DomainTrust`, `Identity`, `Impersonation`,
`Integration`, `Laravel`, `Membership`, `PHPStan`, `Provisioning`, `Runtime`, `Workspace`,
плюс корневой `TenancyServiceProvider.php`. Появление 14-го каталога —
дефект карты.

## 3. Матрица совместимости FQCN

`RAG:✅` P9-Q3: Composer позволяет namespace prefix указывать на
массив каталогов. P9 использует это как переходную физическую
проекцию, не как алиас класса.

Обязательно сохраняют FQCN:

- `CoreX\Tenancy\Contracts\**`, `Models\**`, `Events\**`, `Exceptions\**` —
  public API из P1;
- `Jobs\**` — возможный serialized queue payload;
- `Bootstrappers\**` и корневой `TenancyServiceProvider` — config/package discovery;
- `Http\Controllers\**`, `Http\Middleware\**`, `Commands\**`, `Listeners\**`,
  `Resolvers\**`, `Scopes\**` — route/middleware/console/event/config seams;
- любой дополнительный тип, который baseline P9.1 находит в
  `config/`, `routes/`, Composer `extra`, очереди или вне `packages/tenancy`.

`composer.json` сохраняет broad map `CoreX\Tenancy\ => src/` и добавляет
более точные prefixes на целевые каталоги. Для `Models`, `Events`,
`Exceptions`, `Jobs` значение — массив контекстных каталогов.
P9.1 фиксирует точный JSON по фактическому post-P8 дереву;
исполнитель не угадывает его заранее.

## 4. Runtime и Laravel (P9.2)

`StanclTenancyManager` и `TenancyManagerContextResolver` физически живут в
`Runtime/`; `Storage/**` — в `Runtime/Storage`. `Bootstrappers`, `Commands`, `Http/Middleware`,
`Listeners`, `Resolvers`, `Scopes` переезжают в `Laravel/**`; registrar'ы P8
не двигаются. `PHPStan/**` и provider стационарны.

`RAG:✅` P9-Q4: tenancy bootstrappers применяются в порядке конфига и
откатываются в обратном; экземпляр bootstrapper постоянный.
Поэтому baseline/final сравнивает позиции, FQCN, singleton lifetime и
байтово равный config. Тест повторяет init/revert дважды, чтобы
поймать протечку state между long-lived задачами.

### 4.1 `TenantContextRestorer` и фактическая БД

D22 оставил P9 обязательный выбор. D49 выбирает fail-closed вариант:
`restore()` не поднимает tenancy по payload. Trusted caller сначала устанавливает
контекст; tenancy-реализация restorer делегирует semantic-сверку
`GuardedTenantContextRestorer`, затем проверяет active connection identity.

Cloud identity — `<driver>:<databaseName>`; boxed — `boxed:<accountId>`. Неверные
account/workspace/boxed/database дают `ScopeContextMismatch`; unknown account не
вызывает root-registry lookup. `RuntimeRegistrar` заменяет только concrete
контракта, сохраняя `scoped`. Это единственная допустимая
behavior/binding-дельта compatibility fingerprint.

## 5. Membership (P9.3)

`Central/**` и `Membership/Internal/Transport/**` становятся одним
`Membership/Transport/**`. Остальные девять групп указаны в §2.
`Сentral` не становится bounded context: это место исполнения
транспорта membership. После item пусты оба паттерна:
`src/Membership/Internal` и `namespace CoreX\Tenancy\Membership\Internal`.

## 6. Account и Provisioning (P9.4)

Account owns lifecycle и `Account`, `AccountExport`. Provisioning owns signup,
physical database provisioning, template integrity, migration orchestration, а также
бывшие `Database/**` и `Migrations/**`. `Database` — не домен; это adapter
для provisioning. Миграционные PHP-классы двигаются, а
`packages/tenancy/database/**` — нет.

## 7. Identity и Authentication (P9.5)

`Oidc/**` переезжает в `Authentication/Oidc`; OIDC controllers — в
`Authentication/Http/Controllers`; OIDC exceptions — в `Authentication/Exceptions`.
Identity owns syncer, user model и identity refresh job. Ни route name/method/URI, ни
middleware list/order, ни feature predicate `oidc.enabled` не меняется.

## 8. Workspace, DomainTrust, Impersonation (P9.6)

`Workspaces/**` становится `Workspace/**`; workspace-модели из
`Models/**` живут в `Workspace/Models`. DomainTrust и Impersonation уже
контекстные; к ним переезжают только их models/events. Правила
trusted host, grant expiry, wildcard listeners и middleware aliases байтово
прежние.

## 9. Integration/Wakeup (P9.7)

`Wakeup/Internal/**` переезжает в `Integration/Wakeup/**` и теряет
`Internal` в namespace. Зависимость `corex/wakeup` остаётся `suggest` +
dev-only; optional predicate и `corex.wakeup.hints` tag не меняются.
Package graph allowlist называет ровно эту integration boundary и имеет
негативную посадку на любой другой импорт wakeup.

## 10. Relay control plane и D27

D27 правильно отдал владение каталогом аккаунтов tenancy, но
не дал измеренного сигнала, SLO, operator workflow или контракта
lease/progress. Строить scheduler, state/DDL и concurrency policy в
структурной фазе означало бы выбрать семантику без данных.

D48 уточняет долг: P9 назначает owner `Runtime` и фиксирует триггеры
отдельной работы: наблюдаемое голодание, нарушение SLO или
ручной fan-out, который не укладывается в операционный бюджет.
До этого одноаккаунтный relay D27 остаётся как есть. Это не
закрывает риск; он указан в receipt и roadmap.

## 11. Compatibility fingerprint и гейты (P9.1, P9.8)

Baseline/final JSON содержит:

- public/serialized/config FQCN и `class_exists`/`interface_exists`;
- container abstract, concrete, lifetime и optional predicate из P8;
- ordered bootstrapper list и результат double init/revert;
- routes: name, methods, URI, action FQCN, ordered middleware;
- middleware aliases/groups, commands/signatures, listeners/subscribers/tags;
- migration tree SHA256, model table/connection names, Composer discovery provider;
- PSR-4 optimized/authoritative autoload success и duplicate/ambiguous classes;
- множество внутренних FQCN, которым карта разрешает измениться.

Гейт `scripts/tenancy-structure-gate.py` проверяет 13 корневых каталогов,
запрет `Internal`, атомарность карты и compatibility matrix. Обязательны
две негативные посадки: фиктивный 14-й каталог и
`Membership/Internal/Fake.php`. Ослабление списка вместо исправления кода
запрещено.

P9.8 также закрывает отложенный D19/DATA-003: blocking-гейт
сопоставляет ORM/query-builder writes в `packages/*/src` с owner'ом таблицы из
`docs/architecture/table-ownership.md`. Динамическое имя таблицы без доказуемого
owner падает fail-closed; исключение требует записи в каталоге. Третья
негативная посадка — tenancy-класс, пишущий в таблицу другого пакета.

Финальная цепочка:

```bash
composer --working-dir=packages/tenancy lint:check
composer --working-dir=packages/tenancy analyse
composer --working-dir=packages/tenancy test
composer test:pg
composer package-graph-gate
composer test:arch
composer test:packages
composer dump-autoload --strict-psr --optimize
```

`git diff --exit-code -- packages/tenancy/database` доказывает нулевой DDL-diff.
`composer dump-autoload` запускается так, чтобы не коммитить vendor-артефакты.
