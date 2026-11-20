<?php

declare(strict_types=1);

namespace CoreX\ImportExport\Internal;

use CoreX\Context\Contracts\CurrentWorkScope;
use CoreX\Context\Exceptions\ScopeContextMismatch;
use CoreX\Data\Data\DataScope;
use CoreX\ImportExport\Exceptions\SessionUnavailable;
use Illuminate\Database\ConnectionInterface;
use Packman\Attributes\Internal;

/** A retained session cannot carry database authority across tenant switches. */
#[Internal]
final readonly class SessionRuntime
{
    public function __construct(private CurrentWorkScope $current, private ?string $transferId = null, private ?string $exportRequest = null) {}

    public function forTransfer(string $transferId, ?string $exportRequest = null): self
    {
        return new self($this->current, $transferId, $exportRequest);
    }

    public function assertTransfer(string $transferId): void
    {
        if ($this->transferId !== $transferId) {
            throw new SessionUnavailable('The runner belongs to another transfer session.');
        }
    }

    public function assertExport(string $request): void
    {
        if ($this->exportRequest !== $request) {
            throw new SessionUnavailable('The export inputs differ from the session request.');
        }
    }

    public function assertCurrent(ConnectionInterface $connection, DataScope $scope): void
    {
        try {
            $this->current->assert($scope, $connection);
        } catch (ScopeContextMismatch $exception) {
            throw new SessionUnavailable('The transfer session does not belong to the active tenant connection.', previous: $exception);
        }
    }
}
