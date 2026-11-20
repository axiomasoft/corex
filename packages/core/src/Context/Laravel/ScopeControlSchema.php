<?php

declare(strict_types=1);

namespace CoreX\Context\Laravel;

use Illuminate\Database\Connection;
use Illuminate\Database\Schema\Blueprint;
use Packman\Attributes\Spi;
use Packman\Attributes\SpiKind;

#[Spi(kind: SpiKind::Call, since: '2.0.0')]
final class ScopeControlSchema
{
    /** Install on the host's independent control store during provisioning. */
    public static function install(Connection $control): void
    {
        $schema = $control->getSchemaBuilder();
        $control->transaction(function () use ($schema): void {
            $schema->create('corex_scope_routes', function (Blueprint $table): void {
                $table->string('deployment_id', 128);
                $table->string('account_id', 128);
                $table->string('binding_id', 128);
                $table->unsignedBigInteger('binding_generation');
                $table->string('account_incarnation', 128);
                $table->char('restore_epoch', 64);
                $table->json('used_epochs');
                $table->string('mode', 16);
                $table->unsignedBigInteger('control_revision');
                $table->primary(['deployment_id', 'account_id'], 'corex_scope_routes_pk');
            });
            $schema->create('corex_scope_tickets', function (Blueprint $table): void {
                $table->uuid('ticket_id');
                $table->primary(['ticket_id'], 'corex_scope_tickets_pk');
                $table->string('deployment_id', 128);
                $table->string('account_id', 128);
                $table->string('binding_id', 128);
                $table->unsignedBigInteger('binding_generation');
                $table->char('restore_epoch', 64);
                $table->unsignedBigInteger('control_revision');
                $table->json('scope');
                $table->string('operation_id', 191);
                $table->unsignedInteger('attempt');
                $table->string('kind', 32);
                $table->string('effect_key', 191)->nullable();
                $table->timestampTz('deadline', 6)->nullable();
                $table->string('state', 32);
                $table->string('evidence')->nullable();
                $table->unique(['deployment_id', 'account_id', 'operation_id', 'attempt'], 'corex_scope_attempt_uq');
                $table->index(['binding_id', 'binding_generation', 'restore_epoch', 'state'], 'corex_scope_outstanding_ix');
            });
        });
    }
}
