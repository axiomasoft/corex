<?php

declare(strict_types=1);

namespace CoreX\Attributes\Data;

use CoreX\Attributes\Exceptions\InvalidAttributeTables;

final readonly class AttributeTables
{
    private const IDENTIFIER = '/^[a-z][a-z0-9_]*$/';

    public function __construct(
        public string $documents,
        public string $writeTokens,
        public string $eavValues,
        public string $eavRecords,
        public string $schemaOperations,
    ) {
        foreach (get_object_vars($this) as $property => $table) {
            if (preg_match(self::IDENTIFIER, $table) !== 1) {
                throw InvalidAttributeTables::invalidIdentifier(property: $property, table: $table);
            }
        }

        $tables = [$this->documents, $this->writeTokens, $this->eavValues, $this->eavRecords, $this->schemaOperations];

        if (count($tables) !== count(array_unique($tables))) {
            throw InvalidAttributeTables::duplicateNames();
        }
    }
}
