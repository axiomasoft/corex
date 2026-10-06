<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sys_workflow_intents', function (Blueprint $table): void {
            $table->uuid('id');
            $table->primary(['id'], 'sys_workflow_intents_pk');
            $table->string('request_key', 128)->unique('sys_workflow_intents_request_key_uq');
            $table->char('payload_hash', 64);
            $table->char('context_hash', 64);
            $table->char('restore_epoch', 64);
            $table->string('module', 128);
            $table->string('kind', 16);
            $table->string('definition_ref', 255)->nullable();
            $table->string('version_ref', 255)->nullable();
            $table->string('subject_ref', 255);
            $table->json('payload');
            $table->string('state', 16);
            $table->string('provider_instance', 255)->nullable();
            $table->unsignedBigInteger('claim_epoch')->default(0);
            $table->unsignedBigInteger('attempt')->default(0);
            $table->unsignedBigInteger('generation')->default(0);
            $table->timestamps();
            $table->index(['state', 'updated_at'], 'sys_workflow_intents_state_updated_at_ix');
        });

        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            Schema::getConnection()->statement('ALTER TABLE sys_workflow_intents RENAME CONSTRAINT sys_workflow_intents_pkey TO sys_workflow_intents_pk');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('sys_workflow_intents');
    }
};
