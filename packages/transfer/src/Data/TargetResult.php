<?php

declare(strict_types=1);

namespace CoreX\Transfer\Data;

use CoreX\Data\Data\RecordRef;
use InvalidArgumentException;

final readonly class TargetResult
{
    public function __construct(public string $status, public ?RecordRef $target, public ?string $recordVersion, public ?string $providerTask, public ?string $errorCode)
    {
        if (! in_array($status, ['applied', 'updated', 'noop', 'invalid', 'conflict', 'unknown', 'unsupported'], strict: true)) {
            throw new InvalidArgumentException('Unknown target result.');
        }
        foreach (['record version' => $recordVersion, 'provider task' => $providerTask, 'target error code' => $errorCode] as $name => $value) {
            if ($value !== null) {
                Validation::opaque(value: $value, name: $name);
            }
        }

        if (in_array($status, ['applied', 'updated', 'noop'], strict: true) && ($target === null || $recordVersion === null)) {
            throw new InvalidArgumentException('Successful target result requires record identity and version.');
        }
    }
}
