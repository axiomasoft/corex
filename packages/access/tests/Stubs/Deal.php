<?php

declare(strict_types=1);

namespace CoreX\Access\Tests\Stubs;

use Illuminate\Database\Eloquent\Model;

/**
 * Stub record for AccessChecker::canOnRecord() scope-predicate tests
 * (own/workspace/account) — no timestamps/soft-deletes needed.
 *
 * @property string $id
 * @property ?string $owner_id
 * @property ?string $workspace_id
 */
final class Deal extends Model
{
    public $timestamps = false;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];
}
