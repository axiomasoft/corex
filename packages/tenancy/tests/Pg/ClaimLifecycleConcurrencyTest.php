<?php

declare(strict_types=1);

use Composer\Autoload\ClassLoader;
use CoreX\Tenancy\Contracts\TemplateIntegrityVerifier;
use CoreX\Tenancy\Provisioning\Data\ClaimRequest;
use CoreX\Tenancy\Provisioning\Data\QualifiedTemplate;
use CoreX\Tenancy\Provisioning\Exceptions\ClaimConflict;
use CoreX\Tenancy\Provisioning\Internal\ClaimAccountSignup;
use CoreX\Tenancy\Tests\TestCase;
use Illuminate\Database\ConnectionResolverInterface;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Sleep;
use Illuminate\Support\Str;

uses(TestCase::class)->group('pg');

final class P32ConcurrentTemplate implements TemplateIntegrityVerifier
{
    public function qualify(ClaimRequest $request): QualifiedTemplate
    {
        return new QualifiedTemplate(clusterId: $request->clusterId, templateId: $request->templateId, templateVersion: 1, artifactDigest: str_repeat('a', 64));
    }
}

final class P32ClaimWorker
{
    /** @var array<int, resource> */
    private array $pipes = [];

    /** @var resource */
    private readonly mixed $process;

    private readonly string $errors;

    /** @param array<string, mixed> $payload */
    public function __construct(string $script, array $payload)
    {
        $this->errors = tempnam(sys_get_temp_dir(), 'corex-p32-worker-');
        $this->process = proc_open([PHP_BINARY, $script], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['file', $this->errors, 'w']], $this->pipes);
        fwrite($this->pipes[0], json_encode($payload, JSON_THROW_ON_ERROR)."\n");
        stream_set_timeout($this->pipes[1], 5);

        try {
            expect($this->line())->toStartWith('started:');
        } catch (Throwable $exception) {
            $this->stop();

            throw $exception;
        }
    }

    public function line(): string
    {
        $line = fgets($this->pipes[1]);

        if ($line === false) {
            throw new RuntimeException('Worker barrier/result unavailable: '.file_get_contents($this->errors));
        }

        return trim($line);
    }

    public function release(): void
    {
        fwrite($this->pipes[0], "release\n");
    }

    /** @return array<string, mixed> */
    public function result(): array
    {
        return json_decode($this->line(), true, flags: JSON_THROW_ON_ERROR);
    }

    public function stop(): void
    {
        if (! is_resource($this->process)) {
            return;
        }

        if (proc_get_status($this->process)['running']) {
            proc_terminate($this->process, 9);
        }
        foreach ($this->pipes as $pipe) {
            fclose($pipe);
        }
        proc_close($this->process);
        unlink($this->errors);
    }
}

