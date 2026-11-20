<?php

declare(strict_types=1);

require_once __DIR__.'/../Support/SchedulingDigestFixtures.php';

use CoreX\Context\Contracts\CurrentWorkScope;
use CoreX\Context\Contracts\DataScopeFactory;
use CoreX\Context\Contracts\StoredWorkIssuer;
use CoreX\Data\Contracts\DataScopeCodec;
use CoreX\Notifications\Digest\Internal\NotificationDigestDueSource;
use CoreX\Notifications\Digest\Internal\SqlDigestBuilder;
use CoreX\Notifications\Tests\TestCase;
use CoreX\Scheduling\Contracts\DueWakeupSource;
use CoreX\Scheduling\Data\WakeupHandlerDefinition;
use Illuminate\Support\Facades\DB;
use Packman\Config\Exceptions\InvalidConfiguration;
use Psr\Clock\ClockInterface;

uses(TestCase::class);

it('tags the digest handler and due source when digest is enabled', function (): void {
    $tags = new ReflectionProperty(app(), 'tags')->getValue(app());

    expect($tags[WakeupHandlerDefinition::TAG] ?? [])->toBe(['corex.notifications.digest-wakeup-handler'])
        ->and($tags[DueWakeupSource::TAG] ?? [])->toBe([NotificationDigestDueSource::class]);
});

it('rejects an enabled digest builder that has no scheduler before it writes', function (): void {
    expect(fn () => new SqlDigestBuilder(
        connection: resolve('db')->connection(),
        scopes: resolve(DataScopeFactory::class),
        currentScope: resolve(CurrentWorkScope::class),
        scopeCodec: resolve(DataScopeCodec::class),
        issuer: resolve(StoredWorkIssuer::class),
        wakeup: null,
        enabled: true,
        clock: resolve(ClockInterface::class),
    ))->toThrow(InvalidConfiguration::class, 'corex-notifications.digest.enabled requires a bound CoreX\\Scheduling\\Contracts\\WakeupScheduler');
});

it('schedules an enabled digest exactly once through the bound scheduler', function (): void {
    $digest = schedulingFreeze('enabled-once');

    expect($digest)->not->toBeNull()
        ->and(DB::table('sys_wakeup_requests')->where('kind', 'notification.digest.v1')->count())->toBe(1);
});
