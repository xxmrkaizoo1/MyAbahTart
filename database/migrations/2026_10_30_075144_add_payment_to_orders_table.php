<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('payment_status', 20)->nullable()->after('amount_tendered');
            $table->string('billplz_bill_id', 40)->nullable()->unique()->after('payment_status');
            $table->string('payment_url')->nullable()->after('billplz_bill_id');
            $table->timestamp('paid_at')->nullable()->after('payment_url');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropUnique(['billplz_bill_id']);
            $table->dropColumn(['payment_status', 'billplz_bill_id', 'payment_url', 'paid_at']);
        });
    }
};
