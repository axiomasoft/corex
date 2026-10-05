<?php

declare(strict_types=1);

use CoreX\Context\Contracts\TenantContextResolver;
use CoreX\Context\Data\AccountRef;
use CoreX\Context\Data\TenantContext;
use CoreX\Data\Data\JsonDocument;
use CoreX\Data\Laravel\JsonDocuments;
use CoreX\Notifications\Delivery\Data\RecipientReference;
use CoreX\Notifications\Digest\Contracts\DigestBuilder;
use CoreX\Notifications\Digest\Data\DigestWindow;
use CoreX\Notifications\Inbox\Contracts\NotificationCenter;
use CoreX\Notifications\Inbox\Contracts\NotificationPolicy;
use CoreX\Notifications\Inbox\Data\EntityReference;
use CoreX\Notifications\Inbox\Data\NotificationAction;
use CoreX\Notifications\Inbox\Data\NotificationContext;
use CoreX\Notifications\Inbox\Data\NotificationInput;
use CoreX\Notifications\Inbox\Data\NotificationSubject;
use CoreX\Notifications\Tests\NotificationTenantContextResolver;
use CoreX\Scheduling\Contracts\WakeupScheduler;
use CoreX\Scheduling\Data\CancelResult;
use CoreX\Scheduling\Data\ScheduleResult;
use CoreX\Scheduling\Data\WakeupRequest;
use CoreX\Settings\Contracts\SettingsRepository;
use CoreX\Settings\Data\SettingsScope;
use Illuminate\Support\Facades\DB;

final class SchedulingSpy implements WakeupScheduler
{
    public int $calls = 0;

    public function schedule(WakeupRequest $request): ScheduleResult
    {
        $this->calls++;

        return new ScheduleResult('scheduled', $request->requestKey);
    }

    public function cancel(TenantContext $context, string $requestKey): CancelResult
    {
        return new CancelResult('cancelled');
    }
}

final class SchedulingDigestPolicy implements NotificationPolicy
{
    public function allows(NotificationContext $context, NotificationAction $action, NotificationSubject $subject): bool
    {
        return true;
    }
}

final class SchedulingSettingsRepository implements SettingsRepository
{
    public function get(string $namespace, string $key, ?SettingsScope $scope = null, ?JsonDocument $default = null): JsonDocument
    {
        return JsonDocuments::capture($default);
    }

    public function set(string $namespace, string $key, JsonDocument $value, ?SettingsScope $scope = null): void {}

    public function forget(string $namespace, string $key, ?SettingsScope $scope = null): void {}

    public function all(string $namespace, ?SettingsScope $scope = null): array
    {
        return [];
    }
}

function schedulingFreeze(string $requestKey): ?object
{
    app()->instance(NotificationPolicy::class, new SchedulingDigestPolicy);
    app()->instance(SettingsRepository::class, new SchedulingSettingsRepository);
    app()->instance(TenantContextResolver::class, new NotificationTenantContextResolver);
    app()->forgetScopedInstances();
    app(NotificationCenter::class)->notify(schedulingContext(), new NotificationInput(
        requestKey: $requestKey,
        eventType: 'document.ready',
        recipient: new RecipientReference(kind: 'user', id: '11111111-1111-7111-8111-111111111112'),
        templateKey: 'document-ready',
        payload: JsonDocuments::capture(['message' => $requestKey]),
        related: new EntityReference(handle: 'document', id: 'document-1'),
    ));
    $end = new DateTimeImmutable('-1 minute');
    $start = $end->modify('-1 hour');
    DB::table('ntf_notifications')->update(['created_at' => $start->modify('+1 minute')]);

    return app(DigestBuilder::class)->build(schedulingContext(), new DigestWindow(
        start: $start,
        end: $end,
        period: 'hourly',
    ));
}

function schedulingContext(): NotificationContext
{
    return new NotificationContext(
        tenant: new TenantContext(new AccountRef('11111111-1111-7111-8111-111111111111', 'notification-test', 'active', [], [])),
        actorKind: 'user',
        actorId: '11111111-1111-7111-8111-111111111112',
    );
}
