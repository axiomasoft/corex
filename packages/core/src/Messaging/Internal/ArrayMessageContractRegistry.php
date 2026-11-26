<?php

declare(strict_types=1);

namespace CoreX\Messaging\Internal;

use CoreX\Messaging\Contracts\MessageContractRegistry;
use CoreX\Messaging\Data\MessageContract;
use CoreX\Messaging\Exceptions\UnknownMessageContract;
use Override;
use Packman\Attributes\Internal;

/**
 * Immutable contract registry assembled in the composition root. Duplicate
 * identities fail at construction, as required by EXT-006.
 */
#[Internal]
final readonly class ArrayMessageContractRegistry implements MessageContractRegistry
{
    /** @var array<string, MessageContract> */
    private array $contracts;

    /** @param list<MessageContract> $contracts */
    public function __construct(array $contracts)
    {
        $indexed = [];

        foreach ($contracts as $contract) {
            if (isset($indexed[$contract->id()])) {
                throw UnknownMessageContract::duplicate($contract->id());
            }

            $indexed[$contract->id()] = $contract;
        }

        ksort($indexed);

        $this->contracts = $indexed;
    }

    #[Override]
    public function has(string $name, int $version): bool
    {
        return isset($this->contracts["{$name}.v{$version}"]);
    }

    #[Override]
    public function get(string $name, int $version): MessageContract
    {
        return $this->contracts["{$name}.v{$version}"]
            ?? throw UnknownMessageContract::notDeclared(name: $name, version: $version);
    }

    #[Override]
    public function all(): array
    {
        return array_values($this->contracts);
    }
}
