<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * WooCommerce-style shipping methods (replaces the single store.shipping_fee setting):
 * delivery (flat cost, optionally free above a subtotal) or store pickup.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shipping_methods', function (Blueprint $table) {
            $table->id();
            $table->string('type', 20); // App\Enums\ShippingMethodType
            $table->unsignedInteger('cost')->default(0); // minor units
            $table->unsignedInteger('free_over')->nullable(); // free when subtotal >= this
            $table->unsignedInteger('min_order')->nullable(); // offered only when subtotal >= this
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('shipping_method_translations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('shipping_method_id')->constrained()->cascadeOnDelete();
            $table->string('locale', 5);
            $table->string('name', 120);
            $table->string('description', 255)->nullable();
            $table->unique(['shipping_method_id', 'locale']);
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->foreignId('shipping_method_id')->nullable()->after('shipping_address')->constrained()->nullOnDelete();
            $table->string('shipping_method_name', 120)->nullable()->after('shipping_method_id');
            $table->string('shipping_city', 120)->nullable()->change();
            $table->string('shipping_address', 500)->nullable()->change();
        });

        $this->seedDefaults();
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('shipping_method_id');
            $table->dropColumn('shipping_method_name');
        });
        Schema::dropIfExists('shipping_method_translations');
        Schema::dropIfExists('shipping_methods');
    }

    /** Carries the old flat fee over as the default delivery method, plus free store pickup. */
    private function seedDefaults(): void
    {
        $payload = DB::table('settings')->where('group', 'store')->where('name', 'shipping_fee')->value('payload');
        $fee = $payload !== null ? (int) json_decode((string) $payload, true) : 0;

        $methods = [
            ['delivery', $fee, ['ar' => 'توصيل للمنزل', 'en' => 'Home delivery', 'he' => 'משלוח עד הבית'],
                ['ar' => 'نوصل طلبك إلى عنوانك', 'en' => 'We deliver your order to your address', 'he' => 'נשלח את ההזמנה לכתובת שלך']],
            ['pickup', 0, ['ar' => 'استلام من المحل', 'en' => 'Store pickup', 'he' => 'איסוף עצמי מהחנות'],
                ['ar' => 'استلم طلبك من محلنا بدون رسوم', 'en' => 'Collect your order from our store for free', 'he' => 'אסוף את ההזמנה מהחנות ללא עלות']],
        ];

        foreach ($methods as $i => [$type, $cost, $names, $descriptions]) {
            $id = DB::table('shipping_methods')->insertGetId([
                'type' => $type, 'cost' => $cost, 'is_active' => true, 'sort_order' => $i,
                'created_at' => now(), 'updated_at' => now(),
            ]);
            foreach ($names as $locale => $name) {
                DB::table('shipping_method_translations')->insert([
                    'shipping_method_id' => $id, 'locale' => $locale, 'name' => $name, 'description' => $descriptions[$locale],
                ]);
            }
        }
    }
};
