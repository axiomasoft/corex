<?php

declare(strict_types=1);

namespace CoreX\CustomFields\Internal\Integration;

use CoreX\Messaging\Contracts\MessageContractRegistry;
use CoreX\Messaging\Data\MessageContract;
use InvalidArgumentException;
use Override;
use Packman\Attributes\Internal;

/** Adds this package's contracts through the public registry port. */
#[Internal]
final readonly class AttributeMessageContractRegistry implements MessageContractRegistry
{
    /** @var array<string, MessageContract> */
    private array $owned;

    public function __construct(private MessageContractRegistry $base)
    {
        $owned = [];

        foreach (AttributeMessageContracts::all() as $contract) {
            $id = $contract->id();

            if (isset($owned[$id]) || $base->has(name: $contract->name, version: $contract->version)) {
                throw new InvalidArgumentException("Duplicate message contract: {$id}");
            }

            $owned[$id] = $contract;
        }

        $this->owned = $owned;
    }

    #[Override]
    public function has(string $name, int $version): bool
    {
        return isset($this->owned["{$name}.v{$version}"]) || $this->base->has(name: $name, version: $version);
    }

    #[Override]
    public function get(string $name, int $version): MessageContract
    {
        return $this->owned["{$name}.v{$version}"] ?? $this->base->get(name: $name, version: $version);
    }

    #[Override]
    public function all(): array
    {
        $contracts = [...$this->base->all(), ...array_values($this->owned)];
        usort($contracts, static fn (MessageContract $left, MessageContract $right): int => $left->id() <=> $right->id());

        return $contracts;
    }
}
