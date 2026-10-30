<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Storefront;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class StorefrontTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_shop_uses_the_default_wording_until_it_is_changed(): void
    {
        $this->get(route('home'))
            ->assertOk()
            ->assertSee('Tarts worth')
            ->assertSee('From our shop to your table')
            ->assertSee('Daily, 9 am – 9 pm');
    }

    public function test_an_admin_can_edit_what_customers_see(): void
    {
        Storage::fake('public');

        $this->actingAs(User::factory()->admin()->create())
            ->put(route('admin.storefront.update'), $this->form([
                'hero_title_start' => 'Raya tarts',
                'hero_title_highlight' => 'worth',
                'hero_title_end' => 'the wait.',
                'hero_badge' => '',
                'ticker' => "Raya pre-orders open\nFree gift box",
                'announcement' => 'Closed on Friday',
                'opening_hours' => 'Mon – Sat, 10 am – 6 pm',
                'hero_image_file' => UploadedFile::fake()->image('hero.jpg'),
            ]))
            ->assertRedirect()
            ->assertSessionHas('status');

        $path = app(Storefront::class)->get('hero_image');
        Storage::disk('public')->assertExists($path);

        $this->get(route('home'))
            ->assertOk()
            ->assertSee('Raya tarts')
            ->assertSee('the wait.')
            ->assertSee('Raya pre-orders open')
            ->assertSee('Closed on Friday')
            ->assertSee('Mon – Sat, 10 am – 6 pm')
            ->assertSee($path)
            ->assertDontSee('Tarts worth')
            ->assertDontSee('Prepared fresh for every order');
    }

    public function test_the_hero_picture_can_be_removed(): void
    {
        Storage::fake('public');
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->put(route('admin.storefront.update'), $this->form(['hero_image_file' => UploadedFile::fake()->image('hero.jpg')]));
        $path = app(Storefront::class)->get('hero_image');

        $this->actingAs($admin)->put(route('admin.storefront.update'), $this->form(['remove_hero_image' => '1']));

        Storage::disk('public')->assertMissing($path);
        $this->assertNull(app(Storefront::class)->heroImageUrl());
    }

    public function test_the_storefront_editor_is_for_admins_only(): void
    {
        $this->actingAs(User::factory()->admin()->create())->get(route('admin.storefront.edit'))->assertOk()->assertSee('Home page heading');

        $this->actingAs(User::factory()->create())->get(route('admin.storefront.edit'))->assertForbidden();
        $this->actingAs(User::factory()->create())->put(route('admin.storefront.update'), $this->form())->assertForbidden();
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function form(array $overrides = []): array
    {
        return [...Storefront::DEFAULTS, ...$overrides];
    }
}
