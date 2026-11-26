<?php

declare(strict_types=1);

use CoreX\Context\Contracts\ImpersonationGuard;
use CoreX\Context\Contracts\ImpersonationService;
use CoreX\Context\Data\AccountRef;
use CoreX\Context\Internal\NullImpersonationService;
use CoreX\Tenancy\Account\Events\AccountPurged;
use CoreX\Tenancy\Account\Internal\Lifecycle\AccountLifecycle;
use CoreX\Tenancy\Account\Internal\Lifecycle\AccountLifecycleFsm;
use CoreX\Tenancy\Account\Internal\PendingPool;
use CoreX\Tenancy\Laravel\Support\AccountLifecyclePolicy;
use CoreX\Tenancy\Laravel\Support\HostResolverCacheInvalidator;
use CoreX\Tenancy\Provisioning\Data\ClaimRequest;
use CoreX\Tenancy\Provisioning\Data\TemplateAccountView;
use CoreX\Tenancy\Provisioning\Exceptions\ClaimUnavailable;
use CoreX\Tenancy\Provisioning\Exceptions\TemplateIntegrityException;
use CoreX\Tenancy\Provisioning\Internal\ClaimAccountSignup;
use CoreX\Tenancy\Provisioning\Internal\DatabaseTenantLifecycle;
use CoreX\Tenancy\Tests\Support\TestAccountResources;
use CoreX\Tenancy\Tests\Support\TestTemplateIntegrity as TemplateIntegrityVerifier;
use CoreX\Tenancy\Tests\TestCase;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Database\Connection;
use Illuminate\Database\ConnectionResolverInterface;
use Illuminate\Database\Connectors\ConnectionFactory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Psr\Clock\ClockInterface;

uses()->group('pg');

function claimDatabaseProbe(string $database): Connection
{
    $configuration = DB::connection('pgsql')->getConfig();
    $configuration['database'] = $database;

    return new ConnectionFactory(app())->make($configuration, 'claim-probe');
}

/** @param list<string> $command */
function runClaimArchiveCommand(array $command, string $path, bool $input = false): void
{
    $errors = tempnam(sys_get_temp_dir(), 'claim-command-');
    $descriptors = [0 => $input ? ['file', $path, 'r'] : ['file', '/dev/null', 'r'], 1 => $input ? ['file', '/dev/null', 'w'] : ['file', $path, 'w'], 2 => ['file', $errors, 'w']];
    $configuration = DB::connection('pgsql')->getConfig();
    $databaseArgument = array_search('-d', $command, true);
    expect($databaseArgument)->not->toBeFalse();
    expect(app()->environment())->toBe('testing')
        ->and($command[$databaseArgument + 1])->toEndWith('_test');
    // Debian's pg_wrapper chooses its newest client for remote hosts, whose
    // archive setup statements can be unsupported by an older test server.
    $major = explode('.', (string) DB::connection('pgsql')->getPdo()->getAttribute(PDO::ATTR_SERVER_VERSION))[0];
    $matchingClient = '/usr/lib/postgresql/'.$major.'/bin/'.$command[0];

    if (is_executable($matchingClient)) {
        $command[0] = $matchingClient;
    }

    // Native archive tools must reach the same server as the PDO test connection.
    $environment = array_replace(getenv(), [
        'PGHOST' => $configuration['host'],
        'PGPORT' => (string) $configuration['port'],
        'PGUSER' => $configuration['username'],
        'PGPASSWORD' => $configuration['password'],
    ]);
    $process = proc_open($command, $descriptors, $pipes, env_vars: $environment);
    $status = proc_close($process);
    $message = file_get_contents($errors);
    unlink($errors);
    expect($status)->toBe(0, (string) $message);
}

function createClaimLifecycle(DatabaseTenantLifecycle $database): AccountLifecycle
{
    return new AccountLifecycle(
        lifecycle: resolve(AccountLifecyclePolicy::class), connections: resolve(ConnectionResolverInterface::class), clock: resolve(ClockInterface::class), events: resolve(Dispatcher::class), connectionFactory: resolve(ConnectionFactory::class), hostCache: resolve(HostResolverCacheInvalidator::class),
        centralConnection: 'pgsql',
        pendingPool: resolve(PendingPool::class),
        fsm: new AccountLifecycleFsm,
        tenantDatabaseLifecycle: $database,
        impersonationGuard: app()->makeWith(ImpersonationGuard::class, ['impersonation' => new NullImpersonationService]),
        templateIntegrityVerifier: resolve(TemplateIntegrityVerifier::class),
    );
}

/** A disposable consumer writer/verifier using the existing two callbacks. */
final class ClaimArchiveFixture
{
    public bool $crash = false;

    public string $fault = '';

    public function __construct(public readonly string $root, public readonly AccountRef $account) {}

