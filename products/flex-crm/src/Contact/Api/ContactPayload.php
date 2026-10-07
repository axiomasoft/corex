<?php

declare(strict_types=1);

namespace Flex\Crm\Contact\Api;

use Flex\Crm\Contact\Models\Contact;
use Flex\Crm\Internal\Value;

final class ContactPayload
{
    /** @return array<string, mixed> */
    public static function from(Contact $contact): array
    {
        return [
            'id' => Value::text($contact->getKey()),
            'workspace_id' => $contact->workspace_id === null ? null : (string) $contact->workspace_id,
            'name_first' => $contact->name_first,
            'name_last' => $contact->name_last,
            'name_middle' => $contact->name_middle,
            'display_name' => $contact->display_name,
            'job_title' => $contact->job_title,
            'birthday' => $contact->birthday?->format('Y-m-d'),
            'phones' => $contact->phones,
            'emails' => $contact->emails,
            'source_id' => $contact->source_id === null ? null : (string) $contact->source_id,
            'owner_id' => $contact->owner_id === null ? null : $contact->owner_id,
        ];
    }
}