beforeEach(function (): void {
    expect(app()->environment())->toBe('testing');
    expect(DB::connection()->getDatabaseName())->toEndWith('_test');
    DB::statement('DROP SCHEMA IF EXISTS public CASCADE');
    DB::statement('CREATE SCHEMA public');
    DB::purge('pgsql');
    $this->migrateSchemaSets(['axiomasoft/corex-tenancy:root'], 'pgsql');
    $this->p32Cluster = (string) Str::uuid();
    $this->p32Template = (string) Str::uuid();
    DB::table('root_clusters')->insert(['id' => $this->p32Cluster, 'name' => $this->p32Cluster, 'status' => 'active', 'pg_host_writer' => 'test', 'pgbouncer_host' => 'test', 'app_db_user' => 'corex', 'template_version' => 1]);
    DB::table('root_templates')->insert(['id' => $this->p32Template, 'version' => 1, 'db_name' => 'tpl_p32_logical_test', 'status' => 'ready', 'embedding_model' => 'test', 'embedding_dim' => 1]);
    $this->p32Candidates = [];
    for ($index = 0; $index < 3; $index++) {
        $id = (string) Str::uuid();
        DB::table('root_accounts')->insert(['id' => $id, 'cluster_id' => $this->p32Cluster, 'db_name' => 'acc_p32_'.str_replace('-', '', $id).'_test', 'status' => 'pending', 'template_version' => 1, 'embedding_model' => 'test', 'embedding_dim' => 1]);
        $this->p32Candidates[] = $id;
    }
    $this->p32Workers = [];
    $this->p32WorkerScript = tempnam(sys_get_temp_dir(), 'corex-p32-claim-');
    $loader = new ReflectionClass(ClassLoader::class)->getFileName();
    assert(is_string($loader));
    $autoload = var_export(dirname($loader, 2).'/autoload.php', true);
    $source = <<<'PHP'
<?php
declare(strict_types=1);
use Illuminate\Support\Facades\DB;
use CoreX\Tenancy\Provisioning\Internal\ClaimAccountSignup;
use CoreX\Tenancy\Contracts\TemplateIntegrityVerifier;
use CoreX\Tenancy\Provisioning\Data\ClaimRequest;
use CoreX\Tenancy\Provisioning\Data\QualifiedTemplate;
require AUTOLOAD;
use Illuminate\Database\Capsule\Manager;
use Illuminate\Events\Dispatcher;
use Illuminate\Support\Facades\Facade;
$p = json_decode(fgets(STDIN), true, flags: JSON_THROW_ON_ERROR);
if (! str_ends_with($p['config']['database'], '_test')) { throw new RuntimeException('Unsafe worker database.'); }
$capsule = new Manager;
$capsule->addConnection($p['config'], 'worker');
$capsule->setEventDispatcher(new Dispatcher($capsule->getContainer()));
$capsule->getContainer()->instance('db', $capsule->getDatabaseManager());
Facade::setFacadeApplication($capsule->getContainer());
$c = DB::connection('worker');
$c->select('SELECT set_config(?, ?, false)', ['application_name', $p['name']]);
echo 'started:'.$c->selectOne('SELECT pg_backend_pid() AS id')->id."\n"; fflush(STDOUT);
$r = new ClaimRequest(requestKey: $p['key'], clusterId: $p['cluster'], templateId: $p['template'], requestDigest: $p['digest']);
$v = new class($p['op']) implements TemplateIntegrityVerifier {
    public function __construct(private readonly string $operation) {}
    public function qualify(ClaimRequest $r): QualifiedTemplate {
        if ($this->operation === 'pause') { echo "ready\n"; fflush(STDOUT); fgets(STDIN); }
        return new QualifiedTemplate(clusterId: $r->clusterId, templateId: $r->templateId, templateVersion: 1, artifactDigest: str_repeat('a', 64));
    }
};
if ($p['op'] === 'raw') {
    $c->beginTransaction();
    $c->table('root_accounts')->where('id', $p['account'])->update(['status' => 'provisioning', 'signup_key' => $p['key'], 'signup_request_digest' => $p['digest'], 'signup_cluster_id' => $p['cluster'], 'signup_template_id' => $p['template']]);
    echo "ready\n"; fflush(STDOUT); fgets(STDIN); $c->commit();
    echo json_encode(['account' => $p['account'], 'raw' => true], JSON_THROW_ON_ERROR)."\n";
} else {
    if ($p['op'] === 'precommit') {
        $c->listen(static function ($query): void {
            if (str_contains(strtolower($query->sql), 'update root_accounts')) { echo "ready\n"; fflush(STDOUT); fgets(STDIN); }
        });
    }
    $result = (new ClaimAccountSignup(connections: $capsule->getDatabaseManager(), centralConnection: 'worker', templateIntegrity: $v))->claim($r);
    if ($p['op'] === 'postcommit') { echo "committed\n"; fflush(STDOUT); fgets(STDIN); }
    echo json_encode(['account' => $result->accountId, 'replayed' => $result->replayed], JSON_THROW_ON_ERROR)."\n";
}
PHP;
    file_put_contents($this->p32WorkerScript, str_replace('AUTOLOAD', $autoload, $source));
});

afterEach(function (): void {
    foreach ($this->p32Workers as $worker) {
        $worker->stop();
    }
    unlink($this->p32WorkerScript);
});

function p32SpawnClaim(string $key, string $operation = 'normal', ?string $account = null): P32ClaimWorker
{
    $test = test();
    $payload = ['config' => DB::connection('pgsql')->getConfig(), 'key' => $key, 'cluster' => $test->p32Cluster, 'template' => $test->p32Template, 'digest' => hash('sha256', $key), 'name' => 'p32-'.$key.'-'.$operation, 'op' => $operation, 'account' => $account];
    $worker = new P32ClaimWorker(script: $test->p32WorkerScript, payload: $payload);
    $test->p32Workers = [...$test->p32Workers, $worker];

    return $worker;
}

