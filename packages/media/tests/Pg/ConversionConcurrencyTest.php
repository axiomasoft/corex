<?php

declare(strict_types=1);

use CoreX\Context\Data\AccountRef;
use CoreX\Context\Data\TenantContext;
use CoreX\Media\Contracts\MediaLibrary;
use CoreX\Media\Contracts\MediaPolicy;
use CoreX\Media\Data\MediaAction;
use CoreX\Media\Data\MediaContext;
use CoreX\Media\Data\MediaSubject;
use CoreX\Media\Data\UploadInput;
use CoreX\Media\Tests\PgTestCase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

uses(PgTestCase::class)->group('pg');

function pgConversionContext(): MediaContext
{
    return new MediaContext(
        tenant: new TenantContext(new AccountRef('11111111-1111-7111-8111-111111111111', 'media-pg-test', 'active', [], [])),
        actorKind: 'user',
        actorId: '11111111-1111-7111-8111-111111111114',
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

    $file = app(MediaLibrary::class)->store(
        pgConversionContext(),
        new UploadInput('pg-lock-request', UploadedFile::fake()->createWithContent('source.txt', 'postgres lock'), 'attachment'),
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
