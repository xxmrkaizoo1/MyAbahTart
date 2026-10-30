<?php

namespace App\Providers;

use App\Enums\Theme;
use App\Models\Category;
use App\Models\Order;
use App\Models\Refund;
use App\Services\Cart;
use App\Services\Storefront;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Illuminate\View\View as ViewInstance;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->scoped(Cart::class);
        $this->app->scoped(Storefront::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Blade::directive('rm', fn (string $expression) => "<?php echo 'RM '.number_format((float) ({$expression}), 2); ?>");

        View::composer('components.layouts.shop', function (ViewInstance $view) {
            $cart = $this->app->make(Cart::class);

            $view->with([
                'cartCount' => $cart->count(),
                'cartSubtotal' => $cart->count() > 0 ? $cart->subtotal() : 0.0,
                'navCategories' => Category::query()->orderBy('sort_order')->get(),
            ]);
        });

        View::composer(['components.layouts.shop', 'shop.home'], function (ViewInstance $view) {
            $view->with('storefront', $this->app->make(Storefront::class));
        });

        View::composer(['components.layouts.shop', 'components.layouts.admin', 'components.layouts.auth'], function (ViewInstance $view) {
            $view->with('theme', Theme::current(request()));
        });

        View::composer('components.layouts.admin', function (ViewInstance $view) {
            $view->with([
                'activeOrderCount' => Order::query()->active()->count(),
                'pendingRefundCount' => Refund::query()->pending()->count(),
            ]);
        });
    }
}
