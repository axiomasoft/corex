<?php

declare(strict_types=1);

namespace CoreX\SearchMeilisearch\Internal;

use CoreX\Search\Errors\Exceptions\SearchFeatureUnavailable;
use CoreX\Search\Index\Data\IndexTarget;
use Packman\Attributes\Internal;

#[Internal]
final class MeilisearchNamespace
{
    public const string Stamp = '_corex_namespace';

    public const string Order = '_corex_order';

    public const string WitnessVersion = '_corex_witness_version';

    public static function fingerprint(IndexTarget $target, string $cluster): string
    {
        if ($target->backend !== 'meilisearch' || $target->cluster !== $cluster) {
            throw SearchFeatureUnavailable::unsupported();
        }
        $scope = $target->scope;

        return hash('sha256', json_encode([
            $target->backend, $target->cluster, $target->generation, $target->uid, $target->mappingDigest, $target->entity->value,
            $scope->deploymentId, $scope->bindingId, $scope->bindingGeneration, $scope->restoreEpoch,
            $scope->accountId, $scope->accountIncarnation, $scope->workspaceId,
        ], JSON_THROW_ON_ERROR));
    }

    public static function order(string $id): string
    {
        return 'r'.bin2hex($id);
    }

    public static function recordId(mixed $key): string
    {
        if (! is_string($key) || preg_match('/\Ar(?:[0-9a-f]{2}){1,128}\z/', $key) !== 1) {
            throw SearchFeatureUnavailable::unsupported();
        }
        $hex = hex2bin(substr($key, 1));

        return $hex === false ? throw SearchFeatureUnavailable::unsupported() : $hex;
    }

    public static function field(string $name): string
    {
        return 'f_'.bin2hex($name);
    }

    public static function witness(string $name): string
    {
        return 'w_'.bin2hex($name);
    }
}
