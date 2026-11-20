<?php

declare(strict_types=1);

namespace CoreX\Search\Query\Data;

use CoreX\Data\Data\RecordRef;
use CoreX\Search\Index\Data\IndexTarget;
use CoreX\Search\Projection\Data\FieldWitnesses;
use Packman\Attributes\Api;

#[Api(since: '2.0.0')]
final readonly class Candidate
{
    public function __construct(public IndexTarget $generation, public RecordRef $record, public string $documentId, public ?FieldWitnesses $textWitnesses = null) {}
}
