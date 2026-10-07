<?php

declare(strict_types=1);

namespace CoreX\CustomFields\Data;

use CoreX\Attributes\Api;
use Illuminate\Support\Str;
use InvalidArgumentException;

#[Api(since: '1.0')]
final readonly class AttributeId
{
    /**
     * Fixed CoreX namespace for the UUIDv5 compatibility bridge.
     * Name material is `entity` + NUL + `key`.
     */
    public const string LEGACY_NAMESPACE = 'c0e1a000-a77e-4000-8000-000000000001';

    public function __construct(public string $value)
    {
        if (! self::isVersion(value: $value, version: 7) && ! self::isVersion(value: $value, version: 5)) {
            throw new InvalidArgumentException('Attribute id must be UUIDv7 or a legacy UUIDv5 bridge.');
        }
    }

    public static function generate(): self
    {
        return self::v7((string) Str::uuid7());
    }

    public static function v7(string $value): self
    {
        if (! self::isVersion(value: $value, version: 7)) {
            throw new InvalidArgumentException('New attribute ids must be UUIDv7.');
        }

        return new self($value);
    }

    public static function legacy(string $entity, string $key): self
    {
        return new self(self::uuid5($entity."\0".$key));
    }

    public function isV7(): bool
    {
        return self::isVersion(value: $this->value, version: 7);
    }

    public function isLegacy(): bool
    {
        return self::isVersion(value: $this->value, version: 5);
    }

    private static function uuid5(string $name): string
    {
        $namespace = hex2bin(str_replace('-', '', self::LEGACY_NAMESPACE));
        $hash = sha1($namespace.$name, true);
        $hash[6] = chr((ord($hash[6]) & 0x0F) | 0x50);
        $hash[8] = chr((ord($hash[8]) & 0x3F) | 0x80);

        $hex = bin2hex($hash);

        return sprintf(
            '%s-%s-%s-%s-%s',
            substr($hex, 0, 8),
            substr($hex, 8, 4),
            substr($hex, 12, 4),
            substr($hex, 16, 4),
            substr($hex, 20, 12),
        );
    }

    private static function isVersion(string $value, int $version): bool
    {
        if (preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $value) !== 1) {
            return false;
        }

        return (int) hexdec($value[14]) === $version;
    }
}
