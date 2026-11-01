<?php

use App\Http\Controllers\Admin;
use App\Http\Controllers\Api;
use Illuminate\Support\Facades\Route;

/*
| The admin mobile app. Reads come from the Api\Admin controllers, which return the same
| data as the admin panel pages; every change goes through the admin panel's own
| controllers, so the app follows exactly the same rules.
*/

Route::prefix('admin')->name('api.admin.')->middleware('json')->group(function () {
    Route::post('/login', [Api\AuthController::class, 'store'])->middleware('throttle:10,1')->name('login');

    Route::middleware(['auth:api', 'admin'])->group(function () {
        Route::get('/me', [Api\AuthController::class, 'show'])->name('me');
        Route::post('/logout', [Api\AuthController::class, 'destroy'])->name('logout');
        Route::get('/options', [Api\Admin\SettingsController::class, 'options'])->name('options');

        Route::get('/dashboard', Api\Admin\DashboardController::class)->name('dashboard');

        Route::get('/orders', [Api\Admin\OrderController::class, 'index'])->name('orders.index');
        Route::get('/orders/{order}', [Api\Admin\OrderController::class, 'show'])->name('orders.show');
        Route::patch('/orders/{order}/status', [Admin\OrderController::class, 'update'])->name('orders.update');

        Route::get('/products', [Api\Admin\ProductController::class, 'index'])->name('products.index');
        Route::get('/products/{product}', [Api\Admin\ProductController::class, 'show'])->name('products.show');
        Route::post('/products', [Admin\ProductController::class, 'store'])->name('products.store');
        // POST rather than PUT so photos can be sent as multipart form data.
        Route::post('/products/{product}', [Admin\ProductController::class, 'update'])->name('products.update');
        Route::delete('/products/{product}', [Admin\ProductController::class, 'destroy'])->name('products.destroy');
        Route::patch('/products/{product}/stock', [Admin\ProductController::class, 'updateStock'])->name('products.stock');
        Route::post('/products/{product}/restock', [Admin\ProductController::class, 'restock'])->name('products.restock');

        Route::get('/collections', [Api\Admin\CategoryController::class, 'index'])->name('categories.index');
        Route::post('/collections', [Admin\CategoryController::class, 'store'])->name('categories.store');
        Route::post('/collections/{category}', [Admin\CategoryController::class, 'update'])->name('categories.update');
        Route::delete('/collections/{category}', [Admin\CategoryController::class, 'destroy'])->name('categories.destroy');

        Route::get('/sales', Api\Admin\SalesReportController::class)->name('sales.index');

        Route::get('/storefront', [Api\Admin\SettingsController::class, 'storefront'])->name('storefront.edit');
        Route::post('/storefront', [Admin\StorefrontController::class, 'update'])->name('storefront.update');

        Route::delete('/reviews/{review}', [Admin\ReviewController::class, 'destroy'])->name('reviews.destroy');

        Route::get('/delivery', [Api\Admin\SettingsController::class, 'delivery'])->name('delivery.edit');
        Route::put('/delivery', [Admin\DeliveryController::class, 'update'])->name('delivery.update');

        Route::get('/pos', [Api\Admin\PosController::class, 'index'])->name('pos.index');
        Route::post('/pos/sales', [Admin\PosController::class, 'store'])->name('pos.store');
        Route::get('/pos/sales/{order}', [Api\Admin\PosController::class, 'receipt'])->name('pos.receipt');
        Route::get('/pos/menu', [Api\Admin\PosController::class, 'menu'])->name('pos.menu');
        Route::put('/pos/menu', [Admin\CounterMenuController::class, 'update'])->name('pos.menu.update');

        Route::get('/refunds', [Api\Admin\RefundController::class, 'index'])->name('refunds.index');
        Route::post('/orders/{order}/refunds', [Admin\RefundController::class, 'store'])->name('refunds.store');
        Route::patch('/refunds/{refund}', [Admin\RefundController::class, 'update'])->name('refunds.update');

        Route::get('/vouchers', [Api\Admin\VoucherController::class, 'index'])->name('vouchers.index');
        Route::post('/vouchers', [Admin\VoucherController::class, 'store'])->name('vouchers.store');
        Route::patch('/vouchers/{voucher}', [Admin\VoucherController::class, 'update'])->name('vouchers.update');
        Route::delete('/vouchers/{voucher}', [Admin\VoucherController::class, 'destroy'])->name('vouchers.destroy');
    });
});
