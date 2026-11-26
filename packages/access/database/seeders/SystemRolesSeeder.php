<?php

declare(strict_types=1);

namespace CoreX\Access\Database\Seeders;

use CoreX\Access\Roles\Internal\Models\Role;
use CoreX\Context\Enums\PrincipalType;
use Illuminate\Database\Seeder;

/**
 * Seeds the three system roles with an EMPTY matrix — modules fill
 * it in via `Extend\Permissions`, the core never
 * invents entity/action rows. `owner`'s wildcard capability lives at the
 * grant-source level, not as a stored `'*'` permission key.
 */
final class SystemRolesSeeder extends Seeder
{
    public function run(): void
    {
        Role::withSystemMutationAllowed(function (): void {
            foreach ($this->systemRoles() as $attributes) {
                Role::query()->updateOrCreate(['slug' => $attributes['slug']], $attributes);
            }
        });
    }

    /** @return list<array{slug: string, name: string, description: string, is_system: bool, assignable_to: list<string>}> */
    private function systemRoles(): array
    {
        $onlyUser = [PrincipalType::User->value];

        return [
            [
                'slug' => 'owner',
                'name' => 'Owner',
                'description' => 'Full account control through the owner grant source.',
                'is_system' => true,
                'assignable_to' => $onlyUser,
            ],
            [
                'slug' => 'admin',
                'name' => 'Admin',
                'description' => 'Administrative role; matrix is populated by installed modules.',
                'is_system' => true,
                'assignable_to' => $onlyUser,
            ],
            [
                'slug' => 'employee',
                'name' => 'Employee',
                'description' => 'Default operating role; matrix is populated by installed modules.',
                'is_system' => true,
                'assignable_to' => $onlyUser,
            ],
        ];
    }
}
