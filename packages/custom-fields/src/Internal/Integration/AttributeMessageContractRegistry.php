<?php

declare(strict_types=1);

namespace CoreX\CustomFields\Internal\Integration;

use CoreX\Messaging\Contracts\MessageContractRegistry;
use CoreX\Messaging\Data\MessageContract;
use InvalidArgumentException;
use Override;

/** Adds this package's contracts through the public registry port. */
final readonly class AttributeMessageContractRegistry implements MessageContractRegistry
{
    /** @var array<string, MessageContract> */
    private array $owned;

    public function __construct(private MessageContractRegistry $base)
    {
        $owned = [];

        foreach (AttributeMessageContracts::all() as $contract) {
            $id = $contract->id();

            if (isset($owned[$id]) || $base->has($contract->name, $contract->version)) {
                throw new InvalidArgumentException("Duplicate message contract: {$id}");
            }

            $owned[$id] = $contract;
        }

        $this->owned = $owned;
    }

    #[Override]
    public function has(string $name, int $version): bool
    {
        return isset($this->owned["{$name}.v{$version}"]) || $this->base->has($name, $version);
    }

    #[Override]
    public function get(string $name, int $version): MessageContract
    {
        return $this->owned["{$name}.v{$version}"] ?? $this->base->get($name, $version);
    }

    #[Override]
    public function all(): array
    {
        $contracts = [...$this->base->all(), ...array_values($this->owned)];
        usort($contracts, static fn (MessageContract $left, MessageContract $right): int => $left->id() <=> $right->id());

        return $contracts;
    }
}
