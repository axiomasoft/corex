<?php

declare(strict_types=1);

namespace App\Scenario;

use CoreX\Context\Contracts\CurrentWorkScope;
use CoreX\Data\Data\DataScope;
use CoreX\Search\Index\Data\IndexTarget;
use CoreX\Search\Postgres\Contracts\PostgresCorpus;
use CoreX\Search\Postgres\Contracts\PostgresSnapshotAuthority;
use CoreX\Search\Postgres\Data\PostgresCorpusRows;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\DB;

final class SearchScenarioPg implements PostgresCorpus, PostgresSnapshotAuthority
{
    public function rows(IndexTarget $target, ConnectionInterface $connection): PostgresCorpusRows
    {
        resolve(CurrentWorkScope::class)->assert($target->scope, $connection);
        $rows = SearchScenarioData::rows($target->scope, $target->entity);

        return new PostgresCorpusRows($rows, 'id', ['title' => 'title', 'secret' => 'secret'],
            ['secret' => 'secret', 'visible_flag' => 'visible_flag', 'rank' => 'rank'], ['rank' => 'rank'], ['rank' => 'rank']);
    }

    public function covers(DataScope $scope, ConnectionInterface $connection): bool
    {
        resolve(CurrentWorkScope::class)->assert($scope, $connection);
        $mode = $connection->selectOne("SELECT current_setting('transaction_isolation') AS isolation, current_setting('transaction_read_only') AS read_only");

        return DB::connection() === $connection && $connection->transactionLevel() > 0 && $mode?->isolation === 'repeatable read' && $mode?->read_only === 'on';
    }
}
