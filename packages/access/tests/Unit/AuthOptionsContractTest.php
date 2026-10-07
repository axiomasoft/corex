<?php

declare(strict_types=1);

namespace CoreX\Access\Tests\Unit;

use CoreX\Access\Exceptions\ScopeCapabilityMismatch;
use CoreX\Access\Internal\Support\AccessConfigSection;
use CoreX\Access\Laravel\AuthOptions;
use CoreX\Config\Exceptions\InvalidConfiguration;
use CoreX\Errors\Enums\ErrorCategory;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class AuthOptionsContractTest extends TestCase
{
    public function test_defaults_and_explicit_options_preserve_every_admission_field(): void
    {
        $default = AuthOptions::fromArray(config: []);
        self::assertSame('app', $default->panel);
        self::assertNull($default->cacheStore);
        self::assertNull($default->workspaceScopeClass);
        self::assertTrue($default->identityEnabled);
        self::assertNull($default->identityConnection);
        self::assertSame('users', $default->identityTable);
        self::assertSame(['id' => 'id', 'email' => 'email', 'password' => 'password_hash', 'active' => 'is_active', 'deleted_at' => 'deleted_at'], $default->identityColumns);
        $columns = ['id' => 'uuid', 'email' => 'address', 'password' => 'hash', 'active' => 'enabled', 'deleted_at' => 'removed'];
        $explicit = AuthOptions::fromArray(config: ['panel' => 'admin', 'cache' => ['store' => 'redis'], 'workspace_scope_class' => 'WorkspaceScope', 'identity' => ['enabled' => false, 'connection' => 'root_test', 'table' => 'members', 'columns' => $columns]]);
        self::assertSame(['admin', 'redis', 'WorkspaceScope', false, 'root_test', 'members', $columns], [$explicit->panel, $explicit->cacheStore, $explicit->workspaceScopeClass, $explicit->identityEnabled, $explicit->identityConnection, $explicit->identityTable, $explicit->identityColumns]);
        foreach ([false, 1, [], ''] as $value) {
            $normalized = AuthOptions::fromArray(config: ['cache' => ['store' => $value], 'workspace_scope_class' => $value, 'identity' => ['connection' => $value]]);
            self::assertNull($normalized->workspaceScopeClass);
            self::assertSame(is_string($value) ? $value : null, $normalized->cacheStore);
            self::assertSame(is_string($value) ? $value : null, $normalized->identityConnection);
        }
    }

    #[DataProvider('invalidConfigurations')]
    public function test_missing_or_malformed_identity_columns_and_required_options_fail_closed(array $config, string $path): void
    {
        $this->expectException(InvalidConfiguration::class);
        $this->expectExceptionMessage($path);
        AuthOptions::fromArray(config: $config);
    }

    /** @return iterable<string, array{array<string, mixed>, string}> */
    public static function invalidConfigurations(): iterable
    {
        yield 'panel empty' => [['panel' => ''], 'panel: must be a non-empty string'];
        yield 'panel scalar' => [['panel' => 7], 'panel: must be a non-empty string'];
        yield 'identity table' => [['identity' => ['table' => '']], 'identity.table: must be a non-empty string'];
        yield 'identity enabled' => [['identity' => ['enabled' => 'false']], 'identity.enabled: must be a boolean'];
        $columns = ['id' => 'id', 'email' => 'email', 'password' => 'password_hash', 'active' => 'is_active', 'deleted_at' => 'deleted_at'];
        foreach (array_keys($columns) as $key) {
            $missing = $columns;
            unset($missing[$key]);
            yield 'missing '.$key => [['identity' => ['columns' => $missing]], 'identity.columns.'.$key.': must be a non-empty string'];
            foreach (['', 1] as $index => $value) {
                yield 'invalid '.$key.' '.$index => [['identity' => ['columns' => array_replace($columns, [$key => $value])]], 'identity.columns.'.$key.': must be a non-empty string'];
            }
        }
    }

    public function test_configuration_report_does_not_add_secrets_or_adapters_and_scope_errors_are_safe(): void
    {
        $section = new AccessConfigSection;
        self::assertSame(20, $section->order());
        self::assertSame([AuthOptions::class => ['corex-access']], $section->options());
        self::assertSame([], $section->secretKeys());
        self::assertSame([], $section->secretDefaults());
        self::assertSame([], $section->drivers());
        self::assertSame([], $section->adapters());
        $error = ScopeCapabilityMismatch::forScope(entityKey: 'contact', scope: 'own', missingCapability: 'ownable', column: 'owner_id');
        self::assertSame('Entity [contact] has a matrix row with scope [own], which requires capability [ownable] because column [owner_id] exists on its table — but the entity does not declare that capability. Declare it on the EntityDefinition rather than letting the scope silently widen to account.', $error->getMessage());
        self::assertSame('access.scope_capability_mismatch', $error->errorDescriptor()->code);
        self::assertSame(ErrorCategory::Unsupported, $error->errorDescriptor()->category);
        self::assertFalse($error->errorDescriptor()->retryable);
    }
}
