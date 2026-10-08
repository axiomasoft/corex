<?php

declare(strict_types=1);

namespace CoreX\Operations\Invocation\Internal;

use CoreX\Invocation\Contracts\RequestContext;
use CoreX\Modules\Contracts\ModuleRegistry;
use CoreX\Operations\Authorization\Contracts\OperationAuthorizer;
use CoreX\Operations\Authorization\Contracts\PrincipalResolver;
use CoreX\Operations\Errors\Enums\OperationsErrorCode;
use CoreX\Operations\Errors\Exceptions\ApiError;
use CoreX\Operations\Invocation\Contracts\ContextResolver;
use CoreX\Operations\Invocation\Contracts\EffectiveCapabilities;
use CoreX\Operations\Invocation\Laravel\InvocationMetadataFactory;
use CoreX\Operations\Laravel\Support\ApiOptions;
use CoreX\Operations\Versioning\Contracts\ApiProfiles as ApiProfileFactory;
use CoreX\Operations\Versioning\Data\ApiProfile;
use CoreX\Operations\Versioning\Data\EffectiveCapability;
use Illuminate\Http\Request;

/** Live capability view. Payload tenant, workspace and actor fields are ignored. */
final readonly class CapabilityResolver implements EffectiveCapabilities
{
    public function __construct(
        private ModuleRegistry $modules,
        private ApiOptions $options,
        private ApiProfileFactory $profiles,
        private PrincipalResolver $principals,
        private ContextResolver $contexts,
        private OperationAuthorizer $authorizer,
    ) {}

    public function fingerprint(): string
    {
        return $this->profile()->fingerprint();
    }

    /** @return list<array{id: string, enabled: bool, reason: ?string, limits: array<string, int|string>}> */
    public function effective(Request $request): array
    {
        $version = $request->route('version');
        $metadata = InvocationMetadataFactory::fromRequest(request: $request, version: is_string($version) ? $version : $this->options->defaultMajor);
        $principal = $this->principals->resolve($request)
            ?? throw new ApiError(errorCode: OperationsErrorCode::AuthenticationRequired, status: 401, message: 'Authentication is required.');
        $context = $this->contexts->resolve(request: $request, principal: $principal, metadata: $metadata);
        $profile = $this->profile();
        $modules = [];

        foreach ($profile->operations as $operation) {
            $modules[$operation->module] = $this->modules->isActive(name: $operation->module, ctx: $context->tenant());
        }

        $denied = [];

        foreach ($profile->capabilities as $capability) {
            if ($this->hidden(profile: $profile, operationIds: $capability->operationIds, context: $context)) {
                $denied[] = $capability->id;
            }
        }

        $visible = [];

        foreach ($profile->capabilities as $capability) {
            if (! in_array($capability->id, $denied, true)) {
                $visible[] = $capability->id;
            }
        }

        return array_map(
            static fn (EffectiveCapability $row): array => $row->publicMap(),
            $this->profiles->effective(profile: $profile, moduleAvailable: $modules, knownIds: $visible),
        );
    }

    private function profile(): ApiProfile
    {
        return $this->profiles->make(registry: $this->modules->compiled(), options: $this->options);
    }

    /** @param  list<string>  $operationIds */
    private function hidden(ApiProfile $profile, array $operationIds, RequestContext $context): bool
    {
        foreach ($profile->operations as $operation) {
            if (! in_array($operation->version.':'.$operation->id, $operationIds, true)) {
                continue;
            }

            if (! $this->authorizer->allows(operation: $operation, context: $context)) {
                return true;
            }
        }

        return false;
    }
}
