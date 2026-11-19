<?php

declare(strict_types=1);

namespace CoreX\Access\Access\Internal;

use AzGuard\Kernel\Identity\SubjectRef;
use CoreX\Context\Contracts\Principal;
use Illuminate\Database\Eloquent\Model;
use LogicException;
use Packman\Attributes\Internal;

/** In-memory identity projection for the matrix panel. Identity comes from the CoreX host. */
#[Internal]
final class MatrixSubject extends Model
{
    protected $keyType = 'string';

    public $incrementing = false;

    public static function fromPrincipal(Principal $principal): self
    {
        $identity = SubjectRef::of($principal->principalType()->value, $principal->principalId());

        return (new self)->newFromBuilder(['id' => $identity->key()]);
    }

    public function getMorphClass(): string
    {
        return 'corex-principal';
    }

    public function newModelQuery(): never
    {
        throw new LogicException('CoreX matrix subjects exist only inside an explicit permission request.');
    }
}
