<?php

declare(strict_types=1);

use CoreX\Access\Laravel\AuthOptions;
use CoreX\Config\Exceptions\InvalidConfiguration;

it('normalizes an empty workspace scope class to null', function (): void {
    $options = AuthOptions::fromArray([
        'workspace_scope_class' => '',
    ]);

    expect($options->workspaceScopeClass)->toBeNull()
        ->and($options->panel)->toBe('app');
});

it('rejects an empty panel', function (): void {
    try {
        AuthOptions::fromArray(['panel' => '']);
        $this->fail('Invalid configuration was accepted.');
    } catch (InvalidConfiguration $exception) {
        expect($exception->violations)->toContain('panel: must be a non-empty string');
    }
});

it('accumulates an empty panel and incomplete identity columns in one exception', function (): void {
    try {
        AuthOptions::fromArray([
            'panel' => '',
            'identity' => [
                'columns' => [
                    'id' => 'id',
                    'email' => 'email',
                    'active' => 'is_active',
                    'deleted_at' => 'deleted_at',
                ],
            ],
        ]);
        $this->fail('Invalid configuration was accepted.');
    } catch (InvalidConfiguration $exception) {
        expect($exception->violations)->toBe([
            'panel: must be a non-empty string',
            'identity.columns.password: must be a non-empty string',
        ]);
    }
});
