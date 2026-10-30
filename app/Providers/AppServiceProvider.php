<?php

namespace App\Providers;

use App\Enums\Theme;
use App\Models\Category;
use App\Models\Order;
use App\Models\Refund;
use App\Services\Cart;
use App\Services\Storefront;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\URL;
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
        // Behind a hosting proxy the request can look like plain http; keep links and assets on https.
        if ($this->app->isProduction() && str_starts_with((string) config('app.url'), 'https://')) {
            URL::forceScheme('https');
        }

        VerifyEmail::toMailUsing(fn (object $notifiable, string $url) => (new MailMessage)
            ->subject('Verify your email — '.config('app.name'))
            ->greeting('Hi '.$notifiable->firstName().',')
            ->line('Thanks for signing up! Please confirm this is your email so you can start ordering.')
            ->action('Verify my email', $url)
            ->line('The link works for '.config('auth.verification.expire', 60).' minutes. If you didn’t create an account, you can ignore this email.'));

        ResetPassword::toMailUsing(fn (object $notifiable, string $token) => (new MailMessage)
            ->subject('Reset your password — '.config('app.name'))
            ->greeting('Hi '.$notifiable->firstName().',')
            ->line('We got a request to reset the password for your account.')
            ->action('Choose a new password', route('password.reset', ['token' => $token, 'email' => $notifiable->getEmailForPasswordReset()]))
            ->line('The link works for '.config('auth.passwords.users.expire').' minutes. If you didn’t ask for this, your password stays the same and you can ignore this email.'));

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
