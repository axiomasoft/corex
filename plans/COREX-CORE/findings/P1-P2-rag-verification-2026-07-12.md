# FINDINGS — RAG-верификация внешних фактов P1/P2 (2026-07-12)

**Прогон:** workflow `wf_cc73752d-89b` (8 тем, perplexity-web + context7 + GitHub API), полные
вердикты — journal.jsonl прогона. Здесь — выжимка: что подтверждено, что требует действия.
Items ссылаются на этот файл из Inputs/Required Reads. Дата верификации всех фактов: **2026-07-12**.

## 1. stancl/tenancy v4 + jobpipeline (D8/D16, P2.1+)

| Факт | Вердикт | Детали |
|:--|:--|:--|
| v4 НЕ зарелижен, 0 тегов v4 | ✅ confirmed | GitHub API tags/releases: последний тег v3.10.0 (март 2026). Ветки 4.x нет — v4 идёт в master. Пин `dev-master#<sha> as 4.0.0` остаётся единственным путём. ⚠ Perplexity-поиск в одном из ответов галлюцинировал «v4 зарелижен» — опровергнуто прямым GitHub API; версионные факты проверять только API/raw. |
| jobpipeline 2.0.0-rc7 — последний тег | ✅ confirmed | GitHub API: стабильного 2.0.0 нет. Пин корректен; риск: rc, API может измениться. |
| Octane не поддержан (#1405) | ✅ confirmed | Issue closed as **not_planned** (окт 2025): статики stancl утекают между запросами → cross-tenant leak. FPM-решение плана (D16) подтверждено. |
| Broadcasting #1448 | ⚠ needs-attention | #1448 — **открытый PR** (не issue), fix BroadcastingConfigBootstrapper, НЕ смёржен на 2026-07-12. Баг активен в master. План и так исключает config-bootstrapper (путь BroadcastChannelPrefix) — оставить исключённым, отслеживать PR → OQ-8. |
| **stancl master требует `php ^8.4`** | 🔴 **needs-attention (блокер пина)** | raw composer.json master: `"php": "^8.4"`, `"illuminate/support": "^12.0\|^13.0"`. CoreX держит `php ^8.3` → composer install master-пина НЕ пройдёт. Варианты: (а) бамп php ^8.4 по монорепо (breaking, ADR/D#); (б) sha из истории master, где было ^8.3 (git blame composer.json); (в) отдельное 8.4-окружение. → **OQ-5, решение владельца до P2.1**. |

## 2. UUIDv7 / Laravel 13 (P1.1)

- ✅ **Laravel 13 `HasUuids` по умолчанию генерирует UUIDv7** (дока 13.x eloquent.md дословно:
  «By default, HasUuids generates UUIDv7 identifiers»). Отдельный HasVersion7Uuids — рудимент 11.x, в 13.x не нужен.
- ✅ Нативный `Str::uuid7(?DateTimeInterface)` и `Str::orderedUuid()` — канонические генераторы.
- ✅ symfony/uid 8.1 `Uuid::v7()` — монотонный в пределах ms (RFC 9562); ramsey 4.9 `Uuid::uuid7()` — есть.
- ⚠ Обе либы в lock: канон для P1.1 — **штатный Laravel-путь** (`HasUuids`-default / `Str::uuid7()`),
  прямой импорт `Ramsey\Uuid`/`Symfony\Component\Uid` в коде пакетов не нужен и нежелателен.
- ✅ `$table->uuid()` → нативный PG-тип `uuid` (PostgresGrammar).

## 3. PHPStan 2.2 / larastan 3.10 (P1.13, P2.13)

- ✅ `PHPStan\Rules\Rule` 2.x: `getNodeType()/processNode()`; **`RuleErrorBuilder` с обязательным
  `identifier`** (2.x требование); `PHPStan\Testing\RuleTestCase` — канон тестов правил; регистрация
  services+rules в extension.neon.
- ⚠ **Per-path level override в одном phpstan.neon НЕ поддерживается** — «level 8» для отдельного
  пакета требует split-config (второй neon + отдельный CI-шаг). Для custom rules это неважно:
  Rule-классы срабатывают на ЛЮБОМ level → формулировку «level 8» из P1.13 Intent убрать.
- ✅ larastan 3.10 совместим с phpstan 2.2.2.

## 4. PostgreSQL 16 + PgBouncer transaction pooling (P1.6–P1.11, P1.13, R-15)

- ✅ Официальный pgbouncer features: `SET/RESET` — Never; `LISTEN` — Never (**`NOTIFY` — работает**);
  session advisory locks — Never (xact — OK); `WITH HOLD` cursors — Never; temp tables — только
  `ON COMMIT DROP`. ⚠ **PREPARE:** с PgBouncer ≥1.21 (default c 1.24) `max_prepared_statements>0`
  поддерживает protocol-level prepared statements — тотальный запрет плана консервативен, но валиден;
  зафиксировать зависимость от версии PgBouncer в инфре.
- ✅ `pg_advisory_xact_lock(hashtext(...))`: hashtext → int4, коллизии возможны (лишняя сериализация,
  не потеря корректности); сигнатуры int8 / 2×int4.
- ✅ `UNIQUE NULLS NOT DISTINCT` — PG15+; в Laravel schema builder может требовать raw-выражения.
- ✅ ltree: метки `[A-Za-z0-9_]`, uuid-hex (32 симв.) влезает с запасом; GIST + `<@`/subpath/nlevel — подтверждены.
- ✅ **ltree — trusted extension PG13+**: `CREATE EXTENSION` достаточно роли с CREATE на БД
  (НЕ superuser); RDS/Cloud SQL разрешают. Отразить в P1.10.
- ✅ BRIN по timestamptz append-only — выигрывает у btree на больших объёмах (pages_per_range 128 дефолт).

## 5. Laravel 13 breaking-скан (все items P1)

- ✅ Минимальный PHP, queued listeners/ShouldQueue, Cache tags, package discovery
  (installed.json extra.laravel), Redis pub/sub API — **без breaking для плана**.
- ⚠ `cache.serializable_classes=false` дефолт (hardening): кеш не-скаляров (DTO) требует
  явного allowlist в config/cache.php — учесть, если ModuleStateReader/scope-map кеширует объекты
  (рекомендация: кешировать массивы-скаляры).
- ⚠ Морф-pivot: L13 изменил плюрализацию имён polymorphic pivot-таблиц — кастомным pivot-моделям
  задавать `$table` явно.
- unclear: encrypted casts — breaking не подтверждён (официального упоминания нет; оставить как есть).

## 6. Тест-лейн (P1.6, D12)

- ✅ Pest 4 `->group()` / `--group=pg`, `--exclude-group` — механика подтверждена; testbench 11
  совместим с Laravel 13, паттерн монорепо path-пакетов валиден; docker healthcheck `pg_isready` — канон.
- ⚠ `pest --parallel` НЕ даёт изоляции PG на воркер — нужен template-clone per worker. План на
  последовательный прогон — противоречия нет; при переходе CI на parallel — отдельный item.

## 7. Auth-стек P2 (P2.8–P2.11)

- ⚠ Fortify: матрица совместимости Laravel 13 = ^1.34.1–^1.36.2 (v1.37.2 вне матрицы) →
  constraint `laravel/fortify: ^1.36` зафиксировать в P2.10; на exec сверить матрицу заново.
- ✅ (unclear→подтверждает план) Socialite не имеет first-class generic-OIDC+PKCE — собственный
  OidcClient в P2.10 оправдан. jumbojett/openid-connect-php — без стабильного тега с 2024, не брать.

## 8. Прочее PG (P1.8/P1.11)

- ✅ jsonb TOAST ~1ГБ теорпредел, практический комфорт ≤ единиц МБ — лимит 64КБ Settings валиден.
- ✅ Партиционирование append-only лога — актуально от десятков млн строк; отложение (§9.7) валидно.
