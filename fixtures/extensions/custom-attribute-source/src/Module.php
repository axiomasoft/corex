<?php

declare(strict_types=1);

namespace ThirdParty\Attributes;

use CoreX\CustomFields\Data\AttributeDefinition;
use CoreX\CustomFields\Data\AttributeId;
use CoreX\CustomFields\Data\AttributeLifecycle;
use CoreX\CustomFields\Data\IndexPolicy;
use CoreX\Database\Laravel\BaseModel;
use Packman\Modules\Data\Compiler\InstalledPackageVersion;
use Packman\Modules\Data\Entities\Entity;
use Packman\Modules\Data\Entities\FieldDefinition;
use Packman\Modules\Data\Lifecycle\ModuleManifest;
use RuntimeException;

final class Module
{
    public static function manifest(): ModuleManifest
    {
        return ModuleManifest::make('thirdparty/custom-attribute-source')
            ->title('Custom attribute source')
            ->description('Compiles inspection attributes from fixture-owned scalar data.')
            ->tablePrefix('cas')
            ->extends([
                Entity::make(BaseModel::class)->handle('cas.equipment')->table('cas_equipment')->customFields()->fields([new FieldDefinition('name', 'string')]),
                Entity::make(BaseModel::class)->handle('cas.inspection')->table('cas_inspections')->fields([new FieldDefinition('name', 'string')]),
            ]);
    }

    public static function installedVersion(): InstalledPackageVersion
    {
        return new InstalledPackageVersion('thirdparty/custom-attribute-source', '1.0.0', 'custom-attribute-source');
    }

    public static function prove(): void
    {
        $forward = self::compile(self::scalars());
        $reversed = self::compile(array_reverse(self::scalars()));
        $forwardHash = AttributeDefinition::schemaHash('equipment', $forward);
        $reversedHash = AttributeDefinition::schemaHash('equipment', $reversed);

        if ($forwardHash !== $reversedHash || count($forward) !== 2) {
            throw new RuntimeException('Attribute compilation changed when the scalar source order was reversed.');
        }

        if ($forward[0]->key === $forward[1]->key) {
            throw new RuntimeException('Attribute source collapsed two inspection fields.');
        }
    }

    /**
     * @param  list<array{id: string, key: string, type: string}>  $rows
     * @return list<AttributeDefinition>
     */
    private static function compile(array $rows): array
    {
        $definitions = [];

        foreach ($rows as $row) {
            $definitions[] = new AttributeDefinition(
                id: AttributeId::v7($row['id']),
                entity: 'equipment',
                key: $row['key'],
                revision: 1,
                type: $row['type'],
                lifecycle: AttributeLifecycle::Active,
                required: true,
                searchable: false,
                constraints: [],
                readKeys: ['inspect'],
                writeKeys: ['inspect'],
                index: IndexPolicy::None,
            );
        }

        return AttributeDefinition::canonicalList($definitions);
    }

    /** @return list<array{id: string, key: string, type: string}> */
    private static function scalars(): array
    {
        return [
            ['id' => '01900000-0000-7000-8000-000000000001', 'key' => 'serial', 'type' => 'string'],
            ['id' => '01900000-0000-7000-8000-000000000002', 'key' => 'hours', 'type' => 'integer'],
        ];
    }
}
