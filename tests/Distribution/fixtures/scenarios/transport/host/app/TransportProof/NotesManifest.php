<?php

declare(strict_types=1);

namespace App\TransportProof;

use CoreX\Operations\Bulk\Laravel\BulkOperationDeclarations;
use CoreX\Operations\Metadata\Laravel\MetadataOperationDeclarations;
use CoreX\Webhooks\Laravel\WebhookOperationDeclarations;
use Packman\Modules\Contracts\Definition\ModuleDiscovery;
use Packman\Modules\Data\Extenders\Api;
use Packman\Modules\Data\Lifecycle\ModuleManifest;

final readonly class NotesManifest implements ModuleDiscovery
{
    public const string OWNER = 'laravel/laravel';

    public function __construct(private ModuleDiscovery $installed) {}

    public function discover(): array
    {
        return [...$this->installed->discover(), self::manifest()];
    }

    public static function manifest(): ModuleManifest
    {
        $input = [
            'type' => 'object', 'required' => ['resource_id', 'title'], 'additionalProperties' => false,
            'properties' => [
                'resource_id' => ['type' => 'string', 'maxLength' => 128],
                'title' => ['type' => 'string', 'minLength' => 1, 'maxLength' => 80],
            ],
        ];
        $output = [
            'type' => 'object', 'required' => ['id', 'resource_id', 'title'], 'additionalProperties' => false,
            'properties' => [
                'id' => ['type' => 'string'], 'resource_id' => ['type' => 'string'],
                'title' => ['type' => 'string'], 'private_label' => ['type' => 'string'],
            ],
        ];
        $operation = Api::operation(
            id: 'notes.create', method: 'POST', path: '/notes', handler: CreateNote::class, ability: 'notes.write',
            requestSchema: $input, responseSchema: $output, rules: ['resource_id' => ['required', 'string', 'max:128'], 'title' => ['required', 'string', 'max:80']],
            version: 'v2', successStatus: 201,
        )->asCommand()->idempotency('required')->effect('tenant_transactional')->cost(['base' => 2])
            ->capability('operations.resource_policy.v2')
            ->fields(read: ['/id', '/resource_id', '/title', '/private_label'], write: ['/resource_id', '/title']);

        return ModuleManifest::make(self::OWNER)->title('Installed Notes')->tablePrefix('nts')->extends([
            $operation,
            Api::exposeTool(
                operationId: 'notes.create', operationVersion: 'v2', code: 'notes.create', toolVersion: 'v2',
                sideEffect: 'write', idempotency: 'domain-key', argumentSchema: $input, resultSchema: $output,
                parameterMap: ['resource_id' => 'resource_id', 'title' => 'title'],
            ),
            ...BulkOperationDeclarations::all(),
            ...MetadataOperationDeclarations::all(),
            ...WebhookOperationDeclarations::all(),
        ]);
    }
}
