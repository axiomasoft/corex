<?php

declare(strict_types=1);

namespace Flex\Crm\Contact\Actions;

use DateTimeImmutable;
use Flex\Crm\Contact\Data\ContactData;
use Flex\Crm\Contact\Events\ContactCreated;
use Flex\Crm\Contact\Models\Contact;
use Flex\Crm\Context\ContextGuard;
use Illuminate\Validation\ValidationException;

final readonly class CreateContact
{
    public function __construct(private ContextGuard $guard) {}

    public function handle(ContactData $data): Contact
    {
        if ($data->birthday !== null && (($date = DateTimeImmutable::createFromFormat('!Y-m-d', $data->birthday)) === false || $date->format('Y-m-d') !== $data->birthday)) {
            throw ValidationException::withMessages(['birthday' => 'Birthday must be a valid YYYY-MM-DD date.']);
        }
        $ctx = $this->guard->current();

        return $this->guard->connection($ctx)->transaction(function () use ($data, $ctx): Contact {
            $this->guard->current(lock: true);
            $contact = new Contact([
                'workspace_id' => $this->guard->workspace(ctx: $ctx, accountLevel: $data->accountLevel),
                'name_first' => $data->nameFirst, 'name_last' => $data->nameLast, 'name_middle' => $data->nameMiddle,
                'job_title' => $data->jobTitle, 'birthday' => $data->birthday,
                'phones' => $data->phones, 'emails' => $data->emails,
                'source_id' => $data->sourceId, 'owner_id' => $data->ownerId,
            ]);
            $contact->setConnection($ctx->connectionName);

            if (! $contact->save()) {
                throw ValidationException::withMessages(['contact' => 'CRM creation was vetoed by an observer.']);
            }
            event(new ContactCreated($contact, $ctx->actor));

            return $contact;
        });
    }
}
