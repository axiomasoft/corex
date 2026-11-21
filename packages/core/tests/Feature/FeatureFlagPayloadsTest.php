<?php

declare(strict_types=1);

use CoreX\Data\Laravel\JsonDocuments;
use CoreX\Features\Data\FeatureFlagDefinition;
use CoreX\Features\Internal\DatabaseFeatureFlags;
use CoreX\Foundation\Internal\PackmanFeatureFlags;
use CoreX\Tests\Support\WorkScopeFixture;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Packman\Data\Laravel\JsonDocuments as PackmanJsonDocuments;
use Packman\Features\Data\FeatureFlagDefinition as PackmanFeatureFlagDefinition;

beforeEach(function (): void {
    WorkScopeFixture::install($this->app, principalId: 'flag-operator')->scope();
    Schema::dropIfExists('sys_feature_overrides');
    Schema::dropIfExists('sys_feature_flags');

    Schema::create('sys_feature_flags', static function (Blueprint $table): void {
        $table->uuid('id')->primary();
        $table->string('key', 128)->unique();
        $table->string('module', 190)->nullable();
        $table->boolean('is_enabled')->default(false);
        $table->json('payload')->nullable();
        $table->timestampTz('created_at', precision: 6)->useCurrent();
        $table->timestampTz('updated_at', precision: 6)->useCurrent();
    });

    Schema::create('sys_feature_overrides', static function (Blueprint $table): void {
        $table->uuid('id')->primary();
        $table->uuid('flag_id');
        $table->string('scope_type', 16);
        $table->uuid('scope_id');
        $table->boolean('is_enabled');
        $table->json('payload')->nullable();
        $table->timestampTz('created_at', precision: 6)->useCurrent();
        $table->timestampTz('updated_at', precision: 6)->useCurrent();
    });
});

it('accepts and round-trips scalar, null, and array JSON payloads through DatabaseFeatureFlags and PackmanFeatureFlags', function (): void {
    $flags = new DatabaseFeatureFlags;
    $packmanBridge = new PackmanFeatureFlags($flags);

    $cases = [
        'flag.int' => 42,
        'flag.bool' => true,
        'flag.string' => 'beta',
        'flag.null' => null,
        'flag.map' => ['tier' => 'pro'],
    ];

    foreach ($cases as $key => $expected) {
        $flags->define(new FeatureFlagDefinition(
            key: $key,
            default: true,
            payload: JsonDocuments::capture($expected),
        ));

        expect(JsonDocuments::decode($flags->value($key)))->toBe($expected);

        $packmanKey = 'packman.'.$key;
        $packmanBridge->define(new PackmanFeatureFlagDefinition(
            key: $packmanKey,
            default: true,
            module: 'axiomasoft/corex',
            payload: PackmanJsonDocuments::capture($expected),
        ));

        expect(JsonDocuments::decode($flags->value($packmanKey)))->toBe($expected);
    }
});

it('rejects non-JSON values before persistence', function (): void {
    $resource = fopen('php://memory', 'rb');

    try {
        foreach ([NAN, INF, -INF, new stdClass, $resource] as $invalid) {
            expect(static fn () => JsonDocuments::capture($invalid))->toThrow(InvalidArgumentException::class);
            expect(static fn () => PackmanJsonDocuments::capture($invalid))->toThrow(InvalidArgumentException::class);
        }
    } finally {
        if (is_resource($resource)) {
            fclose($resource);
        }
    }
});
