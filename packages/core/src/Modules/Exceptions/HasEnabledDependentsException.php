<?php

declare(strict_types=1);

namespace CoreX\Modules\Exceptions;

use CoreX\Attributes\Api;
use RuntimeException;

#[Api(since: '1.0')]
final class HasEnabledDependentsException extends RuntimeException
{
    /** @param  list<string>  $dependents */
    public function __construct(string $module, array $dependents)
    {
        parent::__construct(sprintf(
            'Module [%s] has enabled dependents [%s]. Pass cascade: true to disable them first.',
            $module,
            implode(', ', $dependents),
        ));
    }
}
