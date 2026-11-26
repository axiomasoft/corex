<?php

declare(strict_types=1);

namespace App\OwnerEffects;

use Closure;
use RuntimeException;
use Vaulter\Database\VaulterDatabase;
use Vaulter\Media\Contracts\Cas\BlobPins;
use Vaulter\Media\Data\Snapshots\BlobSnapshot;

/** An observer on the real owner's transaction. Each row is an exact committed pin or release call. */
final class PinJournal implements BlobPins
{
    public static bool $interruptRelease = false;

    public function __construct(private readonly BlobPins $next, private readonly VaulterDatabase $database) {}

    public function pin(BlobSnapshot|int|string $blob, string $ownerType, string $referenceKey): void
    {
        $this->next->pin($blob, $ownerType, $referenceKey);
        $this->record($blob, $ownerType, $referenceKey, 'pin');
    }

    public function unpin(BlobSnapshot|int|string $blob, string $ownerType, string $referenceKey): void
    {
        $this->next->unpin($blob, $ownerType, $referenceKey);
        $this->record($blob, $ownerType, $referenceKey, 'unpin');

        if (self::$interruptRelease) {
            self::$interruptRelease = false;

            throw new RuntimeException('Observer interrupted the Vaulter release before owner transaction commit.');
        }
    }

    public function countByOwner(): array
    {
        return $this->next->countByOwner();
    }

    public function releaseOwner(string $ownerType, ?Closure $keys = null): int
    {
        return $this->next->releaseOwner($ownerType, $keys);
    }

    private function record(BlobSnapshot|int|string $blob, string $ownerType, string $referenceKey, string $action): void
    {
        $db = $this->database->connection();

        if ($db->transactionLevel() === 0 || $db !== resolve('db')->connection()) {
            throw new RuntimeException('Pin evidence must commit on the Media and Vaulter owner transaction.');
        }
        $db->table('scenario_vaulter_journal')->insert(['blob' => $blob instanceof BlobSnapshot ? $blob->id : (string) $blob,
            'owner' => $ownerType, 'reference' => $referenceKey, 'action' => $action, 'scope' => json_encode(Effects::scope(), JSON_THROW_ON_ERROR)]);
    }
}
