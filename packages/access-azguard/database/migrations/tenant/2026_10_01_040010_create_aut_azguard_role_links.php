<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('aut_azguard_role_links', static function (Blueprint $table): void {
            $table->uuid('role_id');
            $table->primary(['role_id'], 'aut_azguard_role_links_pk');
            $table->unsignedBigInteger('azguard_role_id')->unique('aut_azguard_role_links_azguard_role_id_uq');
        });

        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            Schema::getConnection()->statement('ALTER TABLE aut_azguard_role_links RENAME CONSTRAINT aut_azguard_role_links_pkey TO aut_azguard_role_links_pk');
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('aut_azguard_role_links');

    }
};
