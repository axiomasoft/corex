<?php

declare(strict_types=1);

namespace CoreX\ImportExport\Data;

use Packman\Attributes\Api;

#[Api(since: '1.0')]
final readonly class StepToken
{
    public function __construct(public string $checkpointSequence, public string $epoch, public string $sourceHash, public string $mappingHash, public string $schemaHash, public string $restoreEpoch, public ?string $stepKey = null)
    {
        Validation::unsignedInteger(value: $checkpointSequence, name: 'checkpoint sequence');
        Validation::unsignedInteger(value: $epoch, name: 'claim epoch');
        Validation::sha256(value: $sourceHash, name: 'source hash');
        Validation::sha256(value: $mappingHash, name: 'mapping hash');
        Validation::sha256(value: $schemaHash, name: 'schema hash');
        Validation::sha256(value: $restoreEpoch, name: 'restore epoch');

        if ($stepKey !== null) {
            Validation::sha256(value: $stepKey, name: 'step key');
        }
    }
}
