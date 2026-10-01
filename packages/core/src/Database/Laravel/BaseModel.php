<?php

declare(strict_types=1);

namespace CoreX\Database\Laravel;

use CoreX\Attributes\Spi;
use CoreX\Attributes\SpiKind;
use CoreX\Audit\Laravel\HasAuditLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @corex-model-extension Eloquent base; configured primary key, deleted_at and Auditable opt-in. Subclasses retain Eloquent relations.
 * Base for ecosystem domain models: ULID key (ADR-003), soft deletes, and an
 * audit trail that only records when the model also implements Auditable.
 *
 * Tenant isolation is physical (DB-per-account, B-11): a model loaded here
 * already comes from its account's database, so no row-level scope is applied.
 * Models that cannot extend this base (e.g. an Authenticatable User) compose
 * the same traits directly.
 */
#[Spi(kind: SpiKind::Implement, since: '1.0')]
abstract class BaseModel extends Model
{
    use HasAuditLog;
    use HasConfiguredId;
    use SoftDeletes;
}
