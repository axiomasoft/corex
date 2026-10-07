<?php

declare(strict_types=1);

namespace CoreX\Tools\Internal;

use CoreX\Data\Laravel\JsonDocuments;
use CoreX\Modules\Contracts\ModuleRegistry;
use CoreX\Operations\Errors\Enums\OperationsErrorCode;
use CoreX\Operations\Errors\Exceptions\ApiError;
use CoreX\Operations\Invocation\Contracts\RequestContext;
use CoreX\Tools\Contracts\ToolCatalogue;
use CoreX\Tools\Contracts\ToolPolicy;
use CoreX\Tools\Data\ToolCatalogueView;
use CoreX\Tools\Data\ToolDescriptor;
use Override;

/** Fresh current-policy catalogue projection over the compiled API slice. */
final readonly class CompiledToolCatalogue implements ToolCatalogue
{
    public const int MAX_DESCRIPTORS = 1000;

    public function __construct(
        private ModuleRegistry $modules,
        private ToolPolicy $policy,
    ) {}

    #[Override]
    public function discover(RequestContext $context): ToolCatalogueView
    {
        $descriptors = [];

        foreach ($this->modules->compiled()->api as $api) {
            if ($api->mode !== 'tool' || $api->declaringModule === null) {
                continue;
            }

            if (! $this->modules->isActive(name: $api->declaringModule, ctx: $context->tenant())) {
                continue;
            }

            $descriptor = ToolDescriptorFactory::fromDeclaration($api);

            if ($this->isDeniedByAvailability($descriptor)) {
                continue;
            }

            if (! $this->policy->discover(tool: $descriptor, context: $context)) {
                continue;
            }

            $descriptors[] = $descriptor;
        }

        usort(
            $descriptors,
            static fn (ToolDescriptor $left, ToolDescriptor $right): int => [$left->module, $left->code, $left->toolVersion]
                <=> [$right->module, $right->code, $right->toolVersion],
        );

        if (count($descriptors) > self::MAX_DESCRIPTORS) {
            throw new ApiError(errorCode: OperationsErrorCode::CatalogueLimit, status: 403, message: 'Catalogue descriptor limit exceeded.');
        }

        return new ToolCatalogueView(
            revision: self::revision($descriptors),
            descriptors: $descriptors,
        );
    }

    /**
     * @param  list<ToolDescriptor>  $descriptors
     */
    public static function revision(array $descriptors): string
    {
        $hashes = array_map(static fn (ToolDescriptor $descriptor): string => $descriptor->contractHash, $descriptors);
        sort($hashes);

        return hash('sha256', implode('', $hashes));
    }

    private function isDeniedByAvailability(ToolDescriptor $tool): bool
    {
        return array_key_exists('discover', JsonDocuments::map($tool->availability))
            && JsonDocuments::map($tool->availability)['discover'] === false;
    }
}
