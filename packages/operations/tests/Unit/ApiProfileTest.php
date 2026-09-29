<?php

declare(strict_types=1);

namespace CoreX\Operations\Tests\Unit;

use CoreX\Config\Exceptions\InvalidConfiguration;
use CoreX\Errors\Enums\UnavailabilityReason;
use CoreX\Modules\Compiler\CompiledRegistry;
use CoreX\Modules\Extend\Api;
use CoreX\Operations\Definition\Contracts\OperationHandler;
use CoreX\Operations\Invocation\Contracts\RequestContext;
use CoreX\Operations\Laravel\Support\ApiOptions;
use CoreX\Operations\Tests\TestCase;
use CoreX\Operations\Versioning\Internal\ApiProfileFactory;
use InvalidArgumentException;

final class ApiProfileTest extends TestCase
{
    public function test_reversed_registry_order_keeps_profile_bytes_and_hash(): void
    {
        $first = $this->operation('notes.create', 'notes.write.v1');
        $second = $this->operation('notes.list', 'notes.read.v1');
        $options = ApiOptions::fromArray(['require_physical_tenancy' => true]);
        $factory = new ApiProfileFactory;

        $forward = $factory->make($this->registry([$first, $second]), $options);
        $reverse = $factory->make($this->registry([$second, $first]), $options);

        $this->assertSame($forward->canonicalBytes(), $reverse->canonicalBytes());
        $this->assertSame($forward->fingerprint(), $reverse->fingerprint());
        $this->assertSame(['id', 'limits', 'operations', 'status'], array_keys($forward->capabilities[0]->publicMap()));
    }

    public function test_activation_changes_effective_capability_without_profile_hash(): void
    {
        $options = ApiOptions::fromArray([
            'cursor' => ['current_key_id' => 'primary', 'keyring' => ['primary' => 'secret-a']],
        ]);
        $otherSecret = ApiOptions::fromArray([
            'cursor' => ['current_key_id' => 'primary', 'keyring' => ['primary' => 'secret-b']],
        ]);
        $factory = new ApiProfileFactory;
        $profile = $factory->make($this->registry([$this->operation('notes.create', 'notes.write.v1')]), $options);
        $sameCode = $factory->make($this->registry([$this->operation('notes.create', 'notes.write.v1')]), $otherSecret);

        $enabled = $factory->effective($profile, ['example/notes' => true]);
        $disabled = $factory->effective($profile, ['example/notes' => false]);

        $this->assertSame($profile->fingerprint(), $sameCode->fingerprint());
        $this->assertStringNotContainsString('secret-a', $profile->canonicalBytes());
        $this->assertTrue($enabled[0]->enabled);
        $this->assertNull($enabled[0]->reason);
        $this->assertFalse($disabled[0]->enabled);
        $this->assertSame(UnavailabilityReason::CapabilityDisabled->value, $disabled[0]->reason);
        $this->assertSame($profile->fingerprint(), $factory->make(
            $this->registry([$this->operation('notes.create', 'notes.write.v1')]),
            $options,
        )->fingerprint());
    }

    public function test_hidden_denial_omits_unknown_capabilities_and_unsupported_operations_are_explicit(): void
    {
        $factory = new ApiProfileFactory;
        $unsupported = $factory->effective(
            $factory->make($this->registry([$this->operation('notes.bulk', 'notes.bulk.v1')->asBulkQuery()->cost(['base' => 1])]), ApiOptions::fromArray([])),
            ['example/notes' => true],
        );
        $visible = $factory->make($this->registry([$this->operation('notes.create', 'notes.write.v1')]), ApiOptions::fromArray([]));
        $hidden = $factory->effective($visible, ['example/notes' => true], knownIds: [], deniedIds: ['notes.write.v1']);
        $known = $factory->effective($visible, ['example/notes' => true], knownIds: ['notes.write.v1'], deniedIds: ['notes.write.v1']);

        $this->assertSame(UnavailabilityReason::OperationUnsupported->value, $unsupported[0]->reason);
        $this->assertSame([], $hidden);
        $this->assertSame(UnavailabilityReason::AccessDenied->value, $known[0]->reason);
        $this->assertNotContains(UnavailabilityReason::FeatureNotEntitled->value, array_map(
            static fn ($row) => $row->reason,
            $known,
        ));
    }

    public function test_invalid_profile_inputs_fail(): void
    {
        $this->expectInvalid(fn () => ApiOptions::fromArray(['versions' => [['major' => '1', 'status' => 'stable']]]));
        $this->expectInvalid(fn () => ApiOptions::fromArray(['default_major' => 'v9']));
        $this->expectInvalid(fn () => ApiOptions::fromArray(['versions' => [['major' => 'v1', 'status' => 'beta']]]));
        $this->expectInvalid(fn () => ApiOptions::fromArray([
            'capabilities' => ['notes.write.v1' => ['secret' => 'leak']],
        ]));
        $this->expectInvalid(fn () => ApiOptions::fromArray([
            'capabilities' => ['notes.write.v1' => ['limits' => ['driver' => 'pgsql']]],
        ]));
        $this->expectInvalid(fn () => ApiOptions::fromArray([
            'capabilities' => ['NOT-A-CAPABILITY' => ['status' => 'available']],
        ]));

        $factory = new ApiProfileFactory;
        $broken = $this->operation('notes.create', 'notes.write.v1')
            ->deprecated('notes.create', '2026-09-02', '2026-09-01', null, 'https://example.test/migrate');

        $this->expectException(InvalidArgumentException::class);
        $factory->make($this->registry([$broken]), ApiOptions::fromArray([]));
    }

    public function test_provider_boots_with_typed_options(): void
    {
        $options = $this->app->make(ApiOptions::class);

        $this->assertTrue($options->requirePhysicalTenancy);
        $this->assertSame('v1', $options->defaultMajor);
        $this->assertArrayNotHasKey('primary', $options->publicProjection());
    }

    private function expectInvalid(callable $callback): void
    {
        try {
            $callback();
            $this->fail('Expected invalid configuration.');
        } catch (InvalidConfiguration) {
            $this->addToAssertionCount(1);
        }
    }

    /** @param  list<Api>  $declarations */
    private function registry(array $declarations): CompiledRegistry
    {
        return new CompiledRegistry(
            entities: [],
            entityFields: [],
            permissions: [],
            api: $declarations,
            filament: [],
            menu: [],
            routes: [],
            workflow: [],
            fingerprint: 'registry-fp',
        );
    }

    private function operation(string $id, string $capability): Api
    {
        $schema = ['type' => 'object'];
        $api = Api::operation($id, 'POST', '/'.$id, ProfileHandler::class, 'notes.write', $schema, $schema)
            ->asCommand()
            ->idempotency('required')
            ->effect('tenant_transactional')
            ->capability($capability);
        $api->declaringModule = 'example/notes';

        return $api;
    }
}

final class ProfileHandler implements OperationHandler
{
    public function handle(RequestContext $context, array $input, array $parameters): array
    {
        return [];
    }
}
