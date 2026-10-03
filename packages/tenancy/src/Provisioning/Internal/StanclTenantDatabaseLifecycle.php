<?php

declare(strict_types=1);

namespace CoreX\Tenancy\Provisioning\Internal;

use Closure;
use CoreX\Context\Contracts\TenantDatabaseLifecycle;
use CoreX\Context\Data\AccountRef;
use CoreX\Tenancy\Account\Events\AccountPurged;
use CoreX\Tenancy\Account\Events\ExportReady;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Database\Connection;
use Illuminate\Database\ConnectionResolverInterface;
use Illuminate\Support\Str;
use Psr\Clock\ClockInterface;
use RuntimeException;

/**
 * `export()`/`drop()` on the physical account database (B-11 §5.1/§7.3,
 * D13/AC-7, P2.12). `drop()` mirrors the `DROP DATABASE IF EXISTS` pattern
 * already used by {@see PendingPool::clearPending()}.
 *
 * The host supplies a writer that returns a durable archive path and size.
 * Every writer must supply a verifier binding readable bytes, size, content
 * digest and tenant ownership to a trusted durable archive manifest. Size
 * alone never authorizes ready or DROP, including local archives.
 */
final class StanclTenantDatabaseLifecycle implements TenantDatabaseLifecycle
{
    /**
     * @param  Closure(AccountRef, string):array{storage_path: string, size_bytes: int}|null  $writer
     * @param  (Closure(string, int): bool)|null  $verifier
     */
    public function __construct(
        private readonly string $centralConnection,
        private readonly ConnectionResolverInterface $connections,
        private readonly ClockInterface $clock,
        private readonly Dispatcher $events,
        private readonly ?Closure $writer = null,
        private readonly ?Closure $verifier = null,
    ) {}

    public function export(AccountRef $account, string $kind): string
    {
        if ($this->writer === null) {
            throw new RuntimeException('No tenant export writer configured; refusing to create a ready export.');
        }
        $this->archiveVerifier();

        $exportId = (string) Str::uuid7();

        $this->db()->table('root_account_exports')->insert([
            'id' => $exportId,
            'account_id' => $account->id,
            'kind' => $kind,
            'status' => 'pending',
            'formats' => json_encode(['pg_dump', 'csv'], JSON_THROW_ON_ERROR),
            'purge_after' => $this->clock->now()->modify('+30 days'),
            'url_expires_at' => $this->clock->now()->modify('+14 days'),
            'created_at' => $this->clock->now(),
            'updated_at' => $this->clock->now(),
        ]);

        $this->db()->table('root_account_exports')
            ->where('id', $exportId)
            ->update(['status' => 'running', 'updated_at' => $this->clock->now()]);

        $writer = $this->writer;
        $result = $writer($account, $exportId);
        $this->verifyArchive($result['storage_path'], $result['size_bytes']);

        $this->db()->table('root_account_exports')
            ->where('id', $exportId)
            ->update([
                'status' => 'ready',
                'storage_path' => $result['storage_path'],
                'size_bytes' => $result['size_bytes'],
                'updated_at' => $this->clock->now(),
            ]);

        $this->events->dispatch(new ExportReady(account: $account, exportId: $exportId));

        return $exportId;
    }

    public function drop(AccountRef $account): void
    {
        $latestExport = $this->db()->table('root_account_exports')
            ->where('account_id', $account->id)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->first();

        if ($latestExport === null || $latestExport->status !== 'ready') {
            throw new RuntimeException(
                "Refusing to drop account [{$account->id}]'s database: no ready export on record (export-before-DROP invariant, D13/AC-7).",
            );
        }

        $this->verifyArchive((string) $latestExport->storage_path, (int) $latestExport->size_bytes);

        $dbName = $this->db()->table('root_accounts')
            ->where('id', $account->id)
            ->value('db_name');

        if (! is_string($dbName) || ! preg_match('/\Aacc_[a-zA-Z0-9_]+\z/D', $dbName)) {
            throw new RuntimeException('Invalid account database name; refusing DROP.');
        }

        $this->db()->statement('DROP DATABASE IF EXISTS "'.$dbName.'"');

        $this->events->dispatch(new AccountPurged($account));
    }

    private function verifyArchive(string $path, int $size): void
    {
        $verifier = $this->archiveVerifier();
        $verified = $path !== '' && $size > 0 && $verifier($path, $size);

        if (! $verified) {
            throw new RuntimeException('Tenant export archive is unavailable or has an invalid size; refusing ready/DROP.');
        }
    }

    private function archiveVerifier(): Closure
    {
        return $this->verifier ?? throw new RuntimeException('A qualified tenant archive integrity verifier is required; refusing ready/DROP.');
    }

    private function db(): Connection
    {
        /** @var Connection */
        $connection = $this->connections->connection($this->centralConnection);

        return $connection;
    }
}
