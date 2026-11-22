<?php

declare(strict_types=1);

namespace CoreX\Operations\Metadata\Laravel;

use CoreX\Operations\Metadata\Internal\DescribeEntity;
use Packman\Attributes\Spi;
use Packman\Attributes\SpiKind;
use Packman\Modules\Data\Extenders\Api;

#[Spi(kind: SpiKind::Call, since: '2.0.0')]
final class MetadataOperationDeclarations
{
    /** @return list<Api> */
    public static function all(): array
    {
        return [self::operation('fields'), self::operation('search')];
    }

    private static function operation(string $kind): Api
    {
        $id = $kind.'.describe';

        return Api::operation(
            id: $id,
            method: 'GET',
            path: '/entities/{entity}/'.$kind,
            handler: DescribeEntity::class,
            ability: $id,
            requestSchema: ['type' => 'object', 'additionalProperties' => false],
            responseSchema: [
                'type' => 'object',
                'required' => ['version', 'entity', 'schema_revision', 'capabilities', 'fields'],
                'properties' => [
                    'version' => ['type' => 'string', 'const' => 'v2'],
                    'entity' => ['type' => 'string'],
                    'schema_revision' => ['type' => 'string'],
                    'capabilities' => ['type' => 'object'],
                    'fields' => ['type' => 'array', 'items' => ['type' => 'object', 'required' => ['id', 'type', 'uses'], 'properties' => [
                        'id' => ['type' => 'string'],
                        'type' => ['type' => 'string'],
                        'uses' => ['type' => 'array', 'items' => ['type' => 'string']],
                        'operators' => ['type' => 'array', 'items' => ['type' => 'string']],
                    ]]],
                ],
            ],
            version: 'v2',
        );
    }
}
