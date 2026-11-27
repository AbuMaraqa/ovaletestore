<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * One invoice per order, issued when the order is confirmed (App\Actions\Invoices\IssueInvoice).
 * Numbers are sequential per year (INV-2026-00001); the PDF is rendered on demand from the order.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('order_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('number', 32)->unique();
            $table->unsignedSmallInteger('year');
            $table->unsignedInteger('sequence');
            $table->string('issued_on_status', 20); // the status change that issued it (its email carries the PDF)
            $table->timestamp('issued_at');
            $table->timestamps();

            $table->unique(['year', 'sequence']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invoices');
    }
};
