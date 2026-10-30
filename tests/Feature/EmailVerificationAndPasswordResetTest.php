<?php

namespace Tests\Feature;

use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

class EmailVerificationAndPasswordResetTest extends TestCase
{
    use RefreshDatabase;

    public function test_signing_up_emails_a_verification_link(): void
    {
        Notification::fake();

        $this->post(route('register'), [
            'name' => 'Nurul Aina',
            'email' => 'nurul@example.com',
            'phone' => '0123456789',
            'password' => 'rahsia-123',
            'password_confirmation' => 'rahsia-123',
        ])->assertRedirect(route('verification.notice'));

        $user = User::firstWhere('email', 'nurul@example.com');
        $this->assertFalse($user->hasVerifiedEmail());
        Notification::assertSentTo($user, VerifyEmail::class, function (VerifyEmail $notification) use ($user) {
            $mail = $notification->toMail($user);

            return $mail->subject === 'Verify your email — '.config('app.name') && $mail->actionText === 'Verify my email';
        });

        $this->get(route('verification.notice'))->assertOk()->assertSee('Check your inbox')->assertSee('nurul@example.com');
    }

    public function test_unverified_customers_cannot_order_until_they_verify(): void
    {
        $customer = User::factory()->unverified()->create();
        $product = Product::factory()->create(['price' => 20, 'stock' => 5]);

        $this->actingAs($customer)->post(route('cart.store', $product));
        $this->get(route('home'))->assertSee('Please verify your email to place orders.');

        $this->post(route('checkout.store'), ['delivery_method' => 'pickup', 'phone' => '0123456789'])
            ->assertRedirect(route('verification.notice'));
        $this->get(route('orders.index'))->assertRedirect(route('verification.notice'));
        $this->get(route('profile.edit'))->assertOk();
        $this->assertSame(0, Order::count());
    }

    public function test_the_emailed_link_verifies_the_account(): void
    {
        $customer = User::factory()->unverified()->create();
        $link = URL::temporarySignedRoute('verification.verify', now()->addHour(), ['id' => $customer->id, 'hash' => sha1($customer->email)]);

        $this->actingAs($customer)->get($link)->assertRedirect(route('home'));

        $this->assertTrue($customer->fresh()->hasVerifiedEmail());
        $this->get(route('orders.index'))->assertOk();
    }

    public function test_a_tampered_verification_link_is_refused(): void
    {
        $customer = User::factory()->unverified()->create();
        $link = URL::temporarySignedRoute('verification.verify', now()->addHour(), ['id' => $customer->id, 'hash' => sha1('someone@else.com')]);

        $this->actingAs($customer)->get($link)->assertForbidden();
        $this->assertFalse($customer->fresh()->hasVerifiedEmail());
    }

    public function test_customers_can_ask_for_a_new_verification_link(): void
    {
        Notification::fake();
        $customer = User::factory()->unverified()->create();

        $this->actingAs($customer)->post(route('verification.send'))->assertSessionHas('status');

        Notification::assertSentTo($customer, VerifyEmail::class);
    }

    public function test_changing_email_on_the_profile_needs_verifying_again(): void
    {
        Notification::fake();
        $customer = User::factory()->create(['phone' => '0123456789']);

        $this->actingAs($customer)->put(route('profile.update'), [
            'name' => $customer->name,
            'email' => 'new-address@example.com',
            'phone' => '0123456789',
        ])->assertRedirect(route('verification.notice'));

        $this->assertFalse($customer->fresh()->hasVerifiedEmail());
        Notification::assertSentTo($customer, VerifyEmail::class);
    }

    public function test_forgot_password_emails_a_reset_link(): void
    {
        Notification::fake();
        $customer = User::factory()->create(['email' => 'aisyah@example.com']);

        $this->get(route('login'))->assertSee('Forgot password?');
        $this->get(route('password.request'))->assertOk()->assertSee('Forgot your password?');

        $this->post(route('password.email'), ['email' => 'Aisyah@Example.com'])->assertSessionHas('status');

        Notification::assertSentTo($customer, ResetPassword::class, function (ResetPassword $notification) use ($customer) {
            return str_contains($notification->toMail($customer)->actionUrl, route('password.reset', $notification->token));
        });
    }

    public function test_forgot_password_does_not_reveal_who_has_an_account(): void
    {
        Notification::fake();

        $this->post(route('password.email'), ['email' => 'nobody@example.com'])
            ->assertSessionHasNoErrors()
            ->assertSessionHas('status', 'If that email has an account, a reset link is on its way. Check your inbox and spam folder.');

        Notification::assertNothingSent();
    }

    public function test_the_reset_link_sets_a_new_password(): void
    {
        $customer = User::factory()->unverified()->create(['email' => 'aisyah@example.com']);
        $token = Password::createToken($customer);

        $this->get(route('password.reset', ['token' => $token, 'email' => $customer->email]))->assertOk()->assertSee('Choose a new password');

        $this->post(route('password.update'), [
            'token' => $token,
            'email' => 'aisyah@example.com',
            'password' => 'baru-rahsia-456',
            'password_confirmation' => 'baru-rahsia-456',
        ])->assertRedirect(route('login'));

        $customer->refresh();
        $this->assertTrue(Hash::check('baru-rahsia-456', $customer->password));
        $this->assertTrue($customer->hasVerifiedEmail());

        $this->post(route('login'), ['login' => 'aisyah@example.com', 'password' => 'baru-rahsia-456'])->assertRedirect();
        $this->assertAuthenticatedAs($customer);
    }

    public function test_an_invalid_reset_link_is_refused(): void
    {
        $customer = User::factory()->create(['email' => 'aisyah@example.com']);

        $this->post(route('password.update'), [
            'token' => 'not-a-real-token',
            'email' => 'aisyah@example.com',
            'password' => 'baru-rahsia-456',
            'password_confirmation' => 'baru-rahsia-456',
        ])->assertSessionHasErrors(['email' => 'This reset link is invalid or has expired. Please ask for a new one.']);

        $this->assertFalse(Hash::check('baru-rahsia-456', $customer->fresh()->password));
    }
}
