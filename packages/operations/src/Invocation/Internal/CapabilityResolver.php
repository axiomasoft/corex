<?php

declare(strict_types=1);

namespace CoreX\Operations\Invocation\Internal;

use CoreX\Modules\Contracts\ModuleRegistry;
use CoreX\Operations\Authorization\Contracts\OperationAuthorizer;
use CoreX\Operations\Authorization\Contracts\PrincipalResolver;
use CoreX\Operations\Errors\Exceptions\ApiError;
use CoreX\Operations\Invocation\Contracts\ContextResolver;
use CoreX\Operations\Invocation\Contracts\RequestContext;
use CoreX\Operations\Invocation\InvocationMetadata;
use CoreX\Operations\Laravel\Support\ApiOptions;
use CoreX\Operations\Versioning\Data\ApiProfile;
use CoreX\Operations\Versioning\Data\EffectiveCapability;
use CoreX\Operations\Versioning\Internal\ApiProfileFactory;
use Illuminate\Http\Request;

/** Live capability view. Payload tenant, workspace and actor fields are ignored. */
final class CapabilityResolver
{
    public function __construct(
        private readonly ModuleRegistry $modules,
        private readonly ApiOptions $options,
        private readonly ApiProfileFactory $profiles,
        private readonly PrincipalResolver $principals,
        private readonly ContextResolver $contexts,
        private readonly OperationAuthorizer $authorizer,
    ) {}

    public function fingerprint(): string
    {
        return $this->profile()->fingerprint();
    }

    /** @return list<array{id: string, enabled: bool, reason: ?string, limits: array<string, int|string>}> */
    public function effective(Request $request): array
    {
        $version = $request->route('version');
        $metadata = InvocationMetadata::fromRequest($request, is_string($version) ? $version : $this->options->defaultMajor);
        $principal = $this->principals->resolve($request)
            ?? throw new ApiError('authentication_required', 401, 'Authentication is required.');
        $context = $this->contexts->resolve($request, $principal, $metadata);
        $profile = $this->profile();
        $modules = [];

        foreach ($profile->operations as $operation) {
            $modules[$operation->module] = $this->modules->isActive($operation->module, $context->tenant());
        }

        $denied = [];

        foreach ($profile->capabilities as $capability) {
            if ($this->hidden($profile, $capability->operationIds, $context)) {
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
            $this->profiles->effective($profile, $modules, knownIds: $visible),
        );
    }

    private function profile(): ApiProfile
    {
        return $this->profiles->make($this->modules->compiled(), $this->options);
    }

    /** @param  list<string>  $operationIds */
    private function hidden(ApiProfile $profile, array $operationIds, RequestContext $context): bool
    {
        foreach ($profile->operations as $operation) {
            if (! in_array($operation->version.':'.$operation->id, $operationIds, true)) {
                continue;
            }

            if (! $this->authorizer->allows($operation, $context)) {
                return true;
            }
        }

        return false;
    }
}
