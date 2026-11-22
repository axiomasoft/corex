<?php

declare(strict_types=1);

namespace CoreX\Search\Projection\Data;

use CoreX\Data\Data\FieldSelection;
use CoreX\Data\Enums\FieldUse;
use CoreX\Search\Projection\Contracts\ProjectionFilter;
use CoreX\Search\Projection\Enums\FieldType;
use InvalidArgumentException;
use Packman\Attributes\Api;

#[Api(since: '2.0.0')]
final readonly class ProjectionField
{
    /** @param list<FieldUse> $uses */
    public function __construct(
        public string $id,
        public FieldType $type,
        public array $uses,
        public ?string $physicalId = null,
        public ?string $authorizationField = null,
        public ?ProjectionFilter $filter = null,
        public bool $predicateRequiresRead = false,
    ) {
        new FieldSelection([$id]);
        ProjectionInput::objects(items: $uses, class: FieldUse::class, max: 7);

        if ($physicalId !== null && preg_match('/\A[a-z][a-z0-9_]{0,62}\z/D', $physicalId) !== 1) {
            throw new InvalidArgumentException('A physical projection field requires a backend-safe identifier.');
        }

        if ($authorizationField !== null) {
            new FieldSelection([$authorizationField]);
        }
    }
}
