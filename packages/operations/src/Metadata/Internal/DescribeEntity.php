<?php

declare(strict_types=1);

namespace CoreX\Operations\Metadata\Internal;

use CoreX\Context\Contracts\CurrentWorkScope;
use CoreX\Context\Contracts\DataScopeFactory;
use CoreX\Data\Data\EntityHandle;
use CoreX\Data\Laravel\JsonDocuments;
use CoreX\Invocation\Contracts\RequestContext;
use CoreX\Operations\Errors\Enums\OperationsErrorCode;
use CoreX\Operations\Errors\Exceptions\ApiError;
use CoreX\Operations\Metadata\Contracts\EntityMetadata;
use CoreX\Operations\Metadata\Contracts\MetadataOperation;
use CoreX\Operations\Metadata\Data\MetadataView;
use Illuminate\Contracts\Container\Container;
use InvalidArgumentException;
use Packman\Attributes\Internal;

#[Internal]
final readonly class DescribeEntity implements MetadataOperation
{
    public function __construct(private Container $container, private DataScopeFactory $scopes, private CurrentWorkScope $current) {}

    /** @param array<string, string> $parameters */
    public function describe(RequestContext $context, array $parameters): MetadataView
    {
        $id = $context->metadata()->operationId;

        if (! in_array($id, ['fields.describe', 'search.describe'], true) || ! $this->container->bound($id)) {
            throw new ApiError(errorCode: OperationsErrorCode::CapabilityUnavailable, status: 503, message: 'Metadata capability is unavailable.');
        }

        $reader = $this->container->make($id);

        if (! $reader instanceof EntityMetadata) {
            throw new ApiError(errorCode: OperationsErrorCode::CapabilityUnavailable, status: 503, message: 'Metadata capability is unavailable.');
        }

        try {
            $entity = new EntityHandle($parameters['entity'] ?? '');
        } catch (InvalidArgumentException) {
            throw new ApiError(errorCode: OperationsErrorCode::InvalidRequest, status: 422, message: 'Invalid entity parameter.', path: '/entity');
        }
        $scope = $this->scopes->forCurrentPrincipal($context->tenant());
        $principal = $context->principal();

        if ($scope->principalId !== $principal->principalId() || $scope->principalKind !== $principal->principalType()->value) {
            throw new ApiError(errorCode: OperationsErrorCode::AccessDenied, status: 403, message: 'The invocation scope is not authorized.');
        }

        $this->current->assert($scope);
        $view = $reader->describe(scope: $scope, entity: $entity);
        $this->current->assert($scope);

        return new MetadataView(body: $view->body, witness: hash('sha256', json_encode([$scope, $view->witness], JSON_THROW_ON_ERROR)));
    }

    public function handle(RequestContext $context, array $input, array $parameters): array
    {
        return JsonDocuments::map($this->describe(context: $context, parameters: $parameters)->body);
    }
}
