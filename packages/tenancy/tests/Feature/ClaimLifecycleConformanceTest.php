<?php

declare(strict_types=1);

use CoreX\Context\Contracts\ImpersonationGuard;
use CoreX\Context\Contracts\ImpersonationService;
use CoreX\Context\Data\AccountRef;
use CoreX\Context\Internal\NullImpersonationService;
use CoreX\Tenancy\Account\Events\AccountPurged;
use CoreX\Tenancy\Account\Internal\Lifecycle\AccountLifecycle;
use CoreX\Tenancy\Account\Internal\Lifecycle\AccountLifecycleFsm;
use CoreX\Tenancy\Account\Internal\Models\Account;
use CoreX\Tenancy\Account\Internal\PendingPool;
use CoreX\Tenancy\Provisioning\Data\ClaimRequest;
use CoreX\Tenancy\Provisioning\Exceptions\ClaimUnavailable;
use CoreX\Tenancy\Provisioning\Exceptions\TemplateIntegrityException;
use CoreX\Tenancy\Provisioning\Internal\ClaimAccountSignup;
use CoreX\Tenancy\Provisioning\Internal\Database\TemplateIntegrityVerifier;
use CoreX\Tenancy\Provisioning\Internal\StanclTenantDatabaseLifecycle;
use CoreX\Tenancy\Tests\TestCase;
use Illuminate\Database\Connection;
use Illuminate\Database\Connectors\ConnectionFactory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

uses()->group('pg');

function p32Probe(string $database): Connection
{
    $configuration = DB::connection('pgsql')->getConfig();
    $configuration['database'] = $database;

    return (new ConnectionFactory(app()))->make($configuration, 'p32-probe');
}

/** @param list<string> $command */
function p32ArchiveCommand(array $command, string $path, bool $input = false): void
{
    $errors = tempnam(sys_get_temp_dir(), 'p32-command-');
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

function p32Lifecycle(StanclTenantDatabaseLifecycle $database): AccountLifecycle
{
    return new AccountLifecycle(
        centralConnection: 'pgsql',
        pendingPool: app(PendingPool::class),
        fsm: new AccountLifecycleFsm,
        tenantDatabaseLifecycle: $database,
        impersonationGuard: app()->makeWith(ImpersonationGuard::class, ['impersonation' => new NullImpersonationService]),
        templateIntegrityVerifier: app(TemplateIntegrityVerifier::class),
    );
}

/** A disposable consumer writer/verifier using the existing two callbacks. */
final class P32ArchiveFixture
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
        p32ArchiveCommand(['pg_dump', '-d', $database, '-Fc'], $path);
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

    public function adapter(): StanclTenantDatabaseLifecycle
    {
        return new StanclTenantDatabaseLifecycle(centralConnection: 'pgsql', writer: $this->write(...), verifier: $this->verify(...));
    }
}

beforeEach(function (): void {
    expect(app()->environment())->toBe('testing');
    expect(DB::connection()->getDatabaseName())->toEndWith('_test');
    app()->singleton(ImpersonationService::class, NullImpersonationService::class);
    DB::statement('DROP SCHEMA IF EXISTS public CASCADE');
    DB::statement('CREATE SCHEMA public');
    DB::purge('pgsql');
    $this->artisan('migrate', ['--database' => 'pgsql', '--path' => realpath(__DIR__.'/../../database/migrations/root'), '--realpath' => true, '--force' => true])->run();
    $suffix = bin2hex(random_bytes(8));
    $this->p32Databases = ['tpl_p32_'.$suffix.'_test', 'acc_p32_archive_'.$suffix.'_test', 'acc_p32_lab_'.$suffix.'_test', 'acc_p32_restore_'.$suffix.'_test'];
    foreach ($this->p32Databases as $index => $name) {
        DB::statement('CREATE DATABASE "'.$name.'"');
        $probe = p32Probe($name);

        if ($index !== 3) {
            $probe->statement('CREATE TABLE records (id integer PRIMARY KEY, content text NOT NULL)');
            $probe->table('records')->insert(['id' => 1, 'content' => ['template', 'archive-sentinel', 'lab-sentinel'][$index]]);
        }
        $probe->disconnect();
    }
    $cluster = (string) Str::uuid();
    $this->p32Cluster = $cluster;
    DB::table('root_clusters')->insert(['id' => $cluster, 'name' => $suffix, 'status' => 'active', 'pg_host_writer' => '127.0.0.1', 'pgbouncer_host' => '127.0.0.1', 'app_db_user' => 'corex', 'template_version' => 1]);
    $template = (string) Str::uuid();
    $this->p32Template = $template;
    DB::table('root_templates')->insert(['id' => $template, 'version' => 1, 'db_name' => $this->p32Databases[0], 'status' => 'ready', 'embedding_model' => 'test', 'embedding_dim' => 1, 'built_at' => now()]);
    TestCase::trustTemplate(templateId: $template, database: $this->p32Databases[0]);
    $this->p32Accounts = [];
    foreach (['archive', 'lab'] as $index => $slug) {
        $id = (string) Str::uuid();
        $owner = (string) Str::uuid();
        DB::table('root_identities')->insert(['id' => $owner, 'email' => $owner.'@example.test']);
        DB::table('root_accounts')->insert(['id' => $id, 'cluster_id' => $cluster, 'owner_identity_id' => $owner, 'slug' => $slug, 'db_name' => $this->p32Databases[$index + 1], 'status' => 'grace', 'template_version' => 1, 'embedding_model' => 'test', 'embedding_dim' => 1]);
        $this->p32Accounts[] = new AccountRef(id: $id, slug: $slug, status: 'grace', features: [], limits: []);
    }
    $this->p32Directory = sys_get_temp_dir().'/corex-p32-'.$suffix;
    mkdir($this->p32Directory, 0700);
    $this->p32Archive = new P32ArchiveFixture(root: $this->p32Directory, account: $this->p32Accounts[0]);
});

