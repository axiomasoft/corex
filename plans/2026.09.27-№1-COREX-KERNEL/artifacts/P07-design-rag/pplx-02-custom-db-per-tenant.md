# pplx-02 — DB-per-tenant without a package: switching, queue context, cache, Octane

- Date: 2026-10-04
- Tool: perplexity-web (`mcp__perplexity__search`, default settings)
- Query: `Laravel multi-tenancy database-per-tenant without a package: minimal custom implementation of tenant identification, switching default database connection, queue job tenant context, cache prefix, Octane safety — best practices and pitfalls (Laravel 12/13)`

## Answer (verbatim)

Для Laravel 12/13 минимальная схема без пакета — центральный реестр арендаторов + middleware, которая на время одного HTTP-запроса или job переключает default DB connection и гарантированно восстанавливает состояние. Ключевые инварианты: tenant определяется только по доверенному реестру, job несёт стабильный tenant_id, а в долгоживущем worker’е нельзя оставлять tenant-настройки после завершения обработки.

## 1. Идентификация арендатора

В центральной БД храните минимум id, домен/slug, статус и параметры подключения либо ссылку на секрет с credentials. Запрос по Host/slug должен идти только в центральную БД. Не превращайте входной домен или tenant_id напрямую в имя базы или конфигурацию соединения.
```
// Ранний middleware: resolve tenant только через центральную БД. $tenant = Tenant::query() ->where('domain', $request->getHost()) ->where('active', true) ->firstOrFail(); app(TenantContext::class)->set($tenant);
```
Проверяйте Host за reverse proxy: доверяйте forwarded-заголовкам только от настроенных proxy. Для общих доменов используйте явную маршрутизацию slug’а, а не произвольный X-Tenant от клиента. Центральная и tenant-БД должны быть разделены по назначению; базовая конфигурация Laravel поддерживает несколько именованных соединений и runtime-конфигурацию соединения через config().

## 2. Переключение соединения

Задайте обычное центральное соединение, например central, и динамически переиспользуйте именованное соединение tenant. Перед сменой конфигурации вызовите purge(): иначе Laravel может продолжить использовать уже созданный объект соединения с прежними credentials/БД.
```
use Illuminate\Support\Facades\DB; final class TenantDatabase { public function activate(Tenant $tenant): void { config([ 'database.connections.tenant' => [ 'driver' => 'pgsql', 'host' => $tenant->db_host, 'port' => $tenant->db_port, 'database' => $tenant->db_name, 'username' => $tenant->db_username, 'password' => decrypt($tenant->db_password), 'charset' => 'utf8', 'prefix' => '', 'search_path' => 'public', 'sslmode' => 'prefer', ], ]); DB::purge('tenant'); DB::setDefaultConnection('tenant'); } public function deactivate(): void { DB::purge('tenant'); DB::setDefaultConnection('central'); } }
```
Если все tenant-БД используют один хост и одну схему подключения, конфигурацию можно собирать из общей базовой конфигурации, меняя только безопасно полученные из реестра параметры. Не меняйте глобально database.default без восстановления: это затрагивает код, который явно не указывает connection. Для особо чувствительных участков явный DB::connection('tenant') лучше неявного default.
Оборачивание жизненного цикла — обязательно:
```
try { $tenantDatabase->activate($tenant); return $next($request); } finally { $tenantDatabase->deactivate(); app(TenantContext::class)->clear(); }
```
В приложении с транзакциями не переключайте connection посреди открытой транзакции: транзакция привязана к конкретному соединению. Также очистка не должна проглатывать исходное исключение; если teardown сам может упасть, логируйте его отдельно, сохраняя первоначальную ошибку.

## 3. Контекст очередей

