<?php

declare(strict_types=1);

use CoreX\Intl\Internal\IntlOptions;
use Packman\Config\Exceptions\InvalidConfiguration;

it('passes on an empty environment with the shipped defaults', function (): void {
    $options = IntlOptions::fromArray([]);

    expect($options->localeSupportedTags)->toBe(['en', 'en-US', 'ru', 'ru-RU'])
        ->and($options->localeAppTag)->toBe('en')
        ->and($options->localeMaxTagLength)->toBe(128)
        ->and($options->moneyMetadataVersion)->toBe('brick/1')
        ->and($options->moneyBatchLimit)->toBe(1000)
        ->and($options->referenceDatasetKey)->toBe('geography')
        ->and($options->formattingIcuEnabled)->toBeFalse();
});

it('accumulates violations across locale, money, reference and formatting sections', function (): void {
    try {
        IntlOptions::fromArray([
            'locale' => ['supported_tags' => 'en', 'max_tag_length' => 0],
            'money' => ['batch_limit' => 'many'],
            'formatting' => ['icu_enabled' => 'yes'],
        ]);
        $this->fail('Invalid configuration was accepted.');
    } catch (InvalidConfiguration $exception) {
        expect($exception->violations)->toBe([
            'locale.supported_tags: must be a list of strings',
            'locale.max_tag_length: must be a positive integer',
            'money.batch_limit: must be a positive integer',
            'formatting.icu_enabled: must be a boolean',
        ]);
    }
});

it('hides the cursor secret and rebuilds the locale policy input', function (): void {
    $options = IntlOptions::fromArray(['reference' => ['cursor_secret' => 'a-private-cursor-secret'], 'locale' => ['app_tag' => 'ru']]);

    expect($options->referenceCursorSecret->reveal())->toBe('a-private-cursor-secret')
        ->and((string) $options->referenceCursorSecret)->toBe('[redacted]')
        ->and($options->localePolicyConfig()['app_tag'])->toBe('ru');
});
