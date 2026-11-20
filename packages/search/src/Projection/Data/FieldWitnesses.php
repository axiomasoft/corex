<?php

declare(strict_types=1);

namespace CoreX\Search\Projection\Data;

use Packman\Attributes\Api;

#[Api(since: '2.0.0')]
final readonly class FieldWitnesses
{
    /** @param array<string,string> $digests */
    public function __construct(public array $digests)
    {
        new ProjectionSelection(array_keys($digests));
        foreach ($digests as $digest) {
            ProjectionInput::digest($digest);
        }
    }

    public static function capture(ProjectedValues $values, ProjectionSelection $selection): self
    {
        $digests = [];
        foreach ($selection->fields as $field) {
            $digests[$field] = $values->fieldWitness($field);
        }

        return new self($digests);
    }

    public function matches(ProjectedValues $values, ProjectionSelection $selection): bool
    {
        if (count($selection->fields) !== count($this->digests)) {
            return false;
        }
        foreach ($selection->fields as $field) {
            if (! isset($this->digests[$field]) || ! hash_equals($this->digests[$field], $values->fieldWitness($field))) {
                return false;
            }
        }

        return true;
    }
}
