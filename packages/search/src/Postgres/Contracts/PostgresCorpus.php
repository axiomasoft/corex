<?php

declare(strict_types=1);

namespace CoreX\Search\Postgres\Contracts;

use CoreX\Search\Index\Data\IndexTarget;
use CoreX\Search\Postgres\Data\PostgresCorpusRows;
use Illuminate\Database\ConnectionInterface;
use Packman\Attributes\Spi;
use Packman\Attributes\SpiKind;

/** Supplies the current scope's owner rows and their approved physical column mappings. */
#[Spi(kind: SpiKind::Implement, since: '2.0.0')]
interface PostgresCorpus
{
    public function rows(IndexTarget $target, ConnectionInterface $connection): PostgresCorpusRows;
}
