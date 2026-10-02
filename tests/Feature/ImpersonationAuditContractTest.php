<?php

declare(strict_types=1);

use CoreX\Audit\Contracts\AuditLogger;
use CoreX\Audit\Data\AuditEntry;
use CoreX\Audit\Enums\ActorType;
use CoreX\Audit\Internal\QueuedAuditLogger;
use CoreX\Audit\Internal\RecordAuditEntry;
use CoreX\Config\Data\CoreOptions;
use CoreX\Context\Contracts\ImpersonationService;
use CoreX\Context\Contracts\TenantContextResolver;
use CoreX\Context\Data\AccountRef;
use CoreX\Context\Data\ImpersonationState;
use CoreX\Context\Data\TenantContext;
use CoreX\Context\Data\WorkspaceRef;
use CoreX\Data\Laravel\JsonDocuments;
use Illuminate\Support\Facades\Queue;

test('P12 stamps a direct AuditLogger call with authoritative impersonation provenance', function (): void {
    Queue::fake();
    config(['corex.audit.enabled' => true]);
    app()->forgetInstance(CoreOptions::class);

    $state = new ImpersonationState('grant-p12', 'staff-p12', 'target-p12', new DateTimeImmutable('+1 hour'), 'support case 42');
    app()->instance(ImpersonationService::class, new P12FixedImpersonationService($state));
    app()->instance(AuditLogger::class, new QueuedAuditLogger(tenantContext: new P12FixedTenantContextResolver('workspace-p12')));

    app(AuditLogger::class)->record(new AuditEntry(
        action: 'direct-contract-proof',
        actorType: ActorType::User,
        actorId: 'spoofed-user',
        context: JsonDocuments::capture(['request' => 'request-p12', 'impersonation' => ['grant' => 'spoofed']]),
    ));

    Queue::assertPushed(RecordAuditEntry::class, function (RecordAuditEntry $job): bool {
        $property = new ReflectionProperty($job, 'entry');
        $entry = $property->getValue($job);

        expect($entry)->toBeInstanceOf(AuditEntry::class)
            ->and($entry->actorType)->toBe(ActorType::Support)
            ->and($entry->actorId)->toBe('staff-p12')
            ->and($entry->workspaceId)->toBe('workspace-p12')
            ->and($entry->occurredAt)->not->toBeNull()
            ->and(JsonDocuments::map($entry->context))->toBe([
                'request' => 'request-p12',
                'impersonation' => [
                    'grant' => 'grant-p12',
                    'target_user_id' => 'target-p12',
                    'reason' => 'support case 42',
                ],
            ]);

        return true;
    });
});

test('P12 direct audit ratchet rejects bypassing the impersonation stamp', function (): void {
    $path = dirname(__DIR__, 2).'/packages/core/src/Audit/Internal/QueuedAuditLogger.php';
    $original = (string) file_get_contents($path);
    $changed = str_replace('$entry = $entry->withImpersonation($state);', '$entry = $entry;', $original);
    file_put_contents($path, $changed);

    try {
        expect(p12ImpersonationAuditViolations())->toContain('I-DIRECT-AUDIT-BYPASS QueuedAuditLogger::record');
    } finally {
        file_put_contents($path, $original);
    }
});

/** @return list<string> */
function p12ImpersonationAuditViolations(): array
{
    $path = dirname(__DIR__, 2).'/packages/core/src/Audit/Internal/QueuedAuditLogger.php';
    $tokens = token_get_all((string) file_get_contents($path));
    $stamp = null;
    $dispatch = null;

    foreach ($tokens as $index => $token) {
        if (! is_array($token) || $token[0] !== T_STRING) {
            continue;
        }

        if ($token[1] === 'withImpersonation') {
            $stamp = $index;
        } elseif ($token[1] === 'dispatch') {
            $dispatch = $index;
        }
    }

    return $stamp === null || $dispatch === null || $stamp > $dispatch
        ? ['I-DIRECT-AUDIT-BYPASS QueuedAuditLogger::record']
        : [];
}

final class P12FixedImpersonationService implements ImpersonationService
{
    public function __construct(private readonly ImpersonationState $fixed) {}

    public function start(string $grantId, string $staffIdentityId): void {}

    public function stop(): void {}

    public function active(): ?string
    {
        return $this->fixed->grantId;
    }

    public function state(): ?ImpersonationState
    {
        return $this->fixed;
    }
}

final class P12FixedTenantContextResolver implements TenantContextResolver
{
    public function __construct(private readonly string $workspaceId) {}

    public function current(): TenantContext
    {
        return new TenantContext(
            new AccountRef('account-p12', 'account', 'active', [], []),
            new WorkspaceRef($this->workspaceId, 'main', 'standard', 'main', null),
        );
    }
}
