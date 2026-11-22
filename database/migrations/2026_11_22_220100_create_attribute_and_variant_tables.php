<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Global attribute when product_id is null; custom (product-local) attribute otherwise.
        Schema::create('attributes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('code', 64);
            $table->string('display_type', 20)->default('button'); // App\Enums\AttributeDisplayType
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['product_id', 'code']);
        });

        Schema::create('attribute_translations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('attribute_id')->constrained()->cascadeOnDelete();
            $table->string('locale', 5);
            $table->string('name');

            $table->unique(['attribute_id', 'locale']);
        });

        Schema::create('attribute_terms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('attribute_id')->constrained()->cascadeOnDelete();
            $table->string('code', 64);
            $table->string('color_hex', 7)->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['attribute_id', 'code']);
        });

        Schema::create('attribute_term_translations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('attribute_term_id')->constrained()->cascadeOnDelete();
            $table->string('locale', 5);
            $table->string('name');

            $table->unique(['attribute_term_id', 'locale']);
        });

        Schema::create('product_attributes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->foreignId('attribute_id')->constrained()->cascadeOnDelete();
            $table->boolean('used_for_variations')->default(false);
            $table->boolean('is_visible')->default(true);
            $table->unsignedInteger('sort_order')->default(0);

            $table->unique(['product_id', 'attribute_id']);
        });

        Schema::create('product_attribute_term', function (Blueprint $table) {
            $table->foreignId('product_attribute_id')->constrained()->cascadeOnDelete();
            $table->foreignId('attribute_term_id')->constrained()->cascadeOnDelete();

            $table->primary(['product_attribute_id', 'attribute_term_id']);
        });

        // The purchasable unit. A simple product has exactly one variant with an empty signature.
        Schema::create('product_variants', function (Blueprint $table) {
            $table->id();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('signature', 191)->default('');   // sorted term ids, e.g. "3-17"
            $table->string('sku', 64)->nullable()->unique();
            $table->unsignedBigInteger('regular_price');      // minor units (agorot)
            $table->unsignedBigInteger('sale_price')->nullable();
            $table->timestamp('sale_starts_at')->nullable();
            $table->timestamp('sale_ends_at')->nullable();
            $table->boolean('manage_stock')->default(false);
            $table->integer('stock_quantity')->nullable();
            $table->string('stock_status', 20)->default('in_stock'); // App\Enums\StockStatus
            $table->unsignedInteger('weight_grams')->nullable();
            $table->unsignedInteger('length_mm')->nullable();
            $table->unsignedInteger('width_mm')->nullable();
            $table->unsignedInteger('height_mm')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_default')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['product_id', 'signature']);
            $table->index(['product_id', 'is_active']);
            $table->index('regular_price');
        });

        Schema::create('product_variant_term', function (Blueprint $table) {
            $table->foreignId('product_variant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('attribute_term_id')->constrained()->cascadeOnDelete();

            $table->primary(['product_variant_id', 'attribute_term_id']);
            $table->index('attribute_term_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_variant_term');
        Schema::dropIfExists('product_variants');
        Schema::dropIfExists('product_attribute_term');
        Schema::dropIfExists('product_attributes');
        Schema::dropIfExists('attribute_term_translations');
        Schema::dropIfExists('attribute_terms');
        Schema::dropIfExists('attribute_translations');
        Schema::dropIfExists('attributes');
    }
};