    /** @return array{storage_path: string, size_bytes: int} */
    public function write(AccountRef $account, string $exportId): array
    {
        if ($account->id !== $this->account->id) {
            throw new RuntimeException('Wrong tenant export writer.');
        }
        $directory = $this->root.'/'.$account->id;

        if (! is_dir($directory)) {
            mkdir($directory, 0700, true);
        }
        $path = $directory.'/'.$exportId.'.dump';
        $database = DB::table('root_accounts')->where('id', $account->id)->value('db_name');
        runClaimArchiveCommand(['pg_dump', '-d', $database, '-Fc'], $path);
        $size = filesize($path);
        $manifest = ['account_id' => $account->id, 'size' => $size, 'sha256' => hash_file('sha256', $path)];
        file_put_contents($path.'.manifest', json_encode($manifest, JSON_THROW_ON_ERROR));

        if ($this->crash) {
            $this->crash = false;

            throw new RuntimeException('Writer interrupted before ready.');
        }
        match ($this->fault) {
            'hash' => file_put_contents($path, str_repeat('x', $size)),
            'size' => file_put_contents($path, 'short'),
            'missing' => unlink($path),
            'unreadable' => chmod($path, 0000),
            default => null,
        };

        return ['storage_path' => $path, 'size_bytes' => $size];
    }

    public function verify(string $path, int $size): bool
    {
        clearstatcache(true, $path);

        if (! str_starts_with($path, $this->root.'/'.$this->account->id.'/')
            || ! is_file($path) || ! is_readable($path) || ! is_file($path.'.manifest')) {
            return false;
        }
        $manifest = json_decode((string) file_get_contents($path.'.manifest'), true, flags: JSON_THROW_ON_ERROR);

        return $manifest['account_id'] === $this->account->id
            && $manifest['size'] === $size && filesize($path) === $size
            && hash_equals($manifest['sha256'], (string) hash_file('sha256', $path));
    }

    public function adapter(): DatabaseTenantLifecycle
    {
        return new DatabaseTenantLifecycle(connections: resolve(ConnectionResolverInterface::class), clock: resolve(ClockInterface::class), events: resolve(Dispatcher::class), centralConnection: 'pgsql', resources: new TestAccountResources(writer: $this->write(...), verifier: $this->verify(...)));
    }
}

beforeEach(function (): void {
    expect(app()->environment())->toBe('testing');
    expect(DB::connection()->getDatabaseName())->toEndWith('_test');
    app()->singleton(ImpersonationService::class, NullImpersonationService::class);
    DB::statement('DROP SCHEMA IF EXISTS public CASCADE');
    DB::statement('CREATE SCHEMA public');
    DB::purge('pgsql');
    $this->migrateSchemaSets(['axiomasoft/corex-tenancy:root'], 'pgsql');
    $suffix = bin2hex(random_bytes(8));
    $this->claimDatabases = ['tpl_claim_'.$suffix.'_test', 'acc_claim_archive_'.$suffix.'_test', 'acc_claim_lab_'.$suffix.'_test', 'acc_claim_restore_'.$suffix.'_test'];
    foreach ($this->claimDatabases as $index => $name) {
        DB::statement('CREATE DATABASE "'.$name.'"');
        $probe = claimDatabaseProbe($name);

        if ($index !== 3) {
            $probe->statement('CREATE TABLE records (id integer PRIMARY KEY, content text NOT NULL)');
            $probe->table('records')->insert(['id' => 1, 'content' => ['template', 'archive-sentinel', 'lab-sentinel'][$index]]);
        }
        $probe->disconnect();
    }
    $cluster = (string) Str::uuid();
    $this->claimCluster = $cluster;
    DB::table('root_clusters')->insert(['id' => $cluster, 'name' => $suffix, 'status' => 'active', 'pg_host_writer' => '127.0.0.1', 'pgbouncer_host' => '127.0.0.1', 'app_db_user' => 'corex', 'template_version' => 1]);
    $template = (string) Str::uuid();
    $this->claimTemplate = $template;
    DB::table('root_templates')->insert(['id' => $template, 'version' => 1, 'db_name' => $this->claimDatabases[0], 'status' => 'ready', 'embedding_model' => 'test', 'embedding_dim' => 1, 'built_at' => now()]);
    TestCase::trustTemplate(templateId: $template, database: $this->claimDatabases[0]);
    $this->claimAccounts = [];
    foreach (['archive', 'lab'] as $index => $slug) {
        $id = (string) Str::uuid();
        $owner = (string) Str::uuid();
        DB::table('root_identities')->insert(['id' => $owner, 'email' => $owner.'@example.test']);
        DB::table('root_accounts')->insert(['id' => $id, 'cluster_id' => $cluster, 'owner_identity_id' => $owner, 'slug' => $slug, 'db_name' => $this->claimDatabases[$index + 1], 'status' => 'grace', 'template_version' => 1, 'embedding_model' => 'test', 'embedding_dim' => 1]);
        $this->claimAccounts[] = new AccountRef(id: $id, slug: $slug, status: 'grace', features: [], limits: []);
    }
    $this->claimDirectory = sys_get_temp_dir().'/corex-claim-'.$suffix;
    mkdir($this->claimDirectory, 0700);
    $this->claimArchive = new ClaimArchiveFixture(root: $this->claimDirectory, account: $this->claimAccounts[0]);
});