function p32AssertBlocked(string $name): void
{
    $deadline = microtime(true) + 5;
    do {
        $row = DB::selectOne('SELECT wait_event_type FROM pg_stat_activity WHERE application_name = ?', [$name]);

        if ($row?->wait_event_type === 'Lock') {
            expect($row->wait_event_type)->toBe('Lock');

            return;
        }
        Sleep::usleep(10000);
    } while (microtime(true) < $deadline);

    throw new RuntimeException('Independent claim did not reach the PostgreSQL lock barrier.');
}

it('overlaps independent claims while an unrelated key progresses before release', function (): void {
    $first = p32SpawnClaim(key: 'shared', operation: 'pause');
    expect($first->line())->toBe('ready');
    $second = p32SpawnClaim('shared');
    p32AssertBlocked('p32-shared-normal');
    $unrelated = p32SpawnClaim('unrelated');
    $other = $unrelated->result();
    expect($other['replayed'])->toBeFalse();
    $first->release();
    $a = $first->result();
    $b = $second->result();
    expect($a['account'])->toBe($b['account'])->not->toBe($other['account']);
    expect($a['replayed'])->toBeFalse()->and($b['replayed'])->toBeTrue();
    expect(DB::table('root_accounts')->where('signup_key', 'shared')->count())->toBe(1);
});

it('reconciles a named unique-index race against a writer with no advisory cooperation', function (): void {
    $id = $this->p32Candidates[0];
    $raw = p32SpawnClaim(key: 'uncooperative', operation: 'raw', account: $id);
    expect($raw->line())->toBe('ready');
    $claim = p32SpawnClaim('uncooperative');
    p32AssertBlocked('p32-uncooperative-normal');
    $raw->release();
    $raw->result();
    $result = $claim->result();
    expect($result)->toBe(['account' => $id, 'replayed' => true]);
    expect(DB::table('root_accounts')->where('status', 'pending')->count())->toBe(2);
    $duplicate = (array) DB::table('root_accounts')->where('id', $id)->first(['signup_key', 'signup_request_digest', 'signup_cluster_id', 'signup_template_id']);

    try {
        DB::table('root_accounts')->where('id', $this->p32Candidates[1])->update($duplicate);

        throw new RuntimeException('Named unique constraint did not reject a duplicate.');
    } catch (QueryException $exception) {
        expect($exception->getPrevious()->getCode())->toBe('23505');
        expect($exception->getMessage())->toContain('root_accounts_signup_key_uq');
    }
});

it('rolls back a killed precommit worker and replays a committed response lost before delivery', function (): void {
    $before = p32SpawnClaim(key: 'crash-before', operation: 'precommit');
    expect($before->line())->toBe('ready');
    $before->stop();
    expect(DB::table('root_accounts')->where('status', 'pending')->count())->toBe(3);
    $after = p32SpawnClaim(key: 'crash-after', operation: 'postcommit');
    expect($after->line())->toBe('committed');
    $id = DB::table('root_accounts')->where('signup_key', 'crash-after')->value('id');
    $after->stop();
    $replay = p32SpawnClaim('crash-after')->result();
    expect($replay)->toBe(['account' => $id, 'replayed' => true]);
    expect(DB::table('root_accounts')->where('status', 'provisioning')->count())->toBe(1);
});

it('denies every changed immutable fingerprint and soft-deleted replay without a second reservation', function (): void {
    $request = new ClaimRequest(requestKey: 'fingerprint', clusterId: $this->p32Cluster, templateId: $this->p32Template, requestDigest: hash('sha256', 'original'));
    $claim = new ClaimAccountSignup(connections: resolve(ConnectionResolverInterface::class), centralConnection: 'pgsql', templateIntegrity: new P32ConcurrentTemplate);
    $first = $claim->claim($request);
    foreach ([['cluster' => (string) Str::uuid()], ['template' => (string) Str::uuid()], ['digest' => hash('sha256', 'changed')]] as $change) {
        $changed = new ClaimRequest(requestKey: $request->requestKey, clusterId: $change['cluster'] ?? $request->clusterId, templateId: $change['template'] ?? $request->templateId, requestDigest: $change['digest'] ?? $request->requestDigest);
        expect(fn () => $claim->claim($changed))->toThrow(ClaimConflict::class);
    }
    DB::table('root_accounts')->where('id', $first->accountId)->update(['deleted_at' => now()]);
    expect(fn () => $claim->claim($request))->toThrow(ClaimConflict::class);
    expect(DB::table('root_accounts')->where('status', 'pending')->count())->toBe(2);
});
