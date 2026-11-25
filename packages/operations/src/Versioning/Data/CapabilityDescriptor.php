<?php

declare(strict_types=1);

namespace CoreX\Operations\Versioning\Data;

use InvalidArgumentException;
use Packman\Attributes\Api;

#[Api(since: '1.0')]
final readonly class CapabilityDescriptor
{
    /** @var list<string> */
    public const array PUBLIC_KEYS = ['id', 'status', 'limits', 'operations'];

    /** @var list<string> */
    public const array FORBIDDEN_KEYS = ['driver', 'connection', 'class', 'table', 'path', 'secret', 'package', 'host'];

    /**
     * @param  list<string>  $operationIds
     * @param  array<string, int|string>  $limits
     */
    public function __construct(
        public string $id,
        public string $status,
        public array $operationIds,
        public array $limits = [],
    ) {
        if (preg_match('/^[a-z][a-z0-9_.-]*\.v[1-9][0-9]*$/D', $id) !== 1) {
            throw new InvalidArgumentException('capability: id must match name.vN');
        }

        if (! in_array($status, ['available', 'deprecated'], true)) {
            throw new InvalidArgumentException('capability: status must be available or deprecated');
        }

        $operations = $operationIds;
        sort($operations);

        if ($operations !== $operationIds || $operationIds === []) {
            throw new InvalidArgumentException('capability: operations must be a non-empty sorted list');
        }

        self::validateLimits($limits);
    }

    /** @return array{id: string, status: string, limits: array<string, int|string>, operations: list<string>} */
    public function publicMap(): array
    {
        return [
            'id' => $this->id,
            'limits' => $this->limits,
            'operations' => $this->operationIds,
            'status' => $this->status,
        ];
    }

    /** @param array<string, int|string> $limits */
    public static function assertPublicLimits(array $limits): void
    {
        self::validateLimits($limits);
    }

    /** @param array<array-key, mixed> $limits */
    private static function validateLimits(array $limits): void
    {
        foreach ($limits as $key => $value) {
            if (! is_string($key) || in_array($key, self::FORBIDDEN_KEYS, true)) {
                throw new InvalidArgumentException('capability: public map rejects infrastructure keys');
            }

            if (preg_match('/^[a-z][a-z0-9_]*$/D', $key) !== 1) {
                throw new InvalidArgumentException('capability: limit keys must be public names');
            }

            if (! is_int($value) && ! is_string($value)) {
                throw new InvalidArgumentException('capability: limits must be bounded scalars');
            }

            if (is_string($value) && (str_contains($value, '://') || str_contains($value, '\\'))) {
                throw new InvalidArgumentException('capability: public map rejects secret or path values');
            }
        }
    }
}
