<?php

declare(strict_types=1);

use CoreX\Tenancy\Central\CentralUnauthorized;
use CoreX\Tenancy\Membership\Internal\Inbox\DurableInbox;
use CoreX\Tenancy\Membership\Internal\Refresh\RefreshCoordinator;
use CoreX\Tenancy\Tests\Support\Membership\Consumer\InboxFixture;
use CoreX\Tenancy\Tests\Support\Membership\TransportFixture;
use Illuminate\Support\Facades\DB;

beforeEach(function (): void {
    InboxFixture::create();
    $this->inbox = new DurableInbox(connection: DB::connection());
    $this->transport = new TransportFixture;
});

afterEach(function (): void {
    $this->transport->cleanup();
    InboxFixture::drop();
});

it('deduplicates a fresh delivery envelope without deriving positive authority', function (): void {
    $trigger = $this->transport->parser()->parse(compact: $this->transport->sign(claims: $this->transport->eventClaims(), event: true), now: $this->transport->now);
    $this->inbox->admit(trigger: $trigger);
    $this->inbox->admit(trigger: $trigger);

    expect(DB::table('membership_trigger_inbox')->count())->toBe(1)
        ->and($this->inbox->isPending(eventId: $trigger->eventId))->toBeTrue();
});

it('keeps failed refresh work pending and acknowledges only a completed pull', function (): void {
    $trigger = $this->transport->parser()->parse(compact: $this->transport->sign(claims: $this->transport->eventClaims(), event: true), now: $this->transport->now);
    $this->inbox->admit(trigger: $trigger);

    $failed = new RefreshCoordinator(inbox: $this->inbox, refresh: static function (): void {
        throw new RuntimeException('network unavailable');
    });
    $succeeded = new RefreshCoordinator(inbox: $this->inbox, refresh: static fn (): null => null);

    expect($failed->refresh(eventId: $trigger->eventId, owner: 'worker-a', now: 10))->toBeFalse()
        ->and($this->inbox->isPending(eventId: $trigger->eventId))->toBeTrue()
        ->and($succeeded->refresh(eventId: $trigger->eventId, owner: 'worker-b', now: 41))->toBeTrue()
        ->and($this->inbox->isPending(eventId: $trigger->eventId))->toBeFalse();
});

it('rejects expired input before any durable inbox write', function (): void {
    $claims = [...$this->transport->eventClaims(), 'exp' => $this->transport->now - 31];

    expect(fn (): mixed => $this->transport->parser()->parse(compact: $this->transport->sign(claims: $claims, event: true), now: $this->transport->now))->toThrow(CentralUnauthorized::class)
        ->and(DB::table('membership_trigger_inbox')->count())->toBe(0);
});
