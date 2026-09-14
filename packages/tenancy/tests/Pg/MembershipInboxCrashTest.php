<?php

declare(strict_types=1);

use CoreX\Tenancy\Membership\Internal\Inbox\DurableInbox;
use CoreX\Tenancy\Membership\Internal\Refresh\RefreshCoordinator;
use CoreX\Tenancy\Tests\Support\Membership\Consumer\InboxFixture;
use CoreX\Tenancy\Tests\Support\Membership\TransportFixture;
use CoreX\Tenancy\Tests\TestCase;
use Illuminate\Support\Facades\DB;

uses(TestCase::class);

it('allows a lease-expired crash owner to be retried by one successor', function (): void {
    InboxFixture::create();
    $fixture = new TransportFixture;
    $inbox = new DurableInbox(connection: DB::connection());

    try {
        $trigger = $fixture->parser()->parse(compact: $fixture->sign(claims: $fixture->eventClaims(), event: true), now: $fixture->now);
        $inbox->admit(trigger: $trigger);
        expect($inbox->claim(eventId: $trigger->eventId, owner: 'crashed-worker', now: 10))->toBeTrue();

        $retry = new RefreshCoordinator(inbox: $inbox, refresh: static fn (): null => null);
        expect($retry->refresh(eventId: $trigger->eventId, owner: 'recovery-worker', now: 41))->toBeTrue()
            ->and($inbox->isPending(eventId: $trigger->eventId))->toBeFalse();
    } finally {
        $fixture->cleanup();
        InboxFixture::drop();
    }
})->group('pg');
