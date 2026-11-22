<?php

declare(strict_types=1);

namespace CoreX\Operations\Invocation\Laravel;

use CoreX\Invocation\Data\InvocationMetadata;
use CoreX\Operations\Authorization\Contracts\PrincipalResolver;
use CoreX\Operations\Definition\Data\Operation;
use CoreX\Operations\Errors\Enums\OperationsErrorCode;
use CoreX\Operations\Errors\Exceptions\ApiError;
use CoreX\Operations\Invocation\Contracts\ClientRequestContext;
use CoreX\Operations\Invocation\Contracts\ContextResolver;
use CoreX\Operations\Invocation\Internal\AuthenticatedRequestContext;
use Illuminate\Http\Request;
use Packman\Attributes\Spi;
use Packman\Attributes\SpiKind;

#[Spi(kind: SpiKind::Call, since: '2.0.0')]
final readonly class AuthenticatedOperationContext
{
    public function __construct(private PrincipalResolver $principals, private ContextResolver $contexts) {}

    /** Run after the host has authenticated the principal and verified its client. */
    public function resolve(Request $request, InvocationMetadata $metadata): ClientRequestContext
    {
        $principal = $this->principals->resolve($request);
        $client = $request->attributes->get('corex.client_id');

        if ($principal === null || ! is_string($client) || preg_match('/\A[^\x00-\x20\x7f]{1,191}\z/D', $client) !== 1) {
            throw new ApiError(errorCode: OperationsErrorCode::AuthenticationRequired, status: 401, message: 'Authentication is required.');
        }

        return new AuthenticatedRequestContext(
            context: $this->contexts->resolve(request: $request, principal: $principal, metadata: $metadata),
            client: $client,
        );
    }

    public static function forOperation(ClientRequestContext $context, Operation $operation): ClientRequestContext
    {
        $metadata = $context->metadata();

        return new AuthenticatedRequestContext(
            context: $context,
            client: $context->clientId(),
            invocation: new InvocationMetadata(
                requestId: $metadata->requestId, version: $operation->version,
                operationId: $operation->id, method: $operation->method,
                correlationId: $metadata->correlationId, accept: $metadata->accept,
            ),
        );
    }
}
