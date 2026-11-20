<?php

declare(strict_types=1);

namespace CoreX\CustomFields\Schemas\Internal;

use CoreX\Data\Data\DataScope;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Query\Builder;
use Packman\Attributes\Internal;
use RuntimeException;

#[Internal]
final class SchemaRows
{
    public const string HEAD = 'sys_field_schema_heads';

    public const string DEFINITIONS = 'sys_attribute_definitions';

    public const string RESERVATIONS = 'sys_attribute_key_reservations';

    public const string DOCUMENTS = 'sys_attribute_documents';

    public const string OPERATIONS = 'sys_attribute_schema_operations';

    public const string BACKFILLS = 'sys_attribute_backfills';

    /** @return array<string, string> */
    public static function logical(DataScope $scope): array
    {
        return ['account_id' => $scope->accountId, 'account_incarnation' => $scope->accountIncarnation,
            'workspace_scope' => $scope->workspaceId === null ? 'account' : 'workspace:'.$scope->workspaceId];
    }

    /** @return array<string, int|string> */
    public static function fence(DataScope $scope): array
    {
        return ['deployment_id' => $scope->deploymentId, 'binding_id' => $scope->bindingId,
            'binding_generation' => $scope->bindingGeneration, 'restore_epoch' => $scope->restoreEpoch];
    }

    public static function rows(ConnectionInterface $db, string $table, DataScope $scope, string $entity): Builder
    {
        return $db->table($table)->where(self::logical($scope))->where('entity', $entity);
    }

    public static function json(mixed $value): string
    {
        return json_encode($value, JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }

    /** @return array<string,mixed> */
    public static function decode(mixed $stored): array
    {
        return SchemaValue::map(json_decode(SchemaValue::text($stored), true, flags: JSON_THROW_ON_ERROR));
    }

    public static function digest(mixed $value): string
    {
        return hash('sha256', self::json(self::canonical($value)));
    }

    private static function canonical(mixed $value): mixed
    {
        if (! is_array($value)) {
            return $value;
        }

        if (! array_is_list($value)) {
            ksort($value, SORT_STRING);
        }

        return array_map(self::canonical(...), $value);
    }

    public static function one(int $changed): void
    {
        if ($changed !== 1) {
            throw new SchemaClaimLost('The field schema claim or revision has changed.');
        }
    }

    public static function transaction(ConnectionInterface $db): void
    {
        if ($db->transactionLevel() < 1) {
            throw new RuntimeException('A field schema mapping requires an active owner transaction.');
        }
    }
}
