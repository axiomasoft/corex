<?php

declare(strict_types=1);

namespace CoreX\Operations\Versioning\Data;

use DateTimeImmutable;
use InvalidArgumentException;

final readonly class DeprecationDescriptor
{
    public function __construct(
        public string $id,
        public string $deprecatedAt,
        public ?string $sunsetAt,
        public ?string $successor,
        public string $migrationUri,
    ) {
        if ($id === '') {
            throw new InvalidArgumentException('deprecation: id is required');
        }

        $deprecated = self::date('deprecatedAt', $deprecatedAt);
        $sunset = $sunsetAt === null ? null : self::date('sunsetAt', $sunsetAt);

        if ($sunset !== null && $sunset < $deprecated) {
            throw new InvalidArgumentException('deprecation: sunsetAt must not precede deprecatedAt');
        }

        if (preg_match('#\Ahttps://#', $migrationUri) !== 1) {
            throw new InvalidArgumentException('deprecation: migrationUri must be an absolute https URI');
        }
    }

    /** @return array{id: string, deprecatedAt: string, sunsetAt: ?string, successor: ?string, migrationUri: string} */
    public function toArray(): array
    {
        return [
            'deprecatedAt' => $this->deprecatedAt,
            'id' => $this->id,
            'migrationUri' => $this->migrationUri,
            'successor' => $this->successor,
            'sunsetAt' => $this->sunsetAt,
        ];
    }

    private static function date(string $path, string $value): string
    {
        $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $value);

        if ($parsed === false || $parsed->format('Y-m-d') !== $value) {
            throw new InvalidArgumentException('deprecation: '.$path.' must be a YYYY-MM-DD date');
        }

        return $value;
    }
}