afterEach(function (): void {
    foreach ($this->p32Databases as $name) {
        expect($name)->toEndWith('_test');
        DB::statement('DROP DATABASE IF EXISTS "'.$name.'"');
    }
    foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($this->p32Directory, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST) as $file) {
        $file->isDir() ? rmdir($file->getPathname()) : unlink($file->getPathname());
    }
    rmdir($this->p32Directory);
});

it('refuses size-only archives without a qualified integrity verifier', function (): void {
    $account = $this->p32Accounts[0];
    $adapter = new StanclTenantDatabaseLifecycle(centralConnection: 'pgsql', writer: $this->p32Archive->write(...));
    expect(fn () => p32Lifecycle($adapter)->requestExport($account))->toThrow(RuntimeException::class, 'verifier');
    expect(DB::table('root_account_exports')->where('status', 'ready')->count())->toBe(0);
    expect(fn () => p32Lifecycle($adapter)->purge($account))->toThrow(ValidationException::class);
    $legacyId = (string) Str::uuid7();
    $legacy = $this->p32Archive->write($account, $legacyId);
    DB::table('root_account_exports')->insert(['id' => $legacyId, 'account_id' => $account->id, 'kind' => 'offboarding', 'status' => 'ready', 'storage_path' => $legacy['storage_path'], 'size_bytes' => $legacy['size_bytes']]);
    expect(fn () => $adapter->drop($account))->toThrow(RuntimeException::class, 'verifier');
    expect(DB::table('pg_database')->where('datname', $this->p32Databases[1])->exists())->toBeTrue();
});

it('refuses missing unreadable size-bad and hash-bad archives before ready and DROP', function (string $fault): void {
    $account = $this->p32Accounts[0];
    $this->p32Archive->fault = $fault;
    $adapter = $this->p32Archive->adapter();
    expect(fn () => p32Lifecycle($adapter)->requestExport($account))->toThrow(RuntimeException::class);
    expect(DB::table('root_account_exports')->where('status', 'ready')->count())->toBe(0);
    expect(fn () => p32Lifecycle($adapter)->purge($account))->toThrow(ValidationException::class);
    // Even a forged ready flag cannot bypass native pre-DROP verification.
    $id = DB::table('root_account_exports')->value('id');
    $path = $this->p32Directory.'/'.$account->id.'/'.$id.'.dump';
    $manifest = json_decode((string) file_get_contents($path.'.manifest'), true, flags: JSON_THROW_ON_ERROR);
    DB::table('root_account_exports')->update(['status' => 'ready', 'storage_path' => $path, 'size_bytes' => $manifest['size']]);
    expect(fn () => $adapter->drop($account))->toThrow(RuntimeException::class);
    expect(DB::table('pg_database')->where('datname', $this->p32Databases[1])->exists())->toBeTrue();
})->with(['missing', 'unreadable', 'size', 'hash']);

