<?php

declare(strict_types=1);

namespace Flex\Crm\Support;

use Packman\Attributes\Internal;
use Packman\Config\Laravel\ConfigurationValidator;

/** @internal Package composition type for flex-crm config (D38 / P16.8). */
#[Internal]
final readonly class CrmOptions
{
    public function __construct(
        public string $searchLanguage,
    ) {}

    /** @param  array<string, mixed>  $config */
    public static function fromArray(array $config): self
    {
        $violations = new ConfigurationValidator;
        $language = self::nonEmpty($violations, 'search_language', $config['search_language'] ?? 'russian');
        $violations->failIfAny();

        return new self(searchLanguage: $language);
    }

    private static function nonEmpty(ConfigurationValidator $violations, string $path, mixed $value): string
    {
        if (is_string($value) && $value !== '') {
            return $value;
        }

        $violations->add(path: $path, message: 'must be a non-empty string');

        return 'invalid';
    }
}
