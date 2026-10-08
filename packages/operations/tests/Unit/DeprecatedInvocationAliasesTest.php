<?php

declare(strict_types=1);

use CoreX\Context\Contracts\Principal;
use CoreX\Context\Data\AccountRef;
use CoreX\Context\Data\TenantContext;
use CoreX\Context\Enums\PrincipalType;
use CoreX\Invocation\Contracts\OperationHandler;
use CoreX\Invocation\Contracts\RequestContext;
use CoreX\Invocation\Data\InvocationMetadata;
use CoreX\Operations\Definition\Contracts\OperationHandler as DeprecatedOperationHandler;
use CoreX\Operations\Invocation\Contracts\RequestContext as DeprecatedRequestContext;

// Audit 2026-10-08 P-02: the handler contract moved to CoreX\Invocation; the old
// FQCNs stay as class_alias names of the SAME types until 2.0.
it('keeps the deprecated operations names as aliases of the kernel invocation contract', function (): void {
    $aliases = [
        DeprecatedOperationHandler::class => OperationHandler::class,
        DeprecatedRequestContext::class => RequestContext::class,
        CoreX\Operations\Authorization\Contracts\Principal::class => Principal::class,
        CoreX\Operations\Invocation\Data\InvocationMetadata::class => InvocationMetadata::class,
    ];

    foreach ($aliases as $deprecated => $kernel) {
        expect(new ReflectionClass($deprecated)->getName())->toBe($kernel);
    }
});

it('accepts a handler written against the deprecated names wherever the kernel contract is expected', function (): void {
    $handler = new class implements DeprecatedOperationHandler
    {
        public function handle(DeprecatedRequestContext $context, array $input, array $parameters): array
        {
            return ['tenant' => $context->tenant()->account->id, 'principal' => $context->principal()->principalId()];
        }
    };
    $context = new class implements RequestContext
    {
        public function tenant(): TenantContext
        {
            return new TenantContext(new AccountRef('account-1', 'account-1', 'active', [], []));
        }

        public function principal(): Principal
        {
            return new class implements Principal
            {
                public function principalType(): PrincipalType
                {
                    return PrincipalType::User;
                }

                public function principalId(): string
                {
                    return 'user-1';
                }
            };
        }

        public function metadata(): InvocationMetadata
        {
            return new InvocationMetadata('req-1', 'v1', 'op.test', 'GET');
        }
    };

    $dispatch = static fn (OperationHandler $kernelHandler): array => $kernelHandler->handle($context, [], []);

    expect($dispatch($handler))->toBe(['tenant' => 'account-1', 'principal' => 'user-1']);
});
