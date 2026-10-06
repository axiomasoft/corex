<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tnt_membership_inbox', static function (Blueprint $table): void {
            $table->uuid('event_id');
            $table->primary(['event_id'], 'tnt_membership_inbox_pk');
            $table->char('logical_digest', 64);
            $table->uuid('delivery_id');
            $table->string('status', 12);
            $table->unsignedBigInteger('attempts')->default(0);
            $table->uuid('owner_id')->nullable();
            $table->bigInteger('lease_until')->nullable();
        });

        Schema::getConnection()->statement('ALTER TABLE tnt_membership_inbox RENAME CONSTRAINT tnt_membership_inbox_pkey TO tnt_membership_inbox_pk');

        DB::statement("ALTER TABLE tnt_membership_inbox ADD CONSTRAINT tnt_membership_inbox_status_ck CHECK (status IN ('pending', 'acknowledged'))");
        DB::statement('CREATE INDEX tnt_membership_inbox_pending_ix ON tnt_membership_inbox (lease_until, event_id) WHERE status = \'pending\'');
    }

    public function down(): void
    {
        if (Schema::hasTable('tnt_membership_inbox') && DB::table('tnt_membership_inbox')->exists()) {
            throw new RuntimeException('Refusing to drop populated membership inbox mapping.');
        }

        Schema::dropIfExists('tnt_membership_inbox');
    }
};
