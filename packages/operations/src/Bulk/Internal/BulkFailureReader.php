<?php

declare(strict_types=1);

namespace CoreX\Operations\Bulk\Internal;

use Closure;
use CoreX\Data\Data\DataScope;
use CoreX\Operations\Bulk\Contracts\BulkOwner;
use CoreX\Operations\Bulk\Data\BulkOperation;
use InvalidArgumentException;
use Packman\Attributes\Internal;

#[Internal]
final readonly class BulkFailureReader
{
    public const int MAX_LIMIT = 100;

    /** @param Closure(): ?BulkOwner $owner */
    public function __construct(private Closure $owner) {}

    /** @return array{records: list<array{ordinal: string, code: string, path: ?string}>, next: ?string}|null */
    public function read(DataScope $scope, BulkOperation $operation, ?string $afterOrdinal, int $limit): ?array
    {
        if ($limit < 1 || $limit > self::MAX_LIMIT || ($afterOrdinal !== null && preg_match('/\A(?:0|[1-9][0-9]{0,18})\z/D', $afterOrdinal) !== 1)) {
            throw new InvalidArgumentException('Invalid bulk failure page limit or cursor.');
        }

        $owner = ($this->owner)();

        if ($owner === null) {
            return null;
        }

        $context = $operation->definition->context($scope);

        if ($operation->failurePageRef === null) {
            return $operation->ownerResultRef !== null && $owner->result($context, $operation->ownerResultRef) !== null
                ? ['records' => [], 'next' => null]
                : null;
        }

        $page = $owner->failures($context, $operation->failurePageRef, $afterOrdinal, $limit);

        if ($page === null) {
            return null;
        }

        if (count($page->records) > $limit) {
            throw new InvalidArgumentException('Bulk failure page exceeded the requested limit.');
        }

        return ['records' => $page->records, 'next' => $page->next];
    }
}
