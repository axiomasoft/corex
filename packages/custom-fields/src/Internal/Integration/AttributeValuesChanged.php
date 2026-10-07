<?php

declare(strict_types=1);

namespace CoreX\CustomFields\Internal\Integration;

use CoreX\Data\Data\DataScope;
use DateTimeImmutable;
use InvalidArgumentException;

final readonly class AttributeValuesChanged
{
    /** @var list<string> */
    public array $changedAttributeIds;

    /** @var list<string> */
    public array $changedKeys;

    /**
     * @param  list<string>  $changedAttributeIds
     * @param  list<string>  $changedKeys
     */
    public function __construct(
        public string $messageId,
        public DataScope $scope,
        public string $recordEntity,
        public string $recordId,
        public string $recordVersion,
        public string $schemaHash,
        array $changedAttributeIds,
        array $changedKeys,
        public DateTimeImmutable $occurredAt,
        public ?string $correlationId = null,
        public ?string $causationId = null,
    ) {
        if ($messageId === '' || $recordVersion === '' || $recordEntity === '' || $recordId === '') {
            throw new InvalidArgumentException('Attribute mutation identity and record version are required.');
        }

        if (preg_match('/\A[a-f0-9]{64}\z/D', $schemaHash) !== 1) {
            throw new InvalidArgumentException('Attribute mutation schema hash must be a lowercase SHA-256 digest.');
        }

        $this->changedAttributeIds = self::canonical(values: $changedAttributeIds, name: 'attribute ids');
        $this->changedKeys = self::canonical(values: $changedKeys, name: 'attribute keys');
    }

    /**
     * @param  list<string>  $values
     * @return list<string>
     */
    private static function canonical(array $values, string $name): array
    {
        if ($values === []) {
            throw new InvalidArgumentException("Changed {$name} must not be empty.");
        }

        foreach ($values as $value) {
            if ($value === '' || preg_match('/[\x00-\x1f\x7f]/', $value) === 1) {
                throw new InvalidArgumentException("Changed {$name} contain an invalid value.");
            }
        }

        $values = array_values(array_unique($values));
        sort($values, SORT_STRING);

        return $values;
    }
}
