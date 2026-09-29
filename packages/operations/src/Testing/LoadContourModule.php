<?php

declare(strict_types=1);

namespace CoreX\Operations\Testing;

use CoreX\Modules\Compiler\CompiledRegistry;
use CoreX\Modules\Extend\Api;
use CoreX\Operations\Definition\Contracts\OperationHandler;
use CoreX\Operations\Invocation\Contracts\RequestContext;

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
            'notes.create',
            'POST',
            '/notes',
            self::class,
            'notes.read',
            $schema,
            $schema,
            ['title' => ['required', 'string']],
        )->cost(['base' => 1, 'row' => 1, 'max_units' => 10]);
        $tool = Api::exposeTool('notes.create', 'v1', 'notes.create', 'v1', 'read', 'read-only', $schema, $schema, ['title' => 'title']);
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
            'actor' => $context->principal()->id(),
            'account' => $context->tenant()->account->id,
            'workspace' => $context->tenant()->workspace?->id,
        ];
    }
}
