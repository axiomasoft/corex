<?php

declare(strict_types=1);

namespace CoreX\Search\Index\Data;

use CoreX\Data\Data\DataScope;
use CoreX\Data\Data\EntityHandle;
use CoreX\Data\Data\RecordRef;
use CoreX\Data\Data\ScopeIdentity;
use InvalidArgumentException;
use Packman\Attributes\Api;

#[Api(since: '2.0.0')]
final readonly class IndexTarget
{
    public function __construct(
        public DataScope $scope,
        public EntityHandle $entity,
        public string $backend,
        public string $cluster,
        public int $generation,
        public string $uid,
        public string $mappingDigest,
        public string $allocationId = '',
    ) {
        ScopeIdentity::opaque($cluster);
        ScopeIdentity::opaque($backend);

        if ($allocationId !== '') {
            ScopeIdentity::opaque($allocationId);
        }

        if ($generation < 1 || $uid !== self::uidFor($scope, $entity, $backend, $cluster, $generation, $allocationId)
            || preg_match('/\A[a-f0-9]{64}\z/D', $mappingDigest) !== 1) {
            throw new InvalidArgumentException('Invalid index namespace.');
        }
    }

    public static function uidFor(DataScope $scope, EntityHandle $entity, string $backend, string $cluster, int $generation, string $allocationId = ''): string
    {
        return 'cx_'.self::digest('corex.search.uid.v2', $scope->deploymentId, $scope->bindingId, (string) $scope->bindingGeneration,
            $scope->restoreEpoch, $scope->accountId, $scope->accountIncarnation, $scope->workspaceId === null ? 'account' : 'workspace',
            $scope->workspaceId ?? '', $entity->value, $backend, $cluster, (string) $generation, $allocationId);
    }

    public function documentId(RecordRef $record): string
    {
        if ($record->entity !== $this->entity->value) {
            throw new InvalidArgumentException('Record belongs to another index entity.');
        }

        return self::digest('corex.search.document.v2', $this->uid, $this->scope->accountId, $this->scope->accountIncarnation,
            $this->scope->workspaceId === null ? 'account' : 'workspace', $this->scope->workspaceId ?? '', $record->entity, $record->id);
    }

    private static function digest(string ...$parts): string
    {
        $bytes = '';
        foreach ($parts as $part) {
            $bytes .= pack('N', strlen($part)).$part;
        }

        return hash('sha256', $bytes);
    }
}
