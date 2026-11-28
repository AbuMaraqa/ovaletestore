<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Columns the internal traffic report groups by (App\Support\Analytics\StoreVisits), indexed instead of read out of
 * JSON. Written by App\Http\Middleware\TrackStoreVisit through shetabit/visitor (App\Models\StoreVisit).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table(config('visitor.table_name'), function (Blueprint $table) {
            $table->string('path', 255)->nullable()->after('route_name');   // decoded path, no query string
            $table->string('subject', 190)->nullable()->after('path');      // slug of the product/category/brand page
            $table->char('visitor_hash', 16)->nullable()->after('subject'); // daily-rotating, no IP / cookie stored
            $table->char('session_hash', 16)->nullable()->after('visitor_hash');
            $table->string('device_type', 12)->nullable()->after('device'); // desktop / mobile / tablet
            $table->string('channel', 24)->nullable()->after('referer');    // null = navigation inside the store
            $table->string('referrer_host', 120)->nullable()->after('channel');
            $table->string('locale', 5)->nullable()->after('referrer_host');

            $table->index('created_at');
            $table->index(['created_at', 'path']);
            $table->index(['created_at', 'session_hash']);
        });
    }

    public function down(): void
    {
        Schema::table(config('visitor.table_name'), function (Blueprint $table) {
            $table->dropIndex(['created_at']);
            $table->dropIndex(['created_at', 'path']);
            $table->dropIndex(['created_at', 'session_hash']);
            $table->dropColumn(['path', 'subject', 'visitor_hash', 'session_hash', 'device_type', 'channel', 'referrer_host', 'locale']);
        });
    }
};
