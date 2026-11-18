<?php

declare(strict_types=1);

namespace CoreX\Operations\Versioning\Data;

use CoreX\Operations\Definition\Data\Operation;
use CoreX\Operations\Validation\Laravel\JsonCanonical;
use Packman\Attributes\Api;

/** Immutable code projection. It has no register, replace, or freeze API. */
#[Api(since: '1.0')]
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

    /** @return array{capabilities: list<array{id: string, status: string, limits: array<string, int|string>, operations: list<string>}>, deprecations: list<array{id: string, deprecatedAt: string, sunsetAt: ?string, successor: ?string, migrationUri: string}>, operations: list<array{contractHash: string, id: string, version: string}>, registryFingerprint: string, version: array{major: string, status: string, supportedThrough: ?string}} */
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
