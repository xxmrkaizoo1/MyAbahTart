<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Counter (walk-in) sales have no customer account or phone number.
     */
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->change();
            $table->string('phone', 20)->nullable()->change();
            $table->string('channel', 20)->default('online')->after('status')->index();
            $table->string('customer_name', 100)->nullable()->after('user_id');
            $table->string('payment_method', 20)->nullable()->after('total');
            $table->decimal('amount_tendered', 10, 2)->nullable()->after('payment_method');
            $table->foreignId('served_by')->nullable()->after('amount_tendered')->constrained('users')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('served_by');
            $table->dropIndex(['channel']);
            $table->dropColumn(['channel', 'customer_name', 'payment_method', 'amount_tendered']);
        });
    }
};
