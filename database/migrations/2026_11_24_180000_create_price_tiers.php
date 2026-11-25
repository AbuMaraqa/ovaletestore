<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Trader (B2B) pricing: named price tiers ("جملة", "موزع"…). A tier gives an automatic % off the
 * regular price, and any variant may have an exact price in that tier. Approved traders are assigned
 * one tier; orders remember the tier they were priced with.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('price_tiers', function (Blueprint $table) {
            $table->id();
            $table->string('code', 40)->unique();
            $table->json('name'); // {ar, en, he}
            $table->decimal('discount_percent', 5, 2)->default(0);
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('price_tier_prices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('price_tier_id')->constrained()->cascadeOnDelete();
            $table->foreignId('product_variant_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('price'); // minor units
            $table->timestamps();
            $table->unique(['price_tier_id', 'product_variant_id']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->string('trader_status', 16)->nullable()->after('is_active')->index(); // pending | approved | rejected
            $table->string('business_name', 150)->nullable()->after('trader_status');
            $table->timestamp('trader_requested_at')->nullable()->after('business_name');
            $table->foreignId('price_tier_id')->nullable()->after('trader_requested_at')->constrained()->nullOnDelete();
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->foreignId('price_tier_id')->nullable()->after('company_id')->constrained()->nullOnDelete();
        });

        DB::table('price_tiers')->insert([
            'code' => 'wholesale',
            'name' => json_encode(['ar' => 'جملة', 'en' => 'Wholesale', 'he' => 'סיטונאי'], JSON_UNESCAPED_UNICODE),
            'discount_percent' => 0,
            'is_active' => true,
            'sort_order' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::table('orders', fn (Blueprint $table) => $table->dropConstrainedForeignId('price_tier_id'));
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('price_tier_id');
            $table->dropIndex(['trader_status']);
            $table->dropColumn(['trader_status', 'business_name', 'trader_requested_at']);
        });
        Schema::dropIfExists('price_tier_prices');
        Schema::dropIfExists('price_tiers');
    }
};
