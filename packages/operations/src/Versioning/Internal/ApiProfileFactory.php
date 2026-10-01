<?php

declare(strict_types=1);

namespace CoreX\Operations\Versioning\Internal;

use CoreX\Errors\Enums\UnavailabilityReason;
use CoreX\Modules\Data\Api;
use CoreX\Modules\Data\CompiledRegistry;
use CoreX\Operations\Definition\Data\Operation;
use CoreX\Operations\Definition\Laravel\OperationFactory;
use CoreX\Operations\Laravel\Support\ApiOptions;
use CoreX\Operations\Validation\Laravel\JsonCanonical;
use CoreX\Operations\Versioning\Contracts\ApiProfiles;
use CoreX\Operations\Versioning\Data\ApiProfile;
use CoreX\Operations\Versioning\Data\CapabilityDescriptor;
use CoreX\Operations\Versioning\Data\DeprecationDescriptor;
use CoreX\Operations\Versioning\Data\EffectiveCapability;
use InvalidArgumentException;

/** Builds one sorted code profile. Tenant activation is resolved separately. */
final class ApiProfileFactory implements ApiProfiles
{
    public function make(CompiledRegistry $registry, ApiOptions $options): ApiProfile
    {
        $declarations = [];

        foreach ($registry->api as $declaration) {
            if ($declaration->mode !== 'operation') {
                continue;
            }

            $declarations[] = $declaration;
        }

        usort($declarations, static function (Api $left, Api $right): int {
            return [$left->version, $left->operationId] <=> [$right->version, $right->operationId];
        });

        $operations = [];
        $seen = [];

        foreach ($declarations as $declaration) {
            $key = $declaration->version."\0".$declaration->operationId;

            if (isset($seen[$key])) {
                throw new InvalidArgumentException('profile: duplicate operation '.$declaration->version.':'.$declaration->operationId);
            }

            $seen[$key] = true;
            $operation = OperationFactory::fromDeclaration($declaration, $registry->fingerprint);

            if ($operation->version !== $options->defaultVersion()->major && ! $this->declaresMajor($options, $operation->version)) {
                throw new InvalidArgumentException('profile: operation version is not a configured major');
            }

            $operations[] = $operation;
        }

        $capabilities = $this->capabilities($operations, $options);
        $deprecations = $this->deprecations($operations);
        $version = $options->defaultVersion();
        $preimage = [
            'capabilities' => array_map(
                static fn (CapabilityDescriptor $capability): array => $capability->publicMap(),
                $capabilities,
            ),
            'deprecations' => array_map(
                static fn (DeprecationDescriptor $deprecation): array => $deprecation->toArray(),
                $deprecations,
            ),
            'operations' => array_map(
                static fn (Operation $operation): array => [
                    'contractHash' => $operation->contractHash,
                    'id' => $operation->id,
                    'version' => $operation->version,
                ],
                $operations,
            ),
            'options' => $options->publicProjection(),
            'registryFingerprint' => $registry->fingerprint,
            'version' => $version->toArray(),
        ];

        return new ApiProfile(
            version: $version,
            operations: $operations,
            capabilities: $capabilities,
            deprecations: $deprecations,
            registryFingerprint: $registry->fingerprint,
            contractFingerprint: hash('sha256', JsonCanonical::encode($preimage)),
        );
    }

