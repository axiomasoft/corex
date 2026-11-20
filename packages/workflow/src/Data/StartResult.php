<?php

declare(strict_types=1);

namespace CoreX\Workflow\Data;

use CoreX\Workflow\Enums\StartOutcome;
use InvalidArgumentException;
use Packman\Attributes\Api;

#[Api(since: '1.0')]
final readonly class StartResult
{
    public function __construct(public StartOutcome $status, public ?string $instanceRef = null, public ?string $errorCode = null)
    {
        $hasInstance = $status === StartOutcome::Accepted || $status === StartOutcome::Confirmed;

        if ($hasInstance !== ($instanceRef !== null)
            || ($instanceRef !== null && preg_match('/\A[^\x00-\x20\x7f]{1,255}\z/D', $instanceRef) !== 1)
            || ($errorCode !== null && preg_match('/\A[a-zA-Z0-9._:-]{1,128}\z/D', $errorCode) !== 1)
            || ($status === StartOutcome::AbsentWithProof && $errorCode === null)) {
            throw new InvalidArgumentException('Invalid workflow engine evidence.');
        }
    }
}
