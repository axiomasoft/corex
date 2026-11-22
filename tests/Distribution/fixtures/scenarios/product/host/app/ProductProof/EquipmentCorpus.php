<?php

declare(strict_types=1);

namespace App\ProductProof;

use App\FieldSearch\EquipmentOwner;
use CoreX\Context\Contracts\CurrentWorkScope;
use CoreX\Search\Index\Data\IndexTarget;
use CoreX\Search\Postgres\Contracts\PostgresCorpus;
use CoreX\Search\Postgres\Data\PostgresCorpusRows;
use Equipment\Registry\Equipment;
use Illuminate\Database\ConnectionInterface;
use RuntimeException;

final readonly class EquipmentCorpus implements PostgresCorpus
{
    public function __construct(private CurrentWorkScope $current) {}

    public function rows(IndexTarget $target, ConnectionInterface $connection): PostgresCorpusRows
    {
        $this->current->assert($target->scope, $connection);

        if ($target->entity->value !== Equipment::Handle) {
            throw new RuntimeException('No installed equipment corpus for a foreign entity.');
        }

        return new PostgresCorpusRows(EquipmentOwner::records($target->scope), 'asset_key', ['title' => 'name']);
    }
}
