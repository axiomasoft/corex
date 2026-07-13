<?php

declare(strict_types=1);

use Flex\Modules\Contracts\RecordsRegistrar;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sba_widgets', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
        });

        app(RecordsRegistrar::class)->claim('corex/stub-base', 'table', 'sba_widgets');
    }

    public function down(): void
    {
        Schema::dropIfExists('sba_widgets');
    }
};
