<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * B2B sales through the iPad POS: sales reps place orders for companies (on account).
 * A company's balance = its non-cancelled on-account order totals − recorded payments.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('companies', function (Blueprint $table) {
            $table->id();
            $table->string('name', 160);
            $table->string('contact_name', 120)->nullable();
            $table->string('phone', 32)->nullable();
            $table->string('city', 120)->nullable();
            $table->string('address', 500)->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['is_active', 'name']);
        });

        Schema::create('company_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('amount'); // minor units
            $table->string('method', 20); // App\Enums\CompanyPaymentMethod
            $table->string('reference', 120)->nullable(); // cheque / transfer number
            $table->string('note', 500)->nullable();
            $table->date('paid_at');
            $table->foreignId('recorded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['company_id', 'paid_at']);
        });

        Schema::table('orders', function (Blueprint $table) {
            // restrict: a company with orders is deactivated, never deleted (its statement must survive).
            $table->foreignId('company_id')->nullable()->after('user_id')->constrained()->restrictOnDelete();
            $table->foreignId('sales_rep_id')->nullable()->after('company_id')->constrained('users')->nullOnDelete();
            $table->string('channel', 10)->default('web')->after('status'); // App\Enums\OrderChannel
            $table->string('payment_method', 20)->default('cod')->after('channel'); // App\Enums\PaymentMethod
            $table->string('customer_phone', 32)->nullable()->change();

            $table->index(['company_id', 'status']);
            $table->index(['sales_rep_id', 'placed_at']);
        });
    }

    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex(['company_id', 'status']);
            $table->dropIndex(['sales_rep_id', 'placed_at']);
            $table->dropConstrainedForeignId('company_id');
            $table->dropConstrainedForeignId('sales_rep_id');
            $table->dropColumn(['channel', 'payment_method']);
        });
        Schema::dropIfExists('company_payments');
        Schema::dropIfExists('companies');
    }
};
