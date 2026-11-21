<?php

declare(strict_types=1);

namespace CoreX\Tasks\Task\Data;

use CoreX\Context\Data\TenantContext;
use CoreX\Data\Data\DataScope;
use CoreX\Tasks\Task\Enums\TaskStatus;
use Packman\Attributes\Api;

#[Api(since: '1.0')]
final readonly class TaskCompletionCandidate
{
    public function __construct(
        public TenantContext $context,
        public DataScope $scope,
        public string $taskId,
        public TaskStatus $previousStatus,
        public TaskStatus $status,
        public string $requestKey,
        public ActorData $actor,
    ) {}
}
