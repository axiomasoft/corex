<?php

declare(strict_types=1);

namespace CoreX\Tenancy\Membership\Data;

use CoreX\Attributes\Api;
use CoreX\Tenancy\Membership\Exceptions\CentralProtocolViolation;
use CoreX\Tenancy\Membership\Internal\Transport\Value;
use DateTimeImmutable;

#[Api(since: '1.0')]
final readonly class AvailableAccounts
{
    /** @param list<AccountListEntry> $accounts */
    public function __construct(public string $identityId, public string $product, public string $membershipVersion, public array $accounts, public DateTimeImmutable $observedAt)
    {
        self::validateInput(identityId: $identityId, product: $product, membershipVersion: $membershipVersion, accounts: $accounts, observedAt: $observedAt);
    }

    public function isFor(AccountsQuery $query): bool
    {
        return $this->identityId === $query->identityId && $this->product === $query->product;
    }

    /** Runtime validation also rejects values from untyped integration callers.
     * @param  array<array-key, mixed>  $accounts
     */
    private static function validateInput(
        /** Runtime input. */
        string $identityId,
        /** Runtime input. */
        string $product,
        /** Runtime input. */
        string $membershipVersion,
        /** Runtime input. */
        array $accounts,
        /** Runtime input. */
        DateTimeImmutable $observedAt,
    ): void {
        Value::uuid($identityId);
        Value::product($product);

        if (trim($membershipVersion) === '' || $observedAt->getTimezone()->getName() !== '+00:00') {
            throw new CentralProtocolViolation('Invalid accounts result metadata.');
        }

        $accountIds = [];
        foreach ($accounts as $account) {
            if (! $account instanceof AccountListEntry || isset($accountIds[$account->accountId])) {
                throw new CentralProtocolViolation('Accounts result must be a unique typed list.');
            }

            $accountIds[$account->accountId] = true;
        }
    }
}
