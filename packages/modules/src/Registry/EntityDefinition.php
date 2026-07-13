<?php

declare(strict_types=1);

namespace Flex\Modules\Registry;

use Flex\Modules\Enums\Capability;

/**
 * Immutable description of a registered entity (B-10 §3.4). Handle is the
 * canonical morph key ({module_short}.{entity}, e.g. 'crm.deal') and drives
 * the morph map, satellite hookup and auto-CRUD/search/admin generation.
 */
final readonly class EntityDefinition
{
    /**
     * @param  class-string  $model
     * @param  list<Capability>  $capabilities
     * @param  list<FieldDefinition>  $fields
     */
    public function __construct(
        public string $handle,
        public string $model,
        public string $table,
        public string $module,
        public string $labelSingular,
        public string $labelPlural,
        public array $capabilities,
        public array $fields,
        public ?string $titleAttribute,
    ) {}
}
