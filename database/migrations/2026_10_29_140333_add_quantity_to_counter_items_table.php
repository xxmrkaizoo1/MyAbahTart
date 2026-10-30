<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * How many units of the product are put out for sale at the counter that day.
     * Null means no limit: the counter can sell from the full stock.
     */
    public function up(): void
    {
        Schema::table('counter_items', function (Blueprint $table) {
            $table->unsignedInteger('quantity')->nullable()->after('product_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('counter_items', function (Blueprint $table) {
            $table->dropColumn('quantity');
        });
    }
};
