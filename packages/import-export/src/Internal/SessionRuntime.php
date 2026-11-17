<?php

declare(strict_types=1);

namespace CoreX\ImportExport\Internal;

use CoreX\Context\Contracts\TenantContextResolver;
use CoreX\Data\Data\DataScope;
use CoreX\ImportExport\Exceptions\SessionUnavailable;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\DatabaseManager;

/** A retained session cannot carry database authority across tenant switches. */
final readonly class SessionRuntime
{
    public function __construct(private DatabaseManager $database, private TenantContextResolver $tenants, private ?string $transferId = null, private ?string $exportRequest = null) {}

    public function forTransfer(string $transferId, ?string $exportRequest = null): self
    {
        return new self($this->database, $this->tenants, $transferId, $exportRequest);
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
        $tenant = $this->tenants->current();

        if ($this->database->connection() !== $connection
            || $scope->connectionIdentity !== $connection->getDriverName().':'.$connection->getDatabaseName()
            || $scope->accountId !== $tenant->account->id
            || $scope->workspaceId !== $tenant->workspace?->id
            || $scope->boxed !== $tenant->isBoxed()) {
            throw new SessionUnavailable('The transfer session does not belong to the active tenant connection.');
        }
    }
}
