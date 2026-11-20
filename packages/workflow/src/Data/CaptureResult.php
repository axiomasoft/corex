<?php

declare(strict_types=1);

namespace CoreX\Workflow\Data;

use CoreX\Workflow\Enums\CaptureStatus;
use InvalidArgumentException;
use Packman\Attributes\Api;

#[Api(since: '1.0')]
final readonly class CaptureResult
{
    public function __construct(public CaptureStatus $status, public ?string $intentRef = null, public ?string $digest = null)
    {
        if ($status === CaptureStatus::Conflict ? $intentRef !== null || $digest !== null
            : $intentRef === null || $intentRef === '' || $digest === null || preg_match('/\A[a-f0-9]{64}\z/D', $digest) !== 1) {
            throw new InvalidArgumentException('Invalid workflow capture evidence.');
        }
    }
}
