<?php

declare(strict_types=1);

namespace App\BulkTransfer;

use CoreX\Context\Contracts\CurrentWorkScope;
use CoreX\Data\Data\DataScope;
use CoreX\Data\Data\JsonDocument;
use CoreX\Data\Laravel\JsonDocuments;
use CoreX\ImportExport\Contracts\ExportSchemaWitness;
use Illuminate\Database\DatabaseManager;

final readonly class EquipmentExportSchema implements ExportSchemaWitness
{
    public function __construct(private CurrentWorkScope $current, private DatabaseManager $database) {}

    public function matches(DataScope $scope, string $expectedHash, JsonDocument $roster): bool
    {
        $connection = $this->database->connection();
        $this->current->assert($scope, $connection);
        $projection = JsonDocuments::map($roster);

        if (($projection['owners'] ?? null) !== ['equipment/registry'] || ! isset($projection[InputIdentity::ENTITY])) {
            return false;
        }

        $revision = $connection->table('bulk_fixture_revisions')->value('schema_hash');

        return is_string($revision) && hash_equals($expectedHash, $revision);
    }
}
