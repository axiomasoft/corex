<?php

declare(strict_types=1);

namespace CoreX\Api\Internal\Bulk;

use CoreX\Api\Internal\JsonCanonical;
use CoreX\Contracts\DataScopeCodec;
use CoreX\Exceptions\ScopeContextMismatch;
use CoreX\Support\Data\DataScope;
use CoreX\Tenancy\Contracts\TenantContextRestorer;
use DateInterval;
use DateTimeImmutable;
use Illuminate\Database\Connection;
use Illuminate\Database\Query\Builder;
use InvalidArgumentException;
use Psr\Clock\ClockInterface;

/**
 * Clock-controlled bulk result and tombstone cleanup.
 *
 * Result expiry clears owned blobs and input references. Metadata expiry
 * removes the terminal row. Active work and expired leases are left in place.
 *
 * @internal
 */
final readonly class BulkRetention
{
    public const int DefaultResultSeconds = 86_400;

    public const int DefaultMetadataSeconds = 2_592_000;

    public const int BatchLimit = 100;

    private const string Table = 'sys_api_bulk_operations';

    public function __construct(
        private Connection $connection,
        private DataScopeCodec $scopeCodec,
        private TenantContextRestorer $contexts,
        private ClockInterface $clock,
        private int $resultRetentionSeconds = self::DefaultResultSeconds,
        private int $metadataRetentionSeconds = self::DefaultMetadataSeconds,
        private int $idempotencyRetentionSeconds = self::DefaultResultSeconds,
    ) {
        if ($this->resultRetentionSeconds < 1
            || $this->metadataRetentionSeconds < 1
            || $this->idempotencyRetentionSeconds < 1) {
            throw new InvalidArgumentException('Bulk retention windows must be positive.');
        }

        if ($this->resultRetentionSeconds > $this->metadataRetentionSeconds) {
            throw new InvalidArgumentException('Bulk result retention must not exceed metadata retention.');
        }

        if ($this->metadataRetentionSeconds < $this->idempotencyRetentionSeconds) {
            throw new InvalidArgumentException('Bulk metadata retention must cover idempotency retention.');
        }
    }

    /**
     * @return array{cleared: int, removed: int}
     */
    public function prune(DataScope $scope, int $limit = self::BatchLimit): array
    {
        $this->assertLimit($limit);
        $this->assertScope($scope);
        $now = $this->clock->now();
        $resultCutoff = $now->sub(new DateInterval('PT'.$this->resultRetentionSeconds.'S'));
        $metadataCutoff = $now->sub(new DateInterval('PT'.$this->metadataRetentionSeconds.'S'));

        $removeRows = $this->terminal($scope, $now)
            ->whereNotNull('finished_at')
            ->where('finished_at', '<=', $metadataCutoff)
            ->orderBy('id')
            ->limit($limit)
            ->get();
        $removeIds = [];

        foreach ($removeRows as $row) {
            if ($this->trusted($row, $scope)) {
                $removeIds[] = (string) $row->id;
            }
        }

        $remaining = $limit - count($removeIds);
        $clearIds = [];

        if ($remaining > 0) {
            $clearRows = $this->terminal($scope, $now)
                ->where(function (Builder $query) use ($now, $resultCutoff): void {
                    $query->where(function (Builder $expired) use ($now): void {
                        $expired->whereNotNull('expires_at')->where('expires_at', '<=', $now);
                    })->orWhere(function (Builder $window) use ($resultCutoff): void {
                        $window->whereNotNull('finished_at')->where('finished_at', '<=', $resultCutoff);
                    });
                })
                ->where(fn (Builder $query): Builder => $this->wherePiiRemains($query))
                ->when($removeIds !== [], fn (Builder $query): Builder => $query->whereNotIn('id', $removeIds))
                ->orderBy('id')
                ->limit($remaining)
                ->get();

            foreach ($clearRows as $row) {
                if ($this->trusted($row, $scope)) {
                    $clearIds[] = (string) $row->id;
                }
            }
        }

        $removed = $removeIds === []
            ? 0
            : $this->connection->table(self::Table)->whereIn('id', $removeIds)->delete();
        $cleared = $clearIds === []
            ? 0
            : $this->connection->table(self::Table)->whereIn('id', $clearIds)->update([
                'input_ref' => '{}',
                'result_ref' => null,
            ]);

        return ['cleared' => $cleared, 'removed' => $removed];
    }

    private function assertLimit(int $limit): void
    {
        if ($limit < 1 || $limit > self::BatchLimit) {
            throw new InvalidArgumentException('Bulk retention batch must be between 1 and 100.');
        }
    }

    private function assertScope(DataScope $scope): void
    {
        $context = $this->contexts->restore($scope);
        $actual = $context->isBoxed()
            ? 'boxed:'.$context->account->id
            : $this->connection->getDriverName().':'.$this->connection->getDatabaseName();

        if (! hash_equals($scope->connectionIdentity, $actual) || ! hash_equals($scope->accountId, $context->account->id)) {
            throw ScopeContextMismatch::connection(expected: $scope->connectionIdentity, actual: $actual);
        }
    }

    private function terminal(DataScope $scope, DateTimeImmutable $now): Builder
    {
        return $this->scoped($scope)
            ->whereNotIn('status', ['pending', 'running', 'cancelling'])
            ->where(function (Builder $query) use ($now): void {
                $query->whereNull('lease_until')->orWhere('lease_until', '<=', $now);
            });
    }

    private function scoped(DataScope $scope): Builder
    {
        $encoded = JsonCanonical::encode($this->scopeCodec->encode($scope));
        $query = $this->connection->table(self::Table);

        if ($this->connection->getDriverName() === 'pgsql') {
            return $query->whereRaw('scope::jsonb = ?::jsonb', [$encoded]);
        }

        return $query->where('scope', $encoded);
    }

    private function wherePiiRemains(Builder $query): Builder
    {
        return $query->where(function (Builder $pii): void {
            $pii->whereNotNull('result_ref');

            if ($this->connection->getDriverName() === 'pgsql') {
                $pii->orWhereRaw("input_ref::text NOT IN ('{}', '[]')");

                return;
            }

            $pii->orWhereNotIn('input_ref', ['{}', '[]']);
        });
    }

    private function trusted(object $row, DataScope $scope): bool
    {
        if ($row->result_ref === null) {
            return true;
        }

        $decoded = JsonCanonical::decode((string) $row->result_ref);

        if (! is_array($decoded)) {
            return false;
        }

        if ($decoded === []) {
            return true;
        }

        if (array_is_list($decoded)) {
            return false;
        }

        $account = $decoded['account_id'] ?? null;
        $operationId = $decoded['operation_id'] ?? null;
        $hash = $decoded['hash'] ?? null;
        $bytes = $decoded['bytes'] ?? null;

        if (! is_string($account) || ! hash_equals($scope->accountId, $account)) {
            return false;
        }

        if (! is_string($operationId) || ! hash_equals((string) $row->id, $operationId)) {
            return false;
        }

        if (! is_string($hash) || preg_match('/\A[a-f0-9]{64}\z/D', $hash) !== 1) {
            return false;
        }

        if (! is_string($bytes) || ! hash_equals(hash('sha256', $bytes), $hash)) {
            return false;
        }

        return true;
    }
}
