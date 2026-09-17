<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('med_files', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('request_key', 128)->unique();
            $table->char('request_digest', 64);
            $table->string('state', 16);
            $table->string('object_key');
            $table->string('mime_type', 255);
            $table->string('kind', 128);
            $table->unsignedBigInteger('size_bytes');
            $table->char('sha256', 64);
            $table->string('uploaded_actor_kind', 64);
            $table->string('uploaded_actor_id', 128)->nullable();
            $table->string('workspace_scope_key', 128)->nullable();
            $table->unsignedBigInteger('version');
            $table->timestamps();
            $table->softDeletes();
        });
    }
};
