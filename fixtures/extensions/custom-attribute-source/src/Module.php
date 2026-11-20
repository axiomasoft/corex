<?php

declare(strict_types=1);

namespace ThirdParty\Attributes;

use CoreX\CustomFields\Types\Data\FieldInput;
use CoreX\Data\Data\EntityHandle;
use CoreX\Data\Laravel\JsonDocuments;
use Packman\Modules\Data\Compiler\InstalledPackageVersion;
use Packman\Modules\Data\Entities\Entity;
use Packman\Modules\Data\Entities\FieldDefinition;
use Packman\Modules\Data\Lifecycle\ModuleManifest;
use RuntimeException;

final class Module
{
    public const string Name = 'thirdparty/custom-attribute-source';

    public static function manifest(): ModuleManifest
    {
        return ModuleManifest::make(self::Name)
            ->title('Inspection fields')
            ->description('Declares equipment fields and an inspection-code codec.')
            ->tablePrefix('cas')
            ->extends([
                Entity::make(Equipment::class)->handle(Equipment::Handle)->table('cas_equipment')
                    ->customFields()->fields([new FieldDefinition('name', 'string')]),
                Entity::make(Inspection::class)->handle('cas.inspection')->table('cas_inspections')->fields([new FieldDefinition('name', 'string')]),
            ]);
    }

    public static function installedVersion(): InstalledPackageVersion
    {
        return new InstalledPackageVersion(self::Name, '1.0.0', 'custom-attribute-source');
    }

    public static function fieldFixture(): array
    {
        return [
            'owner' => self::Name,
            'entity' => Equipment::Handle,
            'model' => Equipment::class,
            'fields' => [
                ['id' => DeclaredEquipmentFields::Serial, 'key' => 'serial', 'type' => 'text', 'input' => 'pump-1', 'expected' => 'pump-1'],
                ['id' => DeclaredEquipmentFields::Hours, 'key' => 'hours', 'type' => 'int', 'input' => 12, 'expected' => 12],
                ['id' => DeclaredEquipmentFields::InspectionCode, 'key' => 'inspection_code', 'type' => InspectionCodeType::Id, 'input' => ' a-42 ', 'expected' => 'A-42'],
            ],
            'filter_key' => 'inspection_code',
            'filter_operand' => 'a-42',
            'encoded_operand' => 'inspection:A-42',
        ];
    }

    public static function prove(): void
    {
        $fields = new DeclaredEquipmentFields;
        $entity = new EntityHandle(Equipment::Handle);
        $definitions = $fields->fields($entity);

        if (count($definitions) !== 3) {
            throw new RuntimeException('The fixture must declare all three fields.');
        }

        $inspection = $definitions[2];
        $type = new InspectionCodeType;
        $descriptor = $type->definition();
        $codec = $descriptor->codec;
        $value = $type->normalize(FieldInput::from(' a-42 '), $inspection);
        $encoded = $codec->encode($value);
        $null = $type->normalize(FieldInput::from(null), $inspection);
        $encodedNull = $codec->encode($null);

        if ($inspection->id->value !== DeclaredEquipmentFields::InspectionCode
            || $type->id()->value !== InspectionCodeType::Id || $descriptor->codecVersion !== 1
            || JsonDocuments::decode($value->document) !== 'A-42'
            || JsonDocuments::decode($encoded) !== 'inspection:A-42'
            || JsonDocuments::decode($codec->decode($encoded)->document) !== 'A-42'
            || JsonDocuments::decode($encodedNull) !== null
            || JsonDocuments::decode($codec->decode($encodedNull)->document) !== null) {
            throw new RuntimeException('Equipment field declarations or the codec round trip changed.');
        }
    }
}
