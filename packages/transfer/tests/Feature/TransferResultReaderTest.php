<?php

declare(strict_types=1);

use CoreX\Context\Data\AccountRef;
use CoreX\Context\Data\TenantContext;
use CoreX\Data\CallableDataScopeAuthorizer;
use CoreX\Data\DataScope;
use CoreX\Transfer\Data\TransferRef;
use CoreX\Transfer\Internal\Transfer\SqlTransferResultReader;
use CoreX\Transfer\Internal\Transfer\TransferUnavailable;
use CoreX\Transfer\Tests\PgTestCase;
use Illuminate\Support\Facades\DB;

uses(PgTestCase::class)->group('pg');

it('returns a bounded failure page and a typed manifest from transfer tables only', function (): void {
    $hash = str_repeat('a', 64);
    $jobId = 'aaaaaaaa-aaaa-4aaa-8aaa-aaaaaaaaaa01';
    DB::table('corex_transfer_jobs')->insert([
        'id' => $jobId, 'source_manifest' => '{}', 'source_hash' => $hash, 'mapping_snapshot' => '{}', 'mapping_hash' => $hash,
        'schema_hash' => $hash, 'restore_epoch' => $hash, 'mode' => 'import', 'state' => 'completed', 'checkpoint_seq' => '2',
        'cursor' => '{"ordinal":"2","boundaryByteOffset":"40"}', 'stats' => '{"invalid":1}', 'claim_epoch' => '1',
        'created_at' => now(), 'updated_at' => now(),
    ]);
    foreach ([['1', 'rowFailed'], ['2', 'unknownTarget']] as [$ordinal, $code]) {
        DB::table('corex_transfer_rows')->insert([
            'id' => (string) str()->uuid(),
            'job_id' => $jobId,
            'ordinal' => $ordinal,
            'external_identity_hash' => str_repeat('b', 64),
            'operation_key' => hash('sha256', $ordinal),
            'payload_hash' => $hash,
            'result' => 'invalid',
            'error' => json_encode(['code' => $code, 'path' => 'values.name'], JSON_THROW_ON_ERROR),
            'epoch' => '1',
        ]);
    }
    $reader = new SqlTransferResultReader(DB::connection(), new CallableDataScopeAuthorizer(static fn (): bool => true));
    $scope = readerScope($hash);

    $page = $reader->failures(scope: $scope, ref: new TransferRef($jobId), afterOrdinal: null, limit: 1);
    $next = $reader->failures(scope: $scope, ref: new TransferRef($jobId), afterOrdinal: $page['next'], limit: 1);
    $manifest = $reader->result(scope: $scope, ref: new TransferRef($jobId));

    expect($page['records'])->toBe([['ordinal' => '1', 'code' => 'rowFailed', 'path' => 'values.name']])
        ->and($page['next'])->toBe('1')
        ->and($next['records'][0]['code'])->toBe('unknownTarget')
        ->and($next['next'])->toBeNull()
        ->and($manifest['state'])->toBe('completed')
        ->and($manifest['checkpointSequence'])->toBe('2')
        ->and($manifest)->not->toHaveKey('source_manifest');
});

it('refuses a result when the scope or policy does not match', function (): void {
    $hash = str_repeat('c', 64);
    $jobId = 'cccccccc-cccc-4ccc-8ccc-cccccccccc01';
    DB::table('corex_transfer_jobs')->insert([
        'id' => $jobId, 'source_manifest' => '{}', 'source_hash' => $hash, 'mapping_snapshot' => '{}', 'mapping_hash' => $hash,
        'schema_hash' => $hash, 'restore_epoch' => $hash, 'mode' => 'import', 'state' => 'completed', 'checkpoint_seq' => '1',
        'cursor' => '{"ordinal":"1","boundaryByteOffset":"1"}', 'stats' => '{}', 'claim_epoch' => '1',
        'created_at' => now(), 'updated_at' => now(),
    ]);
    $reader = new SqlTransferResultReader(DB::connection(), new CallableDataScopeAuthorizer(static fn (): bool => false));

    expect(fn () => $reader->result(scope: readerScope($hash), ref: new TransferRef($jobId)))
        ->toThrow(TransferUnavailable::class)
        ->and(fn () => (new SqlTransferResultReader(DB::connection(), new CallableDataScopeAuthorizer(static fn (): bool => true)))
            ->result(scope: readerScope(str_repeat('d', 64)), ref: new TransferRef($jobId)))
        ->toThrow(TransferUnavailable::class);
});

function readerScope(string $hash): DataScope
{
    return DataScope::fromTenantContext(
        context: new TenantContext(new AccountRef('account-1', 'account-1', 'active', [], []), boxed: true),
        connectionIdentity: 'pgsql:corex_test',
        actorId: 'user-1',
        restoreEpoch: $hash,
    );
}
