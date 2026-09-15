<?php

declare(strict_types=1);

namespace CoreX\Tenancy\Provisioning;

use Closure;
use CoreX\Tenancy\AccountRef;
use CoreX\Tenancy\Contracts\TenantDatabaseLifecycle;
use CoreX\Tenancy\Events\AccountPurged;
use CoreX\Tenancy\Events\ExportReady;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * `export()`/`drop()` on the physical account database (B-11 §5.1/§7.3,
 * D13/AC-7, P2.12). `drop()` mirrors the `DROP DATABASE IF EXISTS` pattern
 * already used by {@see PendingPool::clearPending()}.
 *
 * Scope Excluded: real `pg_dump -Fc`/S3 upload — the actual write step is
 * behind an injectable `$writer` closure (defaults to a no-op that records
 * a synthetic `storage_path`), so this class is unit-testable without real
 * Postgres tooling or S3.
 */
final class StanclTenantDatabaseLifecycle implements TenantDatabaseLifecycle
{
    /**
     * @param  (Closure(AccountRef, string): array{storage_path: string, size_bytes: int})|null  $writer
     */
    public function __construct(
        private readonly string $centralConnection,
        private readonly ?Closure $writer = null,
    ) {}

    public function export(AccountRef $account, string $kind): string
    {
        $exportId = (string) Str::uuid7();

        DB::connection($this->centralConnection)->table('root_account_exports')->insert([
            'id' => $exportId,
            'account_id' => $account->id,
            'kind' => $kind,
            'status' => 'pending',
            'formats' => json_encode(['pg_dump', 'csv'], JSON_THROW_ON_ERROR),
            'purge_after' => now()->addDays(30),
            'url_expires_at' => now()->addDays(14),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::connection($this->centralConnection)->table('root_account_exports')
            ->where('id', $exportId)
            ->update(['status' => 'running', 'updated_at' => now()]);

        $writer = $this->writer ?? self::nullWriter();
        $result = $writer($account, $exportId);

        DB::connection($this->centralConnection)->table('root_account_exports')
            ->where('id', $exportId)
            ->update([
                'status' => 'ready',
                'storage_path' => $result['storage_path'],
                'size_bytes' => $result['size_bytes'],
                'updated_at' => now(),
            ]);

        event(new ExportReady(account: $account, exportId: $exportId));

        return $exportId;
    }

    public function drop(AccountRef $account): void
    {
        $latestExport = DB::connection($this->centralConnection)->table('root_account_exports')
            ->where('account_id', $account->id)
            ->orderByDesc('created_at')
            ->first();

        if ($latestExport === null || $latestExport->status !== 'ready') {
            throw new RuntimeException(
                "Refusing to drop account [{$account->id}]'s database: no ready export on record (export-before-DROP invariant, D13/AC-7).",
            );
        }

        $dbName = DB::connection($this->centralConnection)->table('root_accounts')
            ->where('id', $account->id)
            ->value('db_name');

        DB::connection($this->centralConnection)->statement('DROP DATABASE IF EXISTS "'.$dbName.'"');

        event(new AccountPurged($account));
    }

    /**
     * @return Closure(AccountRef, string): array{storage_path: string, size_bytes: int}
     */
    private static function nullWriter(): Closure
    {
        return static fn (AccountRef $account, string $exportId): array => [
            'storage_path' => "exports/{$account->id}/{$exportId}.dump",
            'size_bytes' => 0,
        ];
    }
}
