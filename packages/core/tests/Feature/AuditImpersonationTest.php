<?php

declare(strict_types=1);

use CoreX\Audit\Contracts\AuditLogger;
use CoreX\Audit\Data\AuditEntry;
use CoreX\Audit\Enums\ActorType;
use CoreX\Audit\Internal\AuditObserver;
use CoreX\Audit\Internal\QueuedAuditLogger;
use CoreX\Audit\Internal\RecordAuditEntry;
use CoreX\Config\Data\CoreOptions;
use CoreX\Context\Contracts\ImpersonationService;
use CoreX\Context\Contracts\TenantContextResolver;
use CoreX\Context\Data\AccountRef;
use CoreX\Context\Data\ImpersonationState;
use CoreX\Context\Data\WorkspaceRef;
use CoreX\Context\Internal\NullImpersonationService;
use CoreX\Data\Laravel\JsonDocuments;
use CoreX\Tests\Support\LocalWorkContexts;
use CoreX\Tests\Support\WorkScopeFixture;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Auth\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;

uses()->group('pg');

beforeEach(function (): void {
    config(['database.default' => 'pgsql', 'corex.audit.enabled' => true, 'queue.default' => 'sync', 'queue.connections.sync.after_commit' => false]);
    app()->forgetInstance(CoreOptions::class);
    DB::statement('DROP SCHEMA IF EXISTS public CASCADE');
    DB::statement('CREATE SCHEMA public');
    $this->migrateSchemaSets(['axiomasoft/corex:tenant'], DB::getDefaultConnection());
    app()->instance(ImpersonationService::class, new NullImpersonationService);
});

it('stamps observer and direct logger entries from authority and keeps unrelated context', function (): void {
    $state = impersonationState('because');
    $tenant = fixedTenant(Str::uuid7()->toString());
    app()->instance(ImpersonationService::class, new FixedImpersonationService($state));
    app()->instance(AuditLogger::class, app()->make(QueuedAuditLogger::class, ['tenantContext' => $tenant]));

    resolve(AuditObserver::class)->record(auditSubject((string) Str::uuid7()), 'updated');
    (app()->make(QueuedAuditLogger::class, ['tenantContext' => $tenant]))->record(new AuditEntry(
        action: 'direct',
        actorType: ActorType::User,
        actorId: 'caller',
        context: JsonDocuments::capture(['ip' => '10.0.0.8', 'impersonation' => ['grant' => 'spoof', 'reason' => 'lie']]),
    ));

    $rows = DB::table('sys_audit_log')->orderBy('action')->get();
    expect($rows)->toHaveCount(2);

    foreach ($rows as $row) {
        expect($row->actor_type)->toBe('support')
            ->and($row->actor_id)->toBe($state->staffIdentityId)
            ->and($row->workspace_id)->toBe($tenant->current()->workspace->id)
            ->and($row->occurred_at)->not->toBeNull();
        $context = json_decode((string) $row->context, true);
        expect($context['impersonation'])->toEqual([
            'grant' => $state->grantId,
            'target_user_id' => $state->targetUserId,
            'reason' => 'because',
        ]);
    }

    $direct = json_decode((string) $rows->firstWhere('action', 'direct')->context, true);
    expect($direct['ip'])->toBe('10.0.0.8');
});

it('leaves user and system records byte-equivalent when no impersonation is active', function (): void {
    $workspaceId = Str::uuid7()->toString();
    $actorId = Str::uuid7()->toString();
    $logger = app()->make(QueuedAuditLogger::class, ['tenantContext' => fixedTenant($workspaceId)]);
    $user = new AuditEntry(action: 'user-act', actorType: ActorType::User, actorId: $actorId, context: JsonDocuments::capture(['ip' => '1.1.1.1']));
    $system = new AuditEntry(action: 'system-act', actorType: ActorType::System, context: JsonDocuments::capture(['module' => 'core']));

    $logger->record($user);
    $logger->record($system);

    $rows = DB::table('sys_audit_log')->orderBy('action')->get()->keyBy('action');
    expect($rows['system-act']->actor_type)->toBe('system')
        ->and($rows['system-act']->actor_id)->toBeNull()
        ->and(json_decode((string) $rows['system-act']->context, true))->toBe(['module' => 'core'])
        ->and($rows['user-act']->actor_type)->toBe('user')
        ->and($rows['user-act']->actor_id)->toBe($actorId)
        ->and(json_decode((string) $rows['user-act']->context, true))->toBe(['ip' => '1.1.1.1'])
        ->and($rows['user-act']->workspace_id)->toBe($workspaceId);
});

