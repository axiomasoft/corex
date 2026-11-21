<?php

declare(strict_types=1);

namespace CoreX\Tasks\Task\Data;

use CoreX\Tasks\Task\Enums\TaskCompletionAdmissionStatus;
use InvalidArgumentException;
use Packman\Attributes\Api;

/** Evidence confirmed by the capture owner on the supplied transaction. */
#[Api(since: '2.0.0')]
final readonly class TaskCompletionAdmission
{
    public function __construct(
        public TaskCompletionAdmissionStatus $status,
        public ?string $intentRef = null,
        public ?string $digest = null,
        public ?string $versionRef = null,
    ) {
        if ($status === TaskCompletionAdmissionStatus::Conflict
            ? $intentRef !== null || $digest !== null || $versionRef !== null
            : $intentRef === null || $intentRef === '' || $versionRef === null || $versionRef === ''
                || $digest === null || preg_match('/\A[a-f0-9]{64}\z/D', $digest) !== 1) {
            throw new InvalidArgumentException('Invalid task completion admission evidence.');
        }
    }
}
