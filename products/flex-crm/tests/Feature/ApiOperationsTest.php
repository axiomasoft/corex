<?php

declare(strict_types=1);

use CoreX\Config\Data\CoreOptions;
use CoreX\Context\Data\TenantContext;
use CoreX\Database\Enums\IdStrategy;
use CoreX\Modules\Contracts\ModuleRegistry;
use CoreX\Modules\Internal\Compiler\RegistryCompiler;
use CoreX\Operations\Authorization\Contracts\OperationAuthorizer;
use CoreX\Operations\Authorization\Contracts\Principal;
use CoreX\Operations\Authorization\Contracts\PrincipalResolver;
use CoreX\Operations\Definition\Data\Operation;
use CoreX\Operations\Invocation\Contracts\ContextResolver;
use CoreX\Operations\Invocation\Contracts\RequestContext;
use CoreX\Operations\Invocation\Data\InvocationMetadata;
use CoreX\Operations\Invocation\Internal\OperationDispatcher;
use CoreX\Operations\Invocation\Laravel\ResolvedContexts;
use Flex\Crm\ApiShared\CrmIdentifier;
use Flex\Crm\CrmModule;
use Flex\Crm\Tests\Support\TestContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;

it('declares and dispatches company, contact and association operations through the platform boundary', function (): void {
    $compiled = app(RegistryCompiler::class)->compile(manifests: [(new CrmModule)->manifest()], installedPackages: []);
    $registry = Mockery::mock(ModuleRegistry::class);
    $registry->shouldReceive('compiled')->andReturn($compiled);
    $registry->shouldReceive('isActive')->with('flex/crm', Mockery::type(TenantContext::class))->andReturn(true);
    app()->instance(ModuleRegistry::class, $registry);

    $principal = new class implements Principal
    {
        public function id(): string
        {
            return 'trusted-user';
        }
    };
    app()->instance(PrincipalResolver::class, new class($principal) implements PrincipalResolver
    {
        public function __construct(private readonly Principal $principal) {}

        public function resolve(Request $request): ?Principal
        {
            return $this->principal;
        }
    });
    app()->instance(ContextResolver::class, new class($this->crmContext, $principal) implements ContextResolver
    {
        public function __construct(
            private readonly TestContext $context,
            private readonly Principal $principal,
        ) {}

        public function resolve(Request $request, Principal $principal, InvocationMetadata $metadata): RequestContext
        {
            return ResolvedContexts::make($this->context->current()->tenant, $this->principal, $metadata);
        }
    });
    app()->instance(OperationAuthorizer::class, new class implements OperationAuthorizer
    {
        public function allows(Operation $operation, RequestContext $context): bool
        {
            return str_starts_with($operation->ability, 'crm.');
        }
    });

    $dispatch = function (string $method, string $path, array $input = []): JsonResponse {
        $request = Request::create(
            uri: '/api/v1/'.$path,
            method: $method,
            parameters: $method === 'GET' ? $input : [],
            server: ['CONTENT_TYPE' => 'application/json'],
            content: $method === 'GET' ? null : json_encode($input, JSON_THROW_ON_ERROR),
        );

        return app(OperationDispatcher::class)->dispatch(request: $request, version: 'v1', path: $path);
    };

    $company = $dispatch('POST', 'companies', ['name' => 'Acme', 'inn' => '7700000000']);
    expect($company->getStatusCode())->toBe(201)
        ->and($company->getData(true)['data']['name'])->toBe('Acme');
    $companyId = $company->getData(true)['data']['id'];

    foreach ([0, 1, '0', '1'] as $notBoolean) {
        expect($dispatch('POST', 'companies', ['name' => 'Invalid flag', 'account_level' => $notBoolean])->getStatusCode())->toBe(422);
    }

    $contact = $dispatch('POST', 'contacts', ['name_first' => 'Jane', 'emails' => [['value' => 'jane@acme.test']]]);
    expect($contact->getStatusCode())->toBe(201)
        ->and($contact->getData(true)['data']['display_name'])->toBe('Jane');
    $contactId = $contact->getData(true)['data']['id'];

    expect($dispatch('POST', 'contacts/'.$contactId.'/companies', ['company_id' => $companyId, 'is_primary' => 1])->getStatusCode())->toBe(422);

    $association = $dispatch('POST', 'contacts/'.$contactId.'/companies', ['company_id' => $companyId, 'is_primary' => true]);
    expect($association->getStatusCode())->toBe(201)
        ->and($association->getData(true)['data']['is_primary'])->toBeTrue();
    $companies = $dispatch('GET', 'companies');
    expect($companies->getStatusCode())->toBe(200)
        ->and($companies->getData(true)['data']['items'])->toHaveCount(1);
    $contacts = $dispatch('GET', 'contacts', ['limit' => 1]);
    expect($contacts->getStatusCode())->toBe(200)
        ->and($contacts->getData(true)['data']['items'])->toHaveCount(1)
        ->and($contacts->getData(true)['data']['items'][0]['id'])->toBe($contactId);
    $shownContact = $dispatch('GET', 'contacts/'.$contactId);
    expect($shownContact->getStatusCode())->toBe(200)
        ->and($shownContact->getData(true)['data']['id'])->toBe($contactId);
    $forgedOwner = $dispatch('POST', 'companies', ['name' => 'Hidden', 'owner_id' => 'forged']);
    expect($forgedOwner->getStatusCode())->toBe(403)
        ->and($forgedOwner->getData(true)['code'])->toBe('operations.forbidden');
    $undeclared = $dispatch('POST', 'companies', ['name' => 'Hidden', 'actor' => 'forged']);
    expect($undeclared->getStatusCode())->toBe(422)
        ->and($undeclared->getData(true)['code'])->toBe('operations.undeclared_fields');
});

it('normalizes CRM identifiers for the configured ID strategy', function (): void {
    config(['corex.ids.strategy' => IdStrategy::Int]);
    app()->forgetInstance(CoreOptions::class);

    expect(CrmIdentifier::fromPath('7'))->toBe(7)
        ->and(CrmIdentifier::fromInput(8))->toBe(8)
        ->and(fn (): int|string => CrmIdentifier::fromPath('0'))->toThrow(ValidationException::class);
});