Передавайте в job скалярный идентификатор арендатора — не полагайтесь на текущий HTTP-контекст и не сериализуйте tenant-модель как источник истины. Worker должен повторно загрузить tenant из центрального реестра, проверить его статус, активировать соединение до загрузки tenant-моделей и снять контекст в finally. Laravel поддерживает job middleware, а queue worker обрабатывает множество jobs в одном процессе; для dispatch внутри транзакции предусмотрены afterCommit/after_commit.
```
final class TenantJobMiddleware { public function handle(object $job, Closure $next): void { $tenant = Tenant::on('central') ->whereKey($job->tenantId) ->where('active', true) ->firstOrFail(); try { app(TenantDatabase::class)->activate($tenant); app(TenantContext::class)->set($tenant); $next($job); } finally { app(TenantDatabase::class)->deactivate(); app(TenantContext::class)->clear(); } } }
```
Добавьте middleware к tenant-job’ам и сделайте отсутствие tenant явной ошибкой. На практике удобно использовать общий trait/base job, который хранит tenantId и всегда возвращает это middleware. Не загружайте Eloquent-модель арендатора в конструкторе job до активации tenant connection: автоматическая десериализация модели может потребовать БД раньше, чем middleware установит правильный контекст. Учтите также jobs, созданные цепочкой, события/уведомления и dispatchSync: синхронная job выполняется в текущем процессе, а значит должна корректно вложиться в уже существующий контекст либо иметь чётко определённое поведение.

## 4. Cache и Octane

Не меняйте общий cache prefix на каждом запросе простым config(['cache.prefix' => ...]). Уже разрешённые store/repository/Redis connections могут сохранить старую конфигурацию; в Octane такой изменяемый глобальный state особенно легко становится источником утечки. Для минимального решения используйте явное пространство ключей:
```
$key = "tenant:{$tenantId}:dashboard:{$userId}"; Cache::remember($key, now()->addMinutes(5), fn () => /* ... */);
```
Так же префиксуйте ключи rate limits, locks, уникальных jobs и любых пользовательских cache keys. Если используете cache tags, проверьте поддержку выбранным backend’ом и учитывайте, что теги не заменяют изоляцию во всех драйверах. Никогда не вызывайте Cache::flush() в tenant-коде: Laravel предупреждает, что flush удаляет все записи store и не соблюдает настроенный prefix.
Octane загружает приложение один раз и повторно использует его между запросами; Laravel отдельно предупреждает о сохранённом состоянии в singleton’ах, статических свойствах и объектах, захвативших request/container. Поэтому не храните текущий tenant в static или долгоживущем singleton-поле: используйте lifecycle-bound context (например, scoped) и всё равно очищайте его в finally.
Кроме того, нельзя предполагать, что Octane-контекст автоматически переносится в Octane::concurrently() или другие фоновые процессы: передавайте tenant ID явно, а там заново устанавливайте контекст. Перезапуск worker’ов и --max-requests полезны как эксплуатационная страховка от утечек памяти, но не являются механизмом tenant-изоляции.

## Минимальный план проверок

- Два последовательных HTTP-запроса к разным tenant’ам в одном Octane worker’е: каждый читает только свою БД.
- Job для tenant A, затем job для tenant B в одном queue worker’е; также проверьте exception/retry/failed job.
- Job с несуществующим, отключённым или удалённым tenant: завершение с понятной ошибкой, без fallback на предыдущую БД.
- Tenant-модель или Eloquent-модель не загружается до активации соединения.
- После завершения запроса/job default connection снова central, tenant context пуст.
- Кэш, locks, rate limits и unique-job keys не пересекаются между tenants.
- Проверка Host/прокси и tenant lookup не допускают выбора чужого tenant по пользовательскому заголовку.
Это минимальный каркас, а не готовая библиотека: он намеренно оставляет provisioning БД, миграции, ротацию credentials, биллинг и аудит за пределами tenant switching. Для Laravel 12/13 базовые механизмы здесь — конфигурация нескольких соединений, очередные middleware и lifecycle cautions Octane; детали API стоит сверить с документацией именно установленной минорной версии.

Sources:
1. [laravel](https://laravel.com/docs/12.x/database)
2. [laravel](https://laravel.com/docs/12.x/queues)
3. [laravel](https://laravel.com/docs/12.x/cache)
4. [laravel](https://laravel.com/docs/12.x/octane)