it('writes the captured snapshot from a worker that has no session', function (): void {
    Queue::fake();
    $state = impersonationState('captured');
    app()->instance(ImpersonationService::class, new FixedImpersonationService($state));
    Auth::login(new User);
    $user = Auth::user();
    expect($user)->not->toBeNull();
    $user->id = 'session-user';

    $workspaceId = Str::uuid7()->toString();
    (app()->make(QueuedAuditLogger::class, ['tenantContext' => fixedTenant($workspaceId)]))->record(new AuditEntry(
        action: 'queued',
        actorType: ActorType::User,
        actorId: 'session-user',
    ));

    Auth::logout();
    app()->instance(ImpersonationService::class, new NullImpersonationService);
    expect(resolve(ImpersonationService::class)->state())->toBeNull();

    $jobs = Queue::pushed(RecordAuditEntry::class);
    expect($jobs)->toHaveCount(1);
    app()->call([$jobs[0], 'handle']);

    $row = DB::table('sys_audit_log')->sole();
    expect($row->actor_type)->toBe('support')
        ->and($row->actor_id)->toBe($state->staffIdentityId)
        ->and($row->workspace_id)->toBe($workspaceId)
        ->and(json_decode((string) $row->context, true)['impersonation'])->toMatchArray([
            'grant' => $state->grantId,
            'target_user_id' => $state->targetUserId,
            'reason' => 'captured',
        ]);
});

it('keeps snapshot and impersonation fields in either copy order', function (): void {
    $state = impersonationState('order');
    $at = new DateTimeImmutable('2026-09-25T03:00:00Z');
    $entry = new AuditEntry(
        action: 'ordered',
        actorType: ActorType::User,
        actorId: 'caller',
        context: JsonDocuments::capture(['ip' => '8.8.8.8', 'impersonation' => ['grant' => 'spoof']]),
    );

    $first = $entry->withImpersonation($state)->withSnapshot($at, 'ws-order');
    $second = $entry->withSnapshot($at, 'ws-order')->withImpersonation($state);

    expect($first)->toEqual($second)
        ->and($first->actorType)->toBe(ActorType::Support)
        ->and($first->actorId)->toBe($state->staffIdentityId)
        ->and($first->occurredAt)->toEqual($at)
        ->and($first->workspaceId)->toBe('ws-order')
        ->and(JsonDocuments::map($first->context)['ip'])->toBe('8.8.8.8')
        ->and(JsonDocuments::map($first->context)['impersonation']['grant'])->toBe($state->grantId);
})->group('pg');

function impersonationState(string $reason): ImpersonationState
{
    return new ImpersonationState(
        (string) Str::uuid7(),
        (string) Str::uuid7(),
        (string) Str::uuid7(),
        new DateTimeImmutable('+1 hour'),
        $reason,
    );
}

function fixedTenant(string $workspaceId): TenantContextResolver
{
    $host = LocalWorkContexts::install(app());
    $host->initialize(new AccountRef('acct-1', 'acct', 'active', [], []));
    $host->setWorkspace(new WorkspaceRef($workspaceId, 'main', 'standard', 'main', null));
    WorkScopeFixture::install(app())->scope();

    return $host;
}

function auditSubject(string $id): Model
{
    $model = new class extends Model
    {
        public string $subjectId = '';

        public function getKey(): string
        {
            return $this->subjectId;
        }

        public function getMorphClass(): string
        {
            return 'widget';
        }
    };
    $model->subjectId = $id;

    return $model;
}

final readonly class FixedImpersonationService implements ImpersonationService
{
    public function __construct(private ?ImpersonationState $fixed) {}

    public function start(string $grantId, string $staffIdentityId): void {}

    public function stop(): void {}

    public function active(): ?string
    {
        return $this->fixed?->grantId;
    }

    public function state(): ?ImpersonationState
    {
        return $this->fixed;
    }
}
