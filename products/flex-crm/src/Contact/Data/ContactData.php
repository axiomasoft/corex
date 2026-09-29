<?php

declare(strict_types=1);

namespace Flex\Crm\Contact\Data;

final readonly class ContactData
{
    /**
     * @param  list<array<string, mixed>>  $phones
     * @param  list<array<string, mixed>>  $emails
     */
    public function __construct(
        public ?string $nameFirst = null,
        public ?string $nameLast = null,
        public ?string $nameMiddle = null,
        public ?string $jobTitle = null,
        public ?string $birthday = null,
        public array $phones = [],
        public array $emails = [],
        public string|int|null $sourceId = null,
        public string|int|null $ownerId = null,
        public bool $accountLevel = false,
    ) {}
}
