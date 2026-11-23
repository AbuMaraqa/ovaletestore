<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Stable `code` per shipping method so ShippingMethodSeeder can create the regional methods
 * (الداخل / الضفة الغربية / القدس / pickup) once, without touching prices the admin later edits.
 * The generic "home delivery" method from the first shipping migration is replaced by the regions.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('shipping_methods', function (Blueprint $table) {
            $table->string('code', 40)->nullable()->unique()->after('id');
        });

        DB::table('shipping_methods')->where('type', 'pickup')->limit(1)->update(['code' => 'pickup', 'sort_order' => 3]); // after the three regions

        // Generic delivery → removed if never used, otherwise kept (inactive) for its past orders.
        foreach (DB::table('shipping_methods')->where('type', 'delivery')->whereNull('code')->pluck('id') as $id) {
            DB::table('orders')->where('shipping_method_id', $id)->exists()
                ? DB::table('shipping_methods')->where('id', $id)->update(['is_active' => false])
                : DB::table('shipping_methods')->where('id', $id)->delete();
        }
    }

    public function down(): void
    {
        Schema::table('shipping_methods', function (Blueprint $table) {
            $table->dropUnique(['code']);
            $table->dropColumn('code');
        });
    }
};
