<?php

declare(strict_types=1);

namespace CoreX\CustomFields\Internal\Values;

use Closure;
use InvalidArgumentException;
use JsonException;
use Packman\Attributes\Internal;
use RuntimeException;

#[Internal]
final readonly class AttributeValueTransformerRegistry
{
    /**
     * @param  array<string, array{forward: Closure, reverse?: Closure}>  $transformers
     */
    public function __construct(private array $transformers = [])
    {
        foreach ($this->transformers as $name => $_transformer) {
            if ($name === '') {
                throw new InvalidArgumentException('Attribute transformers require a name.');
            }
        }
    }

    public function has(string $name): bool
    {
        return isset($this->transformers[$name]);
    }

    public function isReversible(string $name): bool
    {
        return isset($this->transformers[$name]['reverse']);
    }

    public function forward(string $name, mixed $value): mixed
    {
        return $this->apply(name: $name, direction: 'forward', value: $value);
    }

    public function reverse(string $name, mixed $value): mixed
    {
        return $this->apply(name: $name, direction: 'reverse', value: $value);
    }

    /** @param array<string, mixed> $meta */
    public function toTarget(array $meta, mixed $value): mixed
    {
        $name = $meta['evolution_transformer'] ?? null;

        if ($name === null) {
            return $value;
        }

        if (! is_string($name)) {
            throw new RuntimeException('Attribute evolution transformer id must be a string.');
        }

        return ($meta['evolution_transformer_direction'] ?? 'forward') === 'reverse'
            ? $this->reverse(name: $name, value: $value)
            : $this->forward(name: $name, value: $value);
    }

    /** @param array<string, mixed> $meta */
    public function toSource(array $meta, mixed $value): mixed
    {
        $name = $meta['evolution_transformer'] ?? null;

        if ($name === null) {
            return $value;
        }

        if (! is_string($name)) {
            throw new RuntimeException('Attribute evolution transformer id must be a string.');
        }

        return ($meta['evolution_transformer_direction'] ?? 'forward') === 'reverse'
            ? $this->forward(name: $name, value: $value)
            : $this->reverse(name: $name, value: $value);
    }

    private function apply(string $name, string $direction, mixed $value): mixed
    {
        $transformer = $this->transformers[$name][$direction] ?? null;

        if (! $transformer instanceof Closure) {
            throw new RuntimeException("Attribute transformer [{$name}] does not support {$direction} conversion.");
        }

        $first = $transformer($value);
        $second = $transformer($value);

        if (! hash_equals($this->canonicalDigest($first), $this->canonicalDigest($second))) {
            throw new RuntimeException("Attribute transformer [{$name}] is not deterministic.");
        }

        return $first;
    }

    private function canonicalDigest(mixed $value): string
    {
        try {
            return hash('sha256', json_encode($value, JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION));
        } catch (JsonException $exception) {
            throw new RuntimeException('Attribute transformer returned a value that cannot be canonicalized.', previous: $exception);
        }
    }
}