afterEach(function (): void {
    foreach ($this->claimDatabases as $name) {
        expect($name)->toEndWith('_test');
        DB::statement('DROP DATABASE IF EXISTS "'.$name.'"');
    }
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($this->claimDirectory, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST) as $file) {
        $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
    }
    rmdir($this->claimDirectory);
});

it('refuses size-only archives without a qualified integrity verifier', function (): void {
    $account = $this->claimAccounts[0];
    $adapter = new DatabaseTenantLifecycle(connections: resolve(ConnectionResolverInterface::class), clock: resolve(ClockInterface::class), events: resolve(Dispatcher::class), centralConnection: 'pgsql', resources: new TestAccountResources(writer: $this->claimArchive->write(...)));
    expect(fn () => createClaimLifecycle($adapter)->requestExport($account))->toThrow(RuntimeException::class, 'verifier');
    expect(DB::table('root_account_exports')->where('status', 'ready')->count())->toBe(0);
    expect(fn () => createClaimLifecycle($adapter)->purge($account))->toThrow(ValidationException::class);
    $legacyId = (string) Str::uuid7();
    $legacy = $this->claimArchive->write($account, $legacyId);
    DB::table('root_account_exports')->insert(['id' => $legacyId, 'account_id' => $account->id, 'kind' => 'offboarding', 'status' => 'ready', 'storage_path' => $legacy['storage_path'], 'size_bytes' => $legacy['size_bytes']]);
    expect(fn () => $adapter->drop($account))->toThrow(RuntimeException::class, 'verifier');
    expect(DB::table('pg_database')->where('datname', $this->claimDatabases[1])->exists())->toBeTrue();
});

it('refuses missing unreadable size-bad and hash-bad archives before ready and DROP', function (string $fault): void {
    $account = $this->claimAccounts[0];
    $this->claimArchive->fault = $fault;
    $adapter = $this->claimArchive->adapter();
    expect(fn () => createClaimLifecycle($adapter)->requestExport($account))->toThrow(RuntimeException::class);
    expect(DB::table('root_account_exports')->where('status', 'ready')->count())->toBe(0);
    expect(fn () => createClaimLifecycle($adapter)->purge($account))->toThrow(ValidationException::class);
    // Even a forged ready flag cannot bypass physical-owner verification.
    $id = DB::table('root_account_exports')->value('id');
    $path = $this->claimDirectory.'/'.$account->id.'/'.$id.'.dump';
    $manifest = json_decode((string) file_get_contents($path.'.manifest'), true, flags: JSON_THROW_ON_ERROR);
    DB::table('root_account_exports')->update(['status' => 'ready', 'storage_path' => $path, 'size_bytes' => $manifest['size']]);
    expect(fn () => $adapter->drop($account))->toThrow(RuntimeException::class);
    expect(DB::table('pg_database')->where('datname', $this->claimDatabases[1])->exists())->toBeTrue();
})->with(['missing', 'unreadable', 'size', 'hash']);

it('retries interrupted export then restores actual data and reconciles a crash after DROP', function (): void {
    $account = $this->claimAccounts[0];
    $fixture = $this->claimArchive;
    $fixture->crash = true;
    $lifecycle = createClaimLifecycle($fixture->adapter());
    expect(fn () => $lifecycle->requestExport($account))->toThrow(RuntimeException::class, 'interrupted');
    expect(DB::table('root_accounts')->where('id', $account->id)->value('status'))->toBe('exporting');
    expect(fn () => $lifecycle->purge($account))->toThrow(ValidationException::class);
    $export = $lifecycle->requestExport($account);
    $row = DB::table('root_account_exports')->where('id', $export)->first();
    expect($row->status)->toBe('ready');
    runClaimArchiveCommand(['pg_restore', '--exit-on-error', '--no-owner', '--no-privileges', '-d', $this->claimDatabases[3]], $row->storage_path, input: true);
    $restored = claimDatabaseProbe($this->claimDatabases[3]);
    expect($restored->table('records')->get()->map(fn ($r): array => (array) $r)->all())->toBe([['id' => 1, 'content' => 'archive-sentinel']]);
    $restored->disconnect();
    $interrupted = false;
    Event::listen(AccountPurged::class, static function () use (&$interrupted): void {
        if (! $interrupted) {
            $interrupted = true;

            throw new RuntimeException('Crash after physical DROP.');
        }
    });
    expect(fn () => $lifecycle->purge($account))->toThrow(RuntimeException::class, 'Crash after physical DROP');
    expect(DB::table('pg_database')->where('datname', $this->claimDatabases[1])->exists())->toBeFalse();
    expect(DB::table('root_accounts')->where('id', $account->id)->value('status'))->toBe('exporting');
    $lifecycle->purge($account);
    $lifecycle->purge($account);
    expect(DB::table('root_accounts')->where('id', $account->id)->value('status'))->toBe('deleted');
    expect(DB::table('root_account_events')->where('account_id', $account->id)->where('event', 'account.purged')->count())->toBe(1);
    $lab = claimDatabaseProbe($this->claimDatabases[2]);
    expect($lab->table('records')->value('content'))->toBe('lab-sentinel');
    $lab->disconnect();
});

