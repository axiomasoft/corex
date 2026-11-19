<?php

declare(strict_types=1);

namespace CoreX\Access\Access\Internal;

use AzGuard\Kernel\Identity\AccessScope;
use CoreX\Context\Contracts\Principal;
use Packman\Attributes\Internal;

#[Internal]
final readonly class MatrixCell
{
    public function __construct(public Principal $principal, public string $entity, public string $action, public AccessScope $scope) {}
}
