<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** The orders screen sorts by date by default and filters by channel / customer account. */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->index('placed_at');
            $table->index(['channel', 'placed_at']);
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex(['placed_at']);
            $table->dropIndex(['channel', 'placed_at']);
        });
    }
};
