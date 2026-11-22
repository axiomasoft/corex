<?php

declare(strict_types=1);

namespace CoreX\Access\Access\Internal;

use AzGuard\Kernel\Decision\AccessRequest;
use AzGuard\Policies\Decides;
use Packman\Attributes\Internal;

#[Internal]
final readonly class MatrixPolicy
{
    public function __construct(private MatrixPermissions $permissions, private CoreXAccessContextMapper $contexts) {}

    #[Decides(MatrixPermission::Evaluate)]
    public function evaluate(AccessRequest $request): bool
    {
        $cell = $request->resource();

        if (! $cell instanceof MatrixCell || ! $request->subjectModel() instanceof MatrixSubject
            || $request->subject()->id() !== $this->contexts->subject($cell->principal)->key()
            || $this->contexts->scope()?->equals($cell->scope) !== true) {
            return false;
        }

        return $this->permissions->allowed(p: $cell->principal, entityKey: $cell->entity, action: $cell->action);
    }
}
