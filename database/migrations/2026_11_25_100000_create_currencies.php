<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Store currencies. One row is the base (default) currency: every price, cost and balance in the
 * database is stored in it. Every other currency has a rate = how many of its units buy 1 base unit.
 *
 * Orders keep the currency the customer paid in plus the rate used, and `base_total` (the same total
 * in the base currency) so reports never mix currencies.
 */
return new class extends Migration
{
    private const NAMES = [
        'ILS' => ['ar' => 'شيكل', 'en' => 'Israeli Shekel', 'he' => 'שקל חדש'],
        'USD' => ['ar' => 'دولار أمريكي', 'en' => 'US Dollar', 'he' => 'דולר אמריקאי'],
        'JOD' => ['ar' => 'دينار أردني', 'en' => 'Jordanian Dinar', 'he' => 'דינר ירדני'],
    ];

    public function up(): void
    {
        Schema::create('currencies', function (Blueprint $table) {
            $table->id();
            $table->char('code', 3)->unique();
            $table->json('name');
            $table->decimal('rate', 18, 8)->default(1); // units of this currency per 1 base unit
            $table->boolean('is_default')->default(false);
            $table->boolean('is_active')->default(true);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->decimal('exchange_rate', 18, 8)->default(1)->after('currency');
            $table->unsignedBigInteger('base_total')->default(0)->after('total');
        });
        DB::table('orders')->update(['base_total' => DB::raw('total')]);

        // The base currency is the one the store was already using (Settings → Store).
        $payload = DB::table('settings')->where('group', 'store')->where('name', 'currency')->value('payload');
        $code = is_string($payload) ? (string) json_decode($payload) : '';
        $code = preg_match('/^[A-Z]{3}$/', $code) ? $code : 'ILS';

        DB::table('currencies')->insert([
            'code' => $code,
            'name' => json_encode(self::NAMES[$code] ?? ['ar' => $code], JSON_UNESCAPED_UNICODE),
            'rate' => 1,
            'is_default' => true,
            'is_active' => true,
            'sort_order' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropColumn(['exchange_rate', 'base_total']);
        });
        Schema::dropIfExists('currencies');
    }
};