    /**
     * Live view. Activation changes this result and never the profile fingerprint.
     *
     * @param  array<string, bool>  $moduleAvailable
     * @param  list<string>|null  $knownIds  null skips the caller filter
     * @param  list<string>  $deniedIds
     * @return list<EffectiveCapability>
     */
    public function effective(
        ApiProfile $profile,
        array $moduleAvailable,
        ?array $knownIds = null,
        array $deniedIds = [],
    ): array {
        $resolved = [];

        foreach ($profile->capabilities as $capability) {
            if ($knownIds !== null && ! in_array($capability->id, $knownIds, true)) {
                continue;
            }

            $owners = $this->owners($profile, $capability);
            $available = $owners !== [] && ! in_array(false, array_map(
                static fn (string $module) => $moduleAvailable[$module] ?? false,
                $owners,
            ), true);
            $supported = $this->supported($profile, $capability);
            $reason = null;
            $enabled = $available && $supported && ! in_array($capability->id, $deniedIds, true);

            if (! $available) {
                $reason = UnavailabilityReason::CapabilityUnavailable->value;
                $missing = array_filter(
                    $owners,
                    static fn (string $module): bool => array_key_exists($module, $moduleAvailable) && $moduleAvailable[$module] === false,
                );

                if ($missing !== []) {
                    $reason = UnavailabilityReason::CapabilityDisabled->value;
                }
            } elseif (! $supported) {
                $reason = UnavailabilityReason::OperationUnsupported->value;
            } elseif (in_array($capability->id, $deniedIds, true)) {
                $reason = UnavailabilityReason::AccessDenied->value;
            }

            $resolved[] = new EffectiveCapability($capability->id, $enabled, $reason, $capability->limits);
        }

        return $resolved;
    }

    private function declaresMajor(ApiOptions $options, string $major): bool
    {
        foreach ($options->versions as $version) {
            if ($version->major === $major) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  list<Operation>  $operations
     * @return list<CapabilityDescriptor>
     */
    private function capabilities(array $operations, ApiOptions $options): array
    {
        /** @var array<string, list<string>> $owners */
        $owners = [];

        foreach ($operations as $operation) {
            foreach ($operation->capabilities as $id) {
                $owners[$id][] = $operation->version.':'.$operation->id;
            }
        }

        foreach (array_keys($options->capabilities) as $id) {
            if (! isset($owners[$id])) {
                throw new InvalidArgumentException('capability: '.$id.' is not declared by an operation');
            }
        }

        ksort($owners);
        $descriptors = [];

        foreach ($owners as $id => $operationIds) {
            $meta = $options->capabilities[$id] ?? ['status' => 'available', 'limits' => []];
            sort($operationIds);
            $descriptors[] = new CapabilityDescriptor($id, $meta['status'], $operationIds, $meta['limits']);
        }

        return $descriptors;
    }

    /**
     * @param  list<Operation>  $operations
     * @return list<DeprecationDescriptor>
     */
    private function deprecations(array $operations): array
    {
        $descriptors = [];

        foreach ($operations as $operation) {
            if ($operation->deprecation === null) {
                continue;
            }

            $row = $operation->deprecation;
            $descriptors[] = new DeprecationDescriptor(
                id: is_string($row['id'] ?? null) ? $row['id'] : '',
                deprecatedAt: is_string($row['deprecatedAt'] ?? null) ? $row['deprecatedAt'] : '',
                sunsetAt: is_string($row['sunsetAt'] ?? null) ? $row['sunsetAt'] : null,
                successor: is_string($row['successor'] ?? null) ? $row['successor'] : null,
                migrationUri: is_string($row['migrationUri'] ?? null) ? $row['migrationUri'] : '',
            );
        }

        usort($descriptors, static fn (DeprecationDescriptor $left, DeprecationDescriptor $right): int => $left->id <=> $right->id);

        return $descriptors;
    }

    /** @return list<string> */
    private function owners(ApiProfile $profile, CapabilityDescriptor $capability): array
    {
        $modules = [];

        foreach ($profile->operations as $operation) {
            if (in_array($operation->version.':'.$operation->id, $capability->operationIds, true)) {
                $modules[] = $operation->module;
            }
        }

        return array_values(array_unique($modules));
    }

    private function supported(ApiProfile $profile, CapabilityDescriptor $capability): bool
    {
        foreach ($profile->operations as $operation) {
            if (in_array($operation->version.':'.$operation->id, $capability->operationIds, true) && $operation->executionSupported) {
                return true;
            }
        }

        return false;
    }
}
