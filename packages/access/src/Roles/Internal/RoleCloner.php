<?php

declare(strict_types=1);

namespace CoreX\Access\Roles\Internal;

use CoreX\Access\Roles\Internal\Models\Role;
use CoreX\Access\Roles\Internal\Models\RoleMatrixRow;
use CoreX\Foundation\Laravel\ReferenceWrites;
use Illuminate\Database\ConnectionResolverInterface;
use Packman\Attributes\Internal;

/**
 * Customization path for a system role: creates an editable,
 * non-system copy with `cloned_from_id` set and the source matrix copied
 * row-by-row. The clone does NOT inherit the `owner` wildcard — that grant
 * is keyed to the system `owner` slug at the source-of-truth layer,
 * not to any flag a clone could carry — so cloning `owner` yields a role
 * with no capability at all until its (now-editable) matrix is filled in.
 * Cloning `admin`/`employee` is the supported customization path for v1.
 */
#[Internal]
final readonly class RoleCloner
{
    public function __construct(private ConnectionResolverInterface $connections) {}

    public function clone(Role $source, string $slug, ?string $name = null): Role
    {
        return $this->connections->connection()->transaction(function () use ($source, $slug, $name): Role {
            ReferenceWrites::enlist(connection: $this->connections->connection(), scope: ReferenceWrites::current(), producer: 'axiomasoft/corex-access', kind: 'clone-role', witness: ['role' => $source->id]);
            $clone = Role::query()->create([
                'slug' => $slug,
                'name' => $name ?? $source->name,
                'description' => $source->description,
                'is_system' => false,
                'cloned_from_id' => $source->id,
                'assignable_to' => $source->assignable_to,
            ]);

            foreach ($source->matrix()->get() as $row) {
                RoleMatrixRow::query()->create([
                    'role_id' => $clone->id,
                    'entity_key' => $row->entity_key,
                    'action' => $row->action,
                    'scope' => $row->scope,
                    'context_type' => $row->context_type,
                    'context_id' => $row->context_id,
                ]);
            }

            return $clone;
        });
    }
}
