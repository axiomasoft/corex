<?php

declare(strict_types=1);

namespace CoreX\Operations\Testing;

use CoreX\Invocation\Contracts\OperationHandler;
use CoreX\Invocation\Contracts\RequestContext;
use Packman\Attributes\Spi;
use Packman\Attributes\SpiKind;
use Packman\Modules\Data\Compiler\CompiledRegistry;
use Packman\Modules\Data\Extenders\Api;

#[Spi(kind: SpiKind::Call, since: '1.0')]
final class LoadContourModule implements OperationHandler
{
    public static int $calls = 0;

    public static function registry(): CompiledRegistry
    {
        $schema = [
            '$schema' => 'https://json-schema.org/draft/2020-12/schema',
            'type' => 'object',
            'additionalProperties' => false,
            'properties' => [
                'title' => ['type' => 'string'],
                'id' => ['type' => 'string'],
                'actor' => ['type' => 'string'],
                'account' => ['type' => 'string'],
                'workspace' => ['type' => ['string', 'null']],
            ],
        ];
        $operation = Api::operation(
            id: 'notes.create',
            method: 'POST',
            path: '/notes',
            handler: self::class,
            ability: 'notes.read',
            requestSchema: $schema,
            responseSchema: $schema,
            rules: ['title' => ['required', 'string']],
            version: 'v2',
        )->cost(['base' => 1, 'row' => 1, 'max_units' => 10]);
        $tool = Api::exposeTool(operationId: 'notes.create', operationVersion: 'v2', code: 'notes.create', toolVersion: 'v1', sideEffect: 'read', idempotency: 'read-only', argumentSchema: $schema, resultSchema: $schema, parameterMap: ['title' => 'title']);
        $operation->declaringModule = 'example/notes';
        $tool->declaringModule = 'example/notes';

        return new CompiledRegistry(
            entities: [],
            entityFields: [],
            permissions: [],
            api: [$operation, $tool],
            filament: [],
            menu: [],
            routes: [],
            workflow: [],
            fingerprint: hash('sha256', 'load-contour-notes'),
        );
    }

    public function handle(RequestContext $context, array $input, array $parameters): array
    {
        self::$calls++;

        return [
            'title' => $input['title'] ?? 'Note',
            'id' => $parameters['id'] ?? 'new',
            'actor' => $context->principal()->principalId(),
            'account' => $context->tenant()->account->id,
            'workspace' => $context->tenant()->workspace?->id,
        ];
    }
}
