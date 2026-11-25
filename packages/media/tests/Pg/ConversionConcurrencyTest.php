<?php

declare(strict_types=1);

use CoreX\Context\Data\AccountRef;
use CoreX\Context\Data\TenantContext;
use CoreX\Media\Contracts\MediaPolicy;
use CoreX\Media\Data\MediaAction;
use CoreX\Media\Data\MediaContext;
use CoreX\Media\Data\MediaSubject;
use CoreX\Media\Data\UploadInput;
use CoreX\Media\Files\Contracts\MediaLibrary;
use CoreX\Media\Internal\Jobs\PurgeMedia;
use CoreX\Media\Internal\MediaOperationStore;
use CoreX\Media\Laravel\UploadedFileSource;
use CoreX\Media\Tests\PgTestCase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

uses(PgTestCase::class)->group('pg');

function pgConversionContext(): MediaContext
{
    return new MediaContext(
        tenant: new TenantContext(new AccountRef('11111111-1111-7111-8111-111111111111', 'media-pg-test', 'active', [], [])),
        actorKind: 'user',
        actorId: 'actor-1',
    );
}

it('uses a PostgreSQL file-row lock across independent connections', function (): void {
    app()->instance(MediaPolicy::class, new class implements MediaPolicy
    {
        public function allows(MediaContext $context, MediaAction $action, MediaSubject $subject): bool
        {
            return true;
        }
    });
    app()->forgetScopedInstances();

    $file = resolve(MediaLibrary::class)->store(
        pgConversionContext(),
        new UploadInput('pg-lock-request', UploadedFileSource::fromFile(UploadedFile::fake()->createWithContent('source.txt', 'postgres lock')), 'attachment'),
    );
    $connection = config('database.connections.pgsql');
    $dsn = "pgsql:host={$connection['host']};port={$connection['port']};dbname={$connection['database']}";
    $second = new PDO($dsn, $connection['username'], $connection['password'], [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

    DB::beginTransaction();
    DB::table('med_files')->where('id', $file->id)->lockForUpdate()->first();
    $second->exec("SET lock_timeout = '100ms'");

    try {
        expect(fn (): bool => $second->query("SELECT id FROM med_files WHERE id = '{$file->id}' FOR UPDATE") !== false)->toThrow(PDOException::class);
    } finally {
        DB::rollBack();
    }
});

it('finds a file purge and its conversions through file indexes instead of scanning every operation', function (): void {
    app()->instance(MediaPolicy::class, new class implements MediaPolicy
    {
        public function allows(MediaContext $context, MediaAction $action, MediaSubject $subject): bool
        {
            return true;
        }
    });
    app()->forgetScopedInstances();
    $library = resolve(MediaLibrary::class);
    $file = $library->store(
        pgConversionContext(),
        new UploadInput('pg-purge-index', UploadedFileSource::fromFile(UploadedFile::fake()->createWithContent('source.txt', 'purge me')), 'attachment'),
    );
    expect($library->delete(pgConversionContext(), $file->id)->purgePending)->toBeTrue();
    // Many other files with finished operations and conversions in the same tenant.
    DB::statement(<<<'SQL'
        insert into med_files select (jsonb_populate_record(null::med_files, to_jsonb(f) || jsonb_build_object('id', gen_random_uuid(), 'state', 'deleted', 'request_key', 'other-'||g))).*
        from med_files f, generate_series(1, 3000) g where f.id = ?
        SQL, [$file->id]);
    DB::statement(<<<'SQL'
        insert into med_operations (file_id, operation_key, kind, state, claim_epoch, attempts)
        select id, 'purge:'||id, 'purge', 'completed', 1, 1 from med_files where id <> ?
        SQL, [$file->id]);
    DB::statement(<<<'SQL'
        insert into med_conversions (account_id, account_incarnation, workspace_scope, file_id, source_version, profile_key, profile_digest, state)
        select account_id, 'incarnation', 'account', id, 1, 'thumb', repeat('a', 64), 'deleted' from med_files where id <> ?
        SQL, [$file->id]);
    DB::statement('analyze med_files, med_operations, med_conversions');
    $queries = [];
    DB::listen(static function ($query) use (&$queries): void {
        if (preg_match('/^(select|update) .*"(med_operations|med_conversions)".* "file_id" = \?/', $query->sql) === 1) {
            $queries[] = [$query->sql, $query->bindings];
        }
    });

    new PurgeMedia($file->id)->handle(resolve(MediaOperationStore::class));

    expect(DB::table('med_files')->where('id', $file->id)->value('state'))->toBe('deleted')
        ->and($queries)->not->toBeEmpty();
    foreach ($queries as [$sql, $bindings]) {
        expect((string) DB::scalar('explain (format json) '.$sql, $bindings))->not->toContain('"Node Type": "Seq Scan"');
    }
});
