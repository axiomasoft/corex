<?php

declare(strict_types=1);

namespace CoreX\Attributes\Internal\Schema;

use InvalidArgumentException;

final readonly class CatalogueConflictPolicy
{
    /**
     * @param  list<array{higher: string, lower: string, attribute_id: string, allowed_difference: string}>  $overrides
     */
    public function __construct(
        public string $version,
        private array $overrides = [],
    ) {
        if ($this->version === '') {
            throw new InvalidArgumentException('Catalogue conflict policy version is required.');
        }

        $seen = [];
        foreach ($this->overrides as $override) {
            foreach (['higher', 'lower', 'attribute_id', 'allowed_difference'] as $field) {
                if ($override[$field] === '' || str_contains($override[$field], '*')) {
                    throw new InvalidArgumentException('Catalogue conflict overrides must be exact and complete.');
                }
            }

            if ($override['higher'] === $override['lower']) {
                throw new InvalidArgumentException('Catalogue conflict override sources must be distinct.');
            }

            $key = implode("\0", $override);

            if (isset($seen[$key])) {
                throw new InvalidArgumentException('Duplicate catalogue conflict override.');
            }

            $seen[$key] = true;
        }
    }

    public function allowsShadow(
        string $higher,
        string $lower,
        string $attributeId,
        string $difference,
    ): bool {
        foreach ($this->overrides as $override) {
            if ($override === [
                'higher' => $higher,
                'lower' => $lower,
                'attribute_id' => $attributeId,
                'allowed_difference' => $difference,
            ]) {
                return true;
            }
        }

        return false;
    }
}
