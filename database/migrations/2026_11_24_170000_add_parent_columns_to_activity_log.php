<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * "History of this product" = its own entries + those of its children (variants, translations…).
 * Real indexed columns instead of JSON lookups, so the side panel stays fast as the log grows.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('activity_log', function (Blueprint $table) {
            $table->string('parent_type')->nullable()->after('subject_id');
            $table->unsignedBigInteger('parent_id')->nullable()->after('parent_type');
            $table->index(['parent_type', 'parent_id']);
            $table->index(['log_name', 'id']);
            $table->index('event');
        });

        // Backfill entries written before the columns existed (parent kept in properties JSON).
        DB::table('activity_log')->whereNotNull('properties')->orderBy('id')->chunkById(500, function ($rows) {
            foreach ($rows as $row) {
                $props = json_decode((string) $row->properties, true) ?: [];
                if (isset($props['parent_type'], $props['parent_id'])) {
                    DB::table('activity_log')->where('id', $row->id)->update(['parent_type' => $props['parent_type'], 'parent_id' => $props['parent_id']]);
                }
            }
        });
    }

    public function down(): void
    {
        Schema::table('activity_log', function (Blueprint $table) {
            $table->dropIndex(['parent_type', 'parent_id']);
            $table->dropIndex(['log_name', 'id']);
            $table->dropIndex(['event']);
            $table->dropColumn(['parent_type', 'parent_id']);
        });
    }
};
