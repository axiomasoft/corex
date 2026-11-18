<?php

declare(strict_types=1);

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Packman\Schema\Laravel\PackmanMigration;

return new class extends PackmanMigration
{
    protected function hasTables(): bool
    {
        return false;
    }

    public function up(): void
    {
        $this->create('tnt_membership_pairs', static function (Blueprint $table): void {
            $table->uuid('account_id');
            $table->uuid('identity_id');
            $table->decimal('revision', total: 20, places: 0)->default(0);
            $table->decimal('revoke_generation', total: 20, places: 0)->default(0);
            $table->decimal('local_epoch', total: 20, places: 0)->default(0);
            $table->decimal('operation_fence', total: 20, places: 0)->default(0);
            $table->decimal('application_fence', total: 20, places: 0)->nullable();
            $table->uuid('operation_id')->nullable();
            $table->uuid('applied_operation_id')->nullable();
            $table->uuid('barrier_id')->nullable();
            $table->char('snapshot_digest', 64)->nullable();
            $table->boolean('denied')->default(true);
            $table->primary(['account_id', 'identity_id'], 'tnt_membership_pairs_pk');
        });

        DB::statement('ALTER TABLE tnt_membership_pairs ADD CONSTRAINT tnt_membership_pairs_revision_range_ck CHECK (revision BETWEEN 0 AND 18446744073709551615)');
        DB::statement('ALTER TABLE tnt_membership_pairs ADD CONSTRAINT tnt_membership_pairs_revoke_generation_range_ck CHECK (revoke_generation BETWEEN 0 AND 18446744073709551615)');
        DB::statement('ALTER TABLE tnt_membership_pairs ADD CONSTRAINT tnt_membership_pairs_local_epoch_range_ck CHECK (local_epoch BETWEEN 0 AND 18446744073709551615)');
        DB::statement('ALTER TABLE tnt_membership_pairs ADD CONSTRAINT tnt_membership_pairs_operation_fence_range_ck CHECK (operation_fence BETWEEN 0 AND 18446744073709551615)');
        DB::statement('ALTER TABLE tnt_membership_pairs ADD CONSTRAINT tnt_membership_pairs_application_fence_range_ck CHECK (application_fence IS NULL OR application_fence BETWEEN 0 AND 18446744073709551615)');
        DB::statement("ALTER TABLE tnt_membership_pairs ADD CONSTRAINT tnt_membership_pairs_snapshot_digest_format_ck CHECK (snapshot_digest IS NULL OR snapshot_digest ~ '^[a-f0-9]{64}$')");
    }

    public function down(): void
    {
        if (Schema::hasTable('tnt_membership_pairs') && DB::table('tnt_membership_pairs')->exists()) {
            throw new RuntimeException('Refusing to drop populated membership pair mapping.');
        }

        $this->drop('tnt_membership_pairs');
    }
};
