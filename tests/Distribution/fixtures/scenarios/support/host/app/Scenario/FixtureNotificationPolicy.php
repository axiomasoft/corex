<?php

declare(strict_types=1);

namespace App\Scenario;

use CoreX\Access\Contracts\AccessChecker;
use CoreX\Access\Laravel\PrincipalIdentity;
use CoreX\Context\Contracts\WorkPrincipalAuthority;
use CoreX\Context\Enums\PrincipalType;
use CoreX\Notifications\Delivery\Contracts\RecipientResolver;
use CoreX\Notifications\Delivery\Data\DeliveryTarget;
use CoreX\Notifications\Delivery\Data\RecipientReference;
use CoreX\Notifications\Inbox\Contracts\NotificationPolicy;
use CoreX\Notifications\Inbox\Data\NotificationAction;
use CoreX\Notifications\Inbox\Data\NotificationContext;
use CoreX\Notifications\Inbox\Data\NotificationSubject;

final readonly class FixtureNotificationPolicy implements NotificationPolicy, RecipientResolver
{
    public function __construct(private WorkPrincipalAuthority $principals) {}

    public function allows(NotificationContext $context, NotificationAction $action, NotificationSubject $subject): bool
    {
        $principal = $this->principals->current($context->tenant);

        if ($principal->kind !== 'user' || $context->actorKind !== 'user' || $context->actorId !== $principal->id
            || $subject->recipient->kind !== 'user' || $subject->recipient->id !== $principal->id) {
            return false;
        }

        if ($subject->related === null) {
            return true;
        }
        $record = $subject->related->handle === NotificationRelated::HANDLE
            ? NotificationRelated::query()->find($subject->related->id)
            : null;

        return $record !== null && app(AccessChecker::class)->canOnRecord(new PrincipalIdentity(PrincipalType::User, $principal->id), 'read', $record);
    }

    public function resolve(NotificationContext $context, RecipientReference $recipient, string $channel): ?DeliveryTarget
    {
        $principal = $this->principals->current($context->tenant);

        return $recipient->kind === 'user' && $recipient->id === $principal->id && $channel === FixtureNotificationDriver::CHANNEL
            ? new DeliveryTarget($channel, $recipient->id)
            : null;
    }
}
