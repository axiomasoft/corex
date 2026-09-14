<?php

declare(strict_types=1);

namespace Flex\Crm\Data;

final readonly class CompanyData
{
    /**
     * @param  list<array<string, mixed>>  $phones
     * @param  list<array<string, mixed>>  $emails
     * @param  array<string, mixed>|null  $address
     * @param  array<string, mixed>|null  $bankDetails
     */
    public function __construct(
        public string $name,
        public ?string $legalName = null,
        public ?string $domain = null,
        public ?string $inn = null,
        public ?string $kpp = null,
        public ?string $ogrn = null,
        public array $phones = [],
        public array $emails = [],
        public ?array $address = null,
        public ?array $bankDetails = null,
        public string|int|null $sourceId = null,
        public string|int|null $ownerId = null,
        public bool $accountLevel = false,
    ) {}
}
