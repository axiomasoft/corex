<?php

declare(strict_types=1);

namespace CoreX\ImportExport\Data;

use CoreX\Data\Data\RecordRef;
use InvalidArgumentException;
use Packman\Attributes\Api;

#[Api(since: '1.0')]
final readonly class TargetResult
{
    public function __construct(public string $status, public ?RecordRef $target, public ?string $recordVersion, public ?string $providerTask, public ?string $errorCode)
    {
        if (! in_array($status, ['applied', 'updated', 'noop', 'invalid', 'conflict', 'unknown', 'unsupported', 'absent-with-proof'], strict: true)) {
            throw new InvalidArgumentException('Unknown target result.');
        }

        if ($status === 'absent-with-proof' && ($target !== null || $recordVersion !== null || $providerTask !== null || $errorCode !== null)) {
            throw new InvalidArgumentException('Proved absence cannot carry a target receipt.');
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
