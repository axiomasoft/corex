<?php

declare(strict_types=1);

namespace CoreX\Tasks\Planning\Data;

use CoreX\Data\Data\DataScope;
use CoreX\Data\Data\ScopeIdentity;
use InvalidArgumentException;
use Packman\Attributes\Api;

/** The request namespace and subject, with the current scope used for admission. */
#[Api(since: '2.0.0')]
final readonly class TaskOperationIdentity
{
    public function __construct(
        public DataScope $scope,
        public TaskOperationKind $kind,
        public string $requestKey,
        public string $subjectRef,
        public ?string $workspaceId = null,
    ) {
        if ($requestKey === '' || strlen($requestKey) > 190 || str_contains($requestKey, "\0")
            || $subjectRef === '' || strlen($subjectRef) > 190 || str_contains($subjectRef, "\0")) {
            throw new InvalidArgumentException('Invalid task operation request key or subject reference.');
        }

        if ($kind === TaskOperationKind::CompletionCapture) {
            if ($workspaceId !== null) {
                throw new InvalidArgumentException('A task lifetime operation has account scope.');
            }
        } elseif ($scope->workspaceId !== null && $scope->workspaceId !== $workspaceId) {
            throw new InvalidArgumentException('Task operation workspace differs from the current scope.');
        }

        if ($workspaceId !== null) {
            ScopeIdentity::opaque($workspaceId);
        }
    }
}
