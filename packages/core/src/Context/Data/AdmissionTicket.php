<?php

declare(strict_types=1);

namespace CoreX\Context\Data;

use CoreX\Context\Enums\AdmissionKind;
use CoreX\Data\Data\DataScope;
use Packman\Attributes\Api;

#[Api(since: '2.0.0')]
final readonly class AdmissionTicket
{
    public function __construct(public string $id, public DataScope $scope, public int $controlRevision, public OperationKey $key, public AdmissionKind $kind) {}
}
