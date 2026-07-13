<?php

declare(strict_types=1);

namespace CoreX\Contracts;

use CoreX\Enums\ActorType;

/**
 * One append-only audit record (B-10 §3.1). Built by the caller (HasAuditLog
 * trait or CoreX\Audit\AuditObserver) and handed to AuditLogger::record();
 * the id/occurred_at/workspace_id columns are filled at write time, not here.
 */
final readonly class AuditEntry
{
    /**
     * @param  array<string, array{old: mixed, new: mixed}>|null  $changes
     * @param  array<string, mixed>  $context
     */
    public function __construct(
        public string $action,
        public ActorType $actorType,
        public ?string $actorId = null,
        public ?string $entityType = null,
        public ?string $entityId = null,
        public ?array $changes = null,
        public array $context = [],
    ) {}
}
