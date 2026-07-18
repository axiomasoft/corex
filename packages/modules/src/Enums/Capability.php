<?php

declare(strict_types=1);

namespace CoreX\Modules\Enums;

/**
 * Contract of satellite hookup (B-10 §4.4): a satellite queries
 * EntityRegistry::byCapability() at boot and wires itself, without knowing
 * the owning module.
 */
enum Capability: string
{
    case Searchable = 'searchable';
    case Auditable = 'auditable';
    case CustomFields = 'custom_fields';
    case Workflowable = 'workflowable';
    case Commentable = 'commentable';
    case Documentable = 'documentable';
    case SoftDeletes = 'soft_deletes';
}
