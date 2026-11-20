<?php

declare(strict_types=1);

use CoreX\CustomFields\Internal\CustomFieldsOptions;
use Packman\Config\Exceptions\InvalidConfiguration;

it('validates the dynamic DDL setting and rejects ambiguous ownership configuration', function (): void {
    expect(CustomFieldsOptions::fromArray([])->ddl)->toBeTrue()
        ->and(CustomFieldsOptions::fromArray(['ddl' => false])->ddl)->toBeFalse()
        ->and(fn () => CustomFieldsOptions::fromArray(['ddl' => null]))->toThrow(InvalidConfiguration::class)
        ->and(fn () => CustomFieldsOptions::fromArray(['per_field_driver' => []]))->toThrow(InvalidConfiguration::class);
});
