<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations. Products already sold in sizes have their shared stock split evenly across those sizes.
     */
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->json('size_stock')->nullable()->after('stock');
        });

        DB::table('products')->whereNotNull('size_prices')->get(['id', 'size_prices', 'stock'])->each(function (object $product) {
            $sizes = array_values(array_intersect(['S', 'M', 'L'], array_keys((array) json_decode($product->size_prices, true))));

            if ($sizes === []) {
                return;
            }

            $share = intdiv($product->stock, count($sizes));
            $remainder = $product->stock % count($sizes);
            $sizeStock = [];

            foreach ($sizes as $index => $size) {
                $sizeStock[$size] = $share + ($index < $remainder ? 1 : 0);
            }

            DB::table('products')->where('id', $product->id)->update(['size_stock' => json_encode($sizeStock)]);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn('size_stock');
        });
    }
};
