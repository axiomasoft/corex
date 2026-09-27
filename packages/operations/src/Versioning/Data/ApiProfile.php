<?php

declare(strict_types=1);

namespace CoreX\Api\Data;

use CoreX\Api\Internal\JsonCanonical;
use CoreX\Api\Operation;

/** Immutable code projection. It has no register, replace, or freeze API. */
final readonly class ApiProfile
{
    /**
     * @param  list<Operation>  $operations
     * @param  list<CapabilityDescriptor>  $capabilities
     * @param  list<DeprecationDescriptor>  $deprecations
     */
    public function __construct(
        public ApiVersion $version,
        public array $operations,
        public array $capabilities,
        public array $deprecations,
        public string $registryFingerprint,
        public string $contractFingerprint,
    ) {}

    public function canonicalBytes(): string
    {
        return JsonCanonical::encode($this->projection());
    }

    public function fingerprint(): string
    {
        return $this->contractFingerprint;
    }

    /** @return array<string, mixed> */
    public function projection(): array
    {
        return [
            'capabilities' => array_map(
                static fn (CapabilityDescriptor $capability): array => $capability->publicMap(),
                $this->capabilities,
            ),
            'deprecations' => array_map(
                static fn (DeprecationDescriptor $deprecation): array => $deprecation->toArray(),
                $this->deprecations,
            ),
            'operations' => array_map(
                static fn (Operation $operation): array => [
                    'contractHash' => $operation->contractHash,
                    'id' => $operation->id,
                    'version' => $operation->version,
                ],
                $this->operations,
            ),
            'registryFingerprint' => $this->registryFingerprint,
            'version' => $this->version->toArray(),
        ];
    }
}
