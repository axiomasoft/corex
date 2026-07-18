<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// See DB_SCHEMA.md §3.1 root_account_products for the column-by-column
// source. D10: one DB per account, "products" are module bundles.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('root_account_products', function (Blueprint $table): void {
            $table->uuid('account_id');
            $table->string('product', 32);
            $table->timestampTz('activated_at', precision: 6)->useCurrent();
            $table->timestampTz('created_at', precision: 6)->useCurrent();
            $table->timestampTz('updated_at', precision: 6)->useCurrent();

            $table->primary(['account_id', 'product']);
            $table->foreign('account_id')->references('id')->on('root_accounts')->cascadeOnDelete();
        });

        DB::statement("ALTER TABLE root_account_products ADD CONSTRAINT root_account_products_product_ck CHECK (product IN ('flexcrm','flexstore'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('root_account_products');
    }
};