it('retries interrupted export then restores actual data and reconciles a crash after DROP', function (): void {
    $account = $this->p32Accounts[0];
    $fixture = $this->p32Archive;
    $fixture->crash = true;
    $lifecycle = p32Lifecycle($fixture->adapter());
    expect(fn () => $lifecycle->requestExport($account))->toThrow(RuntimeException::class, 'interrupted');
    expect(DB::table('root_accounts')->where('id', $account->id)->value('status'))->toBe('exporting');
    expect(fn () => $lifecycle->purge($account))->toThrow(ValidationException::class);
    $export = $lifecycle->requestExport($account);
    $row = DB::table('root_account_exports')->where('id', $export)->first();
    expect($row->status)->toBe('ready');
    p32ArchiveCommand(['pg_restore', '--exit-on-error', '--no-owner', '--no-privileges', '-d', $this->p32Databases[3]], $row->storage_path, input: true);
    $restored = p32Probe($this->p32Databases[3]);
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
    expect(DB::table('pg_database')->where('datname', $this->p32Databases[1])->exists())->toBeFalse();
    expect(DB::table('root_accounts')->where('id', $account->id)->value('status'))->toBe('exporting');
    $lifecycle->purge($account);
    $lifecycle->purge($account);
    expect(DB::table('root_accounts')->where('id', $account->id)->value('status'))->toBe('deleted');
    expect(DB::table('root_account_events')->where('account_id', $account->id)->where('event', 'account.purged')->count())->toBe(1);
    $lab = p32Probe($this->p32Databases[2]);
    expect($lab->table('records')->value('content'))->toBe('lab-sentinel');
    $lab->disconnect();
});

it('rechecks integrity and ownership after ready without dropping another tenant', function (): void {
    $adapter = $this->p32Archive->adapter();
    $account = $this->p32Accounts[0];
    $id = p32Lifecycle($adapter)->requestExport($account);
    $path = DB::table('root_account_exports')->where('id', $id)->value('storage_path');
    $size = filesize($path);
    file_put_contents($path, str_repeat('z', $size));
    expect(fn () => p32Lifecycle($adapter)->purge($account))->toThrow(RuntimeException::class);
    $lab = $this->p32Accounts[1];
    expect(fn () => $adapter->export($lab, 'manual'))->toThrow(RuntimeException::class, 'Wrong tenant');
    DB::table('root_account_exports')->where('account_id', $lab->id)->update(['status' => 'ready', 'storage_path' => $path, 'size_bytes' => $size]);
    expect(fn () => $adapter->drop($lab))->toThrow(RuntimeException::class);
    foreach ([1, 2] as $index) {
        expect(DB::table('pg_database')->where('datname', $this->p32Databases[$index])->exists())->toBeTrue();
    }
    $probe = p32Probe($this->p32Databases[2]);
    $probe->statement('CREATE TABLE tampered (id integer)');
    $probe->disconnect();
    $model = Account::query()->findOrFail($lab->id);
    expect(fn () => app(TemplateIntegrityVerifier::class)->assertAccount($model))->toThrow(TemplateIntegrityException::class);
});

it('a newer interrupted export wins even when export timestamps are equal', function (): void {
    $account = $this->p32Accounts[0];
    $lifecycle = p32Lifecycle($this->p32Archive->adapter());
    $first = $lifecycle->requestExport($account);
    $this->p32Archive->crash = true;
    expect(fn () => $lifecycle->requestExport($account))->toThrow(RuntimeException::class);
    $second = DB::table('root_account_exports')->where('id', '!=', $first)->value('id');
    expect(strcmp($second, $first))->toBeGreaterThan(0);
    DB::table('root_account_exports')->update(['created_at' => '2026-09-15 00:00:00+00']);
    expect(fn () => $lifecycle->purge($account))->toThrow(ValidationException::class);
    expect(fn () => $this->p32Archive->adapter()->drop($account))->toThrow(RuntimeException::class);
    expect(DB::table('pg_database')->where('datname', $this->p32Databases[1])->exists())->toBeTrue();
});

it('refuses exhausted soft-deleted pools and a physically tampered signed template before reserving', function (): void {
    $request = new ClaimRequest(requestKey: 'pool-denial', clusterId: $this->p32Cluster, templateId: $this->p32Template, requestDigest: hash('sha256', 'pool'));
    $claim = new ClaimAccountSignup(centralConnection: 'pgsql', templateIntegrity: app(TemplateIntegrityVerifier::class));
    expect(fn () => $claim->claim($request))->toThrow(ClaimUnavailable::class);
    DB::table('root_accounts')->where('id', $this->p32Accounts[1]->id)->update(['status' => 'pending', 'deleted_at' => now()]);
    expect(fn () => $claim->claim($request))->toThrow(ClaimUnavailable::class);
    DB::table('root_accounts')->where('id', $this->p32Accounts[1]->id)->update(['deleted_at' => null]);
    $probe = p32Probe($this->p32Databases[0]);
    $probe->statement('ALTER TABLE records ADD COLUMN unsigned_change integer');
    $probe->disconnect();
    expect(fn () => $claim->claim($request))->toThrow(TemplateIntegrityException::class);
    expect(DB::table('root_accounts')->where('status', 'provisioning')->count())->toBe(0);
});
