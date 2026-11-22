<?php

declare(strict_types=1);

namespace CoreX\Operations\OpenApi\Internal;

use CoreX\Data\Laravel\JsonDocuments;
use CoreX\Operations\Definition\Contracts\OperationRegistry;
use CoreX\Operations\Definition\Data\Operation;
use CoreX\Operations\OpenApi\Contracts\ContractSnapshotSection;
use CoreX\Operations\Versioning\Data\ApiProfile;
use InvalidArgumentException;
use Packman\Attributes\Internal;
use Packman\Errors\Contracts\ProblemRegistry;

/** @internal Installed-code v2 snapshot. */
#[Internal]
final readonly class ContractSnapshotBuilder
{
    public const array FILES = ['errors.json', 'openapi.json', 'operations.json', 'webhooks.json', 'manifest.json'];

    /** @var array<string, string> */
    private const array ENFORCEMENT = [
        'account' => 'observe',
        'actor' => 'observe',
        'client' => 'observe',
        'global' => 'enforce',
    ];

    /** @var array<string, int> */
    private const array RECIPE = [
        'attribute_filter' => 0,
        'base' => 1,
        'field' => 0,
        'highlight' => 0,
        'max_units' => 10000,
        'relation' => 0,
        'row' => 0,
        'side_effect' => 0,
    ];

    private ContractSnapshotSection $webhooks;

    /** @param iterable<ContractSnapshotSection> $sections */
    public function __construct(
        private OperationRegistry $operations,
        private OpenApiDocumentGenerator $openApi,
        private ApiProfile $profile,
        private ProblemRegistry $problems,
        private string $serverUrl,
        iterable $sections = [],
    ) {
        $webhooks = null;

        foreach ($sections as $section) {
            if ($section->file() !== 'webhooks.json' || $webhooks !== null) {
                throw new InvalidArgumentException('Snapshot requires exactly one webhooks.json contributor.');
            }

            $webhooks = $section;
        }

        $this->webhooks = $webhooks ?? throw new InvalidArgumentException('Missing webhooks.json contributor.');
    }

    /** @return array<string, string> */
    public function documents(): array
    {
        $major = 'v2';
        $operations = [];

        foreach ($this->operations->all($major) as $operation) {
            $operations[] = $this->operation($operation);
        }

        usort($operations, static fn (array $left, array $right): int => $left['id'] <=> $right['id']);
        $normalized = SnapshotJson::normalize($this->openApi->document(version: $major, serverUrl: $this->serverUrl));

        if (! is_array($normalized)) {
            throw new InvalidArgumentException('OpenAPI snapshot is not a document.');
        }

        $bodies = [
            'errors.json' => SnapshotJson::pretty(value: $this->errors()),
            'openapi.json' => SnapshotJson::pretty(value: $normalized),
            'operations.json' => SnapshotJson::pretty(value: $operations),
            'webhooks.json' => SnapshotJson::pretty(value: $this->webhooks->document(major: $major)),
        ];

        $bodies['manifest.json'] = SnapshotJson::pretty([
            'contract_fingerprint' => $this->profile->contractFingerprint,
            'files' => array_map(static fn (string $body): string => hash('sha256', $body), $bodies),
            'major' => $major,
            'registry_fingerprint' => $this->profile->registryFingerprint,
            'schema' => 'corex-api-snapshot/1',
        ]);

        return $bodies;
    }

    /** @return array<string, mixed> */
    private function operation(Operation $operation): array
    {
        return [
            'ability' => $operation->ability,
            'capabilities' => $operation->capabilities,
            'contract_hash' => $operation->contractHash,
            'cost' => $this->cost($operation->cost),
            'deprecation' => $operation->deprecation,
            'effect_mode' => $operation->effectMode,
            'execution_supported' => $operation->executionSupported,
            'id' => $operation->id,
            'idempotency' => $operation->idempotency->value,
            'method' => $operation->method,
            'mode' => $operation->mode->value,
            'module' => $operation->module,
            'pagination' => JsonDocuments::optionalMap($operation->pagination),
            'path' => $operation->path,
            'read_fields' => $operation->readFields,
            'request_schema' => JsonDocuments::map($operation->requestSchema),
            'required_scopes' => $operation->requiredScopes,
            'response_schema' => JsonDocuments::map($operation->responseSchema),
            'success_status' => $operation->successStatus,
            'version' => $operation->version,
            'write_fields' => $operation->writeFields,
        ];
    }

    /**
     * @param  array<string, int>|null  $declared
     * @return array{enforcement: array<string, string>, recipe: array<string, int>}
     */
    private function cost(?array $declared): array
    {
        $recipe = self::RECIPE;

        foreach ($declared ?? [] as $key => $value) {
            if (array_key_exists($key, $recipe)) {
                $recipe[$key] = $value;
            }
        }

        ksort($recipe);

        return ['enforcement' => self::ENFORCEMENT, 'recipe' => $recipe];
    }

    /** @return list<array{owner: string, code: string, category: string, detail_keys: list<string>, path: null, retryable: bool}> */
    private function errors(): array
    {
        $rows = [];
        foreach ($this->problems->all() as $definition) {
            $rows[] = [
                'category' => $definition->category->value,
                'code' => $definition->code->value(),
                'detail_keys' => $definition->detailKeys,
                'owner' => $this->problems->owner(code: $definition->code),
                'path' => null,
                'retryable' => $definition->retryable,
            ];
        }

        usort($rows, static fn (array $left, array $right): int => [$left['owner'], $left['code']] <=> [$right['owner'], $right['code']]);

        return $rows;
    }
}
