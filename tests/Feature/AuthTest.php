<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_customer_can_register_and_the_phone_number_is_normalised(): void
    {
        $response = $this->post(route('register'), [
            'name' => 'Nurul Aina',
            'email' => 'Nurul@Example.com',
            'phone' => '012-345 6789',
            'password' => 'rahsia-123',
            'password_confirmation' => 'rahsia-123',
        ]);

        $response->assertRedirect(route('home'));
        $this->assertAuthenticated();

        $user = User::firstWhere('email', 'nurul@example.com');
        $this->assertSame('0123456789', $user->phone);
        $this->assertFalse($user->isAdmin());
    }

    public function test_registration_cannot_set_the_admin_role(): void
    {
        $this->post(route('register'), [
            'name' => 'Penceroboh',
            'email' => 'evil@example.com',
            'phone' => '0111111111',
            'password' => 'rahsia-123',
            'password_confirmation' => 'rahsia-123',
            'role' => 'admin',
        ]);

        $this->assertFalse(User::firstWhere('email', 'evil@example.com')->fresh()->isAdmin());
    }

    public function test_a_customer_can_log_in_with_their_phone_number(): void
    {
        User::factory()->create(['phone' => '0198765432']);

        $this->post(route('login'), ['login' => '019-876 5432', 'password' => 'password'])
            ->assertRedirect(route('home'));

        $this->assertAuthenticated();
    }

    public function test_an_admin_is_sent_to_the_dashboard_after_logging_in(): void
    {
        $admin = User::factory()->admin()->create();

        $this->post(route('login'), ['login' => $admin->email, 'password' => 'password'])
            ->assertRedirect(route('admin.dashboard'));
    }

    public function test_wrong_credentials_are_rejected(): void
    {
        $user = User::factory()->create();

        $this->post(route('login'), ['login' => $user->email, 'password' => 'salah'])
            ->assertSessionHasErrors('login');

        $this->assertGuest();
    }

    public function test_customers_cannot_open_the_admin_portal(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('admin.dashboard'))
            ->assertForbidden();
    }

    public function test_guests_are_sent_to_login_from_the_admin_portal(): void
    {
        $this->get(route('admin.dashboard'))->assertRedirect(route('login'));
    }
}
