<?php

use App\Http\Controllers\Admin;
use App\Http\Controllers\Auth\EmailVerificationController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\PasswordResetController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\BillplzController;
use App\Http\Controllers\CartController;
use App\Http\Controllers\CheckoutController;
use App\Http\Controllers\DeliveryQuoteController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\LiveController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\RefundController;
use App\Http\Controllers\ReviewController;
use App\Http\Controllers\VoucherController;
use Illuminate\Support\Facades\Route;

Route::get('/', HomeController::class)->name('home');
Route::get('/menu', [ProductController::class, 'index'])->name('products.index');
Route::get('/menu/{product}', [ProductController::class, 'show'])->name('products.show');

Route::get('/payments/billplz/return', [BillplzController::class, 'return'])->name('payments.billplz.return');
Route::post('/payments/billplz/callback', [BillplzController::class, 'callback'])->name('payments.billplz.callback');

Route::get('/cart', [CartController::class, 'index'])->name('cart.index');
Route::post('/cart/{product}', [CartController::class, 'store'])->name('cart.store');
Route::patch('/cart/{product}', [CartController::class, 'update'])->name('cart.update');
Route::delete('/cart/{product}', [CartController::class, 'destroy'])->name('cart.destroy');
Route::post('/delivery-quote', DeliveryQuoteController::class)->middleware('throttle:30,1')->name('delivery.quote');
Route::post('/cart-voucher', [VoucherController::class, 'store'])->name('cart.voucher.store');
Route::delete('/cart-voucher', [VoucherController::class, 'destroy'])->name('cart.voucher.destroy');

Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'create'])->name('login');
    Route::post('/login', [LoginController::class, 'store'])->middleware('throttle:10,1');
    Route::get('/register', [RegisterController::class, 'create'])->name('register');
    Route::post('/register', [RegisterController::class, 'store']);

    Route::get('/forgot-password', [PasswordResetController::class, 'create'])->name('password.request');
    Route::post('/forgot-password', [PasswordResetController::class, 'store'])->middleware('throttle:6,1')->name('password.email');
    Route::get('/reset-password/{token}', [PasswordResetController::class, 'edit'])->name('password.reset');
    Route::post('/reset-password', [PasswordResetController::class, 'update'])->middleware('throttle:6,1')->name('password.update');
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [LoginController::class, 'destroy'])->name('logout');

    Route::get('/live', LiveController::class)->middleware('throttle:120,1')->name('live');

    Route::get('/email/verify', [EmailVerificationController::class, 'notice'])->name('verification.notice');
    Route::get('/email/verify/{id}/{hash}', [EmailVerificationController::class, 'verify'])->middleware(['signed', 'throttle:6,1'])->name('verification.verify');
    Route::post('/email/verification-notification', [EmailVerificationController::class, 'send'])->middleware('throttle:3,1')->name('verification.send');

    // Customers can still fix a mistyped email on their profile before verifying.
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::put('/profile', [ProfileController::class, 'update'])->name('profile.update');

    Route::middleware('verified')->group(function () {
        Route::post('/checkout', [CheckoutController::class, 'store'])->name('checkout.store');

        Route::get('/orders', [OrderController::class, 'index'])->name('orders.index');
        Route::get('/orders/{order}', [OrderController::class, 'show'])->name('orders.show');
        Route::post('/orders/{order}/cancel', [OrderController::class, 'cancel'])->name('orders.cancel');
        Route::post('/orders/{order}/reorder', [OrderController::class, 'reorder'])->name('orders.reorder');
        Route::post('/orders/{order}/refund', [RefundController::class, 'store'])->name('refunds.store');

        Route::post('/menu/{product}/reviews', [ReviewController::class, 'store'])->name('reviews.store');
    });
});

Route::middleware(['auth', 'admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/', Admin\DashboardController::class)->name('dashboard');

    Route::get('/orders', [Admin\OrderController::class, 'index'])->name('orders.index');
    Route::get('/orders/{order}', [Admin\OrderController::class, 'show'])->name('orders.show');
    Route::patch('/orders/{order}/status', [Admin\OrderController::class, 'update'])->name('orders.update');

    Route::resource('products', Admin\ProductController::class)->except('show');
    Route::patch('/products/{product}/stock', [Admin\ProductController::class, 'updateStock'])->name('products.stock');
    Route::post('/products/{product}/restock', [Admin\ProductController::class, 'restock'])->name('products.restock');

    Route::get('/collections', [Admin\CategoryController::class, 'index'])->name('categories.index');
    Route::post('/collections', [Admin\CategoryController::class, 'store'])->name('categories.store');
    Route::patch('/collections/{category}', [Admin\CategoryController::class, 'update'])->name('categories.update');
    Route::delete('/collections/{category}', [Admin\CategoryController::class, 'destroy'])->name('categories.destroy');

    Route::get('/sales', Admin\SalesReportController::class)->name('sales.index');

    Route::get('/storefront', [Admin\StorefrontController::class, 'edit'])->name('storefront.edit');
    Route::put('/storefront', [Admin\StorefrontController::class, 'update'])->name('storefront.update');

    Route::delete('/reviews/{review}', [Admin\ReviewController::class, 'destroy'])->name('reviews.destroy');

    Route::get('/delivery', [Admin\DeliveryController::class, 'edit'])->name('delivery.edit');
    Route::put('/delivery', [Admin\DeliveryController::class, 'update'])->name('delivery.update');

    Route::get('/pos', [Admin\PosController::class, 'index'])->name('pos.index');
    Route::post('/pos/sales', [Admin\PosController::class, 'store'])->name('pos.store');
    Route::get('/pos/sales/{order}', [Admin\PosController::class, 'receipt'])->name('pos.receipt');
    Route::get('/pos/menu', [Admin\CounterMenuController::class, 'edit'])->name('pos.menu');
    Route::put('/pos/menu', [Admin\CounterMenuController::class, 'update'])->name('pos.menu.update');

    Route::get('/refunds', [Admin\RefundController::class, 'index'])->name('refunds.index');
    Route::post('/orders/{order}/refunds', [Admin\RefundController::class, 'store'])->name('refunds.store');
    Route::patch('/refunds/{refund}', [Admin\RefundController::class, 'update'])->name('refunds.update');

    Route::get('/vouchers', [Admin\VoucherController::class, 'index'])->name('vouchers.index');
    Route::post('/vouchers', [Admin\VoucherController::class, 'store'])->name('vouchers.store');
    Route::patch('/vouchers/{voucher}', [Admin\VoucherController::class, 'update'])->name('vouchers.update');
    Route::delete('/vouchers/{voucher}', [Admin\VoucherController::class, 'destroy'])->name('vouchers.destroy');
});