it('rechecks integrity and ownership after ready without dropping another tenant', function (): void {
    $adapter = $this->claimArchive->adapter();
    $account = $this->claimAccounts[0];
    $id = createClaimLifecycle($adapter)->requestExport($account);
    $path = DB::table('root_account_exports')->where('id', $id)->value('storage_path');
    $size = filesize($path);
    file_put_contents($path, str_repeat('z', $size));
    expect(fn () => createClaimLifecycle($adapter)->purge($account))->toThrow(RuntimeException::class);
    $lab = $this->claimAccounts[1];
    expect(fn () => $adapter->export($lab, 'manual'))->toThrow(RuntimeException::class, 'Wrong tenant');
    DB::table('root_account_exports')->where('account_id', $lab->id)->update(['status' => 'ready', 'storage_path' => $path, 'size_bytes' => $size]);
    expect(fn () => $adapter->drop($lab))->toThrow(RuntimeException::class);
    foreach ([1, 2] as $index) {
        expect(DB::table('pg_database')->where('datname', $this->claimDatabases[$index])->exists())->toBeTrue();
    }
    $probe = claimDatabaseProbe($this->claimDatabases[2]);
    $probe->statement('CREATE TABLE tampered (id integer)');
    $probe->disconnect();
    resolve(TemplateIntegrityVerifier::class)->deniedDatabases[] = $this->claimDatabases[2];
    expect(fn () => resolve(TemplateIntegrityVerifier::class)->verifyAccount(new TemplateAccountView(1, $this->claimCluster, $this->claimDatabases[2])))->toThrow(TemplateIntegrityException::class);
});

it('a newer interrupted export wins even when export timestamps are equal', function (): void {
    $account = $this->claimAccounts[0];
    $lifecycle = createClaimLifecycle($this->claimArchive->adapter());
    $first = $lifecycle->requestExport($account);
    $this->claimArchive->crash = true;
    expect(fn () => $lifecycle->requestExport($account))->toThrow(RuntimeException::class);
    $second = DB::table('root_account_exports')->where('id', '!=', $first)->value('id');
    expect(strcmp($second, $first))->toBeGreaterThan(0);
    DB::table('root_account_exports')->update(['created_at' => '2026-09-15 00:00:00+00']);
    expect(fn () => $lifecycle->purge($account))->toThrow(ValidationException::class);
    expect(fn () => $this->claimArchive->adapter()->drop($account))->toThrow(RuntimeException::class);
    expect(DB::table('pg_database')->where('datname', $this->claimDatabases[1])->exists())->toBeTrue();
});

it('refuses exhausted soft-deleted pools and a template rejected by its physical owner before reserving', function (): void {
    $request = new ClaimRequest(requestKey: 'pool-denial', clusterId: $this->claimCluster, templateId: $this->claimTemplate, requestDigest: hash('sha256', 'pool'));
    $claim = new ClaimAccountSignup(connections: resolve(ConnectionResolverInterface::class), centralConnection: 'pgsql', templateIntegrity: resolve(TemplateIntegrityVerifier::class));
    expect(fn () => $claim->claim($request))->toThrow(ClaimUnavailable::class);
    DB::table('root_accounts')->where('id', $this->claimAccounts[1]->id)->update(['status' => 'pending', 'deleted_at' => now()]);
    expect(fn () => $claim->claim($request))->toThrow(ClaimUnavailable::class);
    DB::table('root_accounts')->where('id', $this->claimAccounts[1]->id)->update(['deleted_at' => null]);
    resolve(TemplateIntegrityVerifier::class)->revoke($this->claimTemplate);
    expect(fn () => $claim->claim($request))->toThrow(TemplateIntegrityException::class);
    expect(DB::table('root_accounts')->where('status', 'provisioning')->count())->toBe(0);
});
