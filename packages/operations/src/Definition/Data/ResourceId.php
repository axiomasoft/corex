<?php

declare(strict_types=1);

namespace CoreX\Api\Data;

use CoreX\Enums\IdStrategy;
use InvalidArgumentException;
use Stringable;

final readonly class ResourceId implements Stringable
{
    private function __construct(
        public string $type,
        public IdStrategy $strategy,
        public int|string $value,
    ) {}

    public static function fromValue(string $type, int|string $value, IdStrategy $strategy): self
    {
        self::assertType($type);

        return new self($type, $strategy, self::canonicalValue($value, $strategy));
    }

    public static function fromConfigured(
        string $type,
        int|string $value,
        IdStrategy $configuredStrategy = IdStrategy::Uuid7,
    ): self {
        return self::fromValue($type, $value, $configuredStrategy);
    }

    public static function parse(
        string $encoded,
        IdStrategy $expectedStrategy,
        ?string $expectedType = null,
    ): self {
        $parts = explode(':', $encoded, 3);

        if (count($parts) !== 3) {
            throw new InvalidArgumentException('Resource id must use the <type>:<strategy>:<value> form.');
        }

        [$type, $wireStrategy, $value] = $parts;
        self::assertType($type);

        if ($expectedType !== null && ! hash_equals($expectedType, $type)) {
            throw new InvalidArgumentException('Resource id type does not match the expected type.');
        }

        if (! hash_equals(self::wireStrategy($expectedStrategy), $wireStrategy)) {
            throw new InvalidArgumentException('Resource id strategy does not match the expected strategy.');
        }

        $resourceId = self::fromValue($type, $value, $expectedStrategy);

        if (! hash_equals($resourceId->toString(), $encoded)) {
            throw new InvalidArgumentException('Resource id is not canonically encoded.');
        }

        return $resourceId;
    }

    public function toString(): string
    {
        return "{$this->type}:".self::wireStrategy($this->strategy).":{$this->value}";
    }

    public function __toString(): string
    {
        return $this->toString();
    }

    private static function assertType(string $type): void
    {
        if (preg_match('/^[a-z][a-z0-9_]{1,31}$/D', $type) !== 1) {
            throw new InvalidArgumentException('Resource id type is invalid.');
        }
    }

    private static function canonicalValue(int|string $value, IdStrategy $strategy): int|string
    {
        return match ($strategy) {
            IdStrategy::Int => self::canonicalInteger($value),
            IdStrategy::Ulid => self::canonicalUlid($value),
            IdStrategy::Uuid => self::canonicalUuid($value),
            IdStrategy::Uuid7 => self::canonicalUuid($value, 7),
        };
    }

    private static function canonicalInteger(int|string $value): int
    {
        $encoded = (string) $value;

        if (preg_match('/^[1-9][0-9]*$/D', $encoded) !== 1
            || filter_var($encoded, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) === false) {
            throw new InvalidArgumentException('Resource id integer must be a canonical positive integer.');
        }

        return (int) $encoded;
    }

    private static function canonicalUlid(int|string $value): string
    {
        if (! is_string($value) || preg_match('/^[0-7][0-9A-HJKMNP-TV-Z]{25}$/D', $value) !== 1) {
            throw new InvalidArgumentException('Resource id ULID must use canonical uppercase encoding.');
        }

        return $value;
    }

    private static function canonicalUuid(int|string $value, ?int $version = null): string
    {
        if (! is_string($value)
            || preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[1-8][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/D', $value) !== 1
            || ($version !== null && $value[14] !== (string) $version)) {
            throw new InvalidArgumentException($version !== null
                ? 'Resource id UUIDv7 must use canonical lowercase encoding.'
                : 'Resource id UUID must use canonical lowercase encoding.');
        }

        return $value;
    }

    private static function wireStrategy(IdStrategy $strategy): string
    {
        return match ($strategy) {
            IdStrategy::Uuid, IdStrategy::Uuid7 => 'uuid',
            IdStrategy::Ulid => 'ulid',
            IdStrategy::Int => 'integer',
        };
    }
}
