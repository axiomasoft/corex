<?php

declare(strict_types=1);

namespace Flex\Crm\Support;

use CoreX\Config\ViolationList;

/** @internal Package composition type for flex-crm config (D38 / P16.8). */
final readonly class CrmOptions
{
    public function __construct(
        public string $searchLanguage,
    ) {}

    /** @param  array<string, mixed>  $config */
    public static function fromArray(array $config): self
    {
        $violations = new ViolationList;
        $language = self::nonEmpty($violations, 'search_language', $config['search_language'] ?? 'russian');
        $violations->failIfAny();

        return new self(searchLanguage: $language);
    }

    private static function nonEmpty(ViolationList $violations, string $path, mixed $value): string
    {
        if (is_string($value) && $value !== '') {
            return $value;
        }

        $violations->add($path, 'must be a non-empty string');

        return 'invalid';
    }
}
