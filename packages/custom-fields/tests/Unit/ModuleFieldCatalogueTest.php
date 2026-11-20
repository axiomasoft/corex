<?php

declare(strict_types=1);

use CoreX\Context\Data\AccountRef;
use CoreX\Context\Data\TenantContext;
use CoreX\CustomFields\Data\AttributeId;
use CoreX\CustomFields\Internal\Schema\ModuleFieldCatalogue;
use CoreX\Tests\Support\CallerWorkScopeFixture;
use Packman\Context\Contracts\ContextScopeInterface;
use Packman\Modules\Contracts\Entities\EntityRegistry;
use Packman\Modules\Contracts\Registry\ModuleRegistry;
use Packman\Modules\Data\Compiler\CompiledEntityField;
use Packman\Modules\Data\Compiler\CompiledModule;
use Packman\Modules\Data\Compiler\CompiledRegistry;
use Packman\Modules\Data\Entities\EntityDefinition;
use Packman\Modules\Data\Entities\EntityField;
use Packman\Modules\Enums\Entities\Capability;

it('projects active compiled entity fields without creating another registry', function (): void {
    $context = new TenantContext(new AccountRef('account-1', 'account-1', 'active', [], []), boxed: true);
    $entity = new EntityDefinition('contact', stdClass::class, 'contacts', 'corex/contacts', 'Contact', 'Contacts', [], [], null);
    $activeField = EntityField::scalar('contact', 'website', 'string')->searchable();
    $sleepingField = EntityField::scalar('contact', 'private_note', 'text');
    $compiled = new CompiledRegistry(
        entities: ['contact' => $entity],
        entityFields: [
            new CompiledEntityField($activeField, 'corex/contacts', false),
            new CompiledEntityField($sleepingField, 'corex/private-notes', true),
        ],
        permissions: [],
        api: [],
        filament: [],
        menu: [],
        routes: [],
        workflow: [],
    );

    $catalogue = new ModuleFieldCatalogue(
        entities: new readonly class($entity) implements EntityRegistry
        {
            public function __construct(private EntityDefinition $entity) {}

            public function get(string $handle): EntityDefinition
            {
                return $this->entity;
            }

            public function has(string $handle): bool
            {
                return $handle === $this->entity->handle;
            }

            public function all(?ContextScopeInterface $ctx = null): array
            {
                return [$this->entity];
            }

            public function byCapability(Capability $cap, ?ContextScopeInterface $ctx = null): array
            {
                return [];
            }

            public function forModel(string $modelClass): ?EntityDefinition
            {
                return null;
            }
        },
        modules: new readonly class($compiled) implements ModuleRegistry
        {
            public function __construct(private CompiledRegistry $compiled) {}

            public function all(): array
            {
                return [];
            }

            public function get(string $name): CompiledModule
            {
                throw new RuntimeException('Unused.');
            }

            public function active(ContextScopeInterface $ctx): array
            {
                return [];
            }

            public function isActive(string $name, ContextScopeInterface $ctx): bool
            {
                return false;
            }

            public function compiled(): CompiledRegistry
            {
                return $this->compiled;
            }
        },
        contexts: new CallerWorkScopeFixture($context),
        drivers: ['jsonb' => true, 'eav' => true, 'ddl' => true],
    );

    $snapshot = $catalogue->snapshot(CallerWorkScopeFixture::forContext($context, 'user-1', str_repeat('a', 64)), 'contact');

    expect($snapshot->schemaVersion)->toBe('attributes/2')
        ->and($snapshot->activeFields)->toBe([[
            'code' => 'website',
            'constraints' => [],
            'id' => AttributeId::legacy('contact', 'website')->value,
            'index' => 'none',
            'key' => 'website',
            'lifecycle' => 'active',
            'meta' => ['legacy_identity' => true],
            'read_keys' => [],
            'required' => false,
            'revision' => 1,
            'searchable' => true,
            'type' => 'text',
            'write_keys' => [],
        ]])
        ->and($snapshot->schemaHash)->toBe($snapshot->schemaHash)
        ->and($snapshot->driverCapabilities)->toBe(['jsonb' => true, 'eav' => true, 'ddl' => true]);
});
