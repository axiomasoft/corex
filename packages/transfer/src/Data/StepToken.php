<?php

declare(strict_types=1);

namespace CoreX\Transfer\Data;

final readonly class StepToken
{
    public function __construct(public string $checkpointSequence, public string $epoch, public string $sourceHash, public string $mappingHash, public string $schemaHash, public string $restoreEpoch)
    {
        Validation::unsignedInteger(value: $checkpointSequence, name: 'checkpoint sequence');
        Validation::unsignedInteger(value: $epoch, name: 'claim epoch');
        Validation::sha256(value: $sourceHash, name: 'source hash');
        Validation::sha256(value: $mappingHash, name: 'mapping hash');
        Validation::sha256(value: $schemaHash, name: 'schema hash');
        Validation::sha256(value: $restoreEpoch, name: 'restore epoch');
    }
}
