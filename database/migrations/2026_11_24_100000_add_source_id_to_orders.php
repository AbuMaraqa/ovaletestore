<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Orders imported from WooCommerce keep their source id, so OrderSeeder can be re-run safely
 * (updates in place, never duplicates).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->unsignedBigInteger('source_id')->nullable()->unique()->after('number');
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropUnique(['source_id']);
            $table->dropColumn('source_id');
        });
    }
};
