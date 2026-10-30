<?php

namespace Tests\Feature;

use App\Enums\Theme;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ThemeTest extends TestCase
{
    use RefreshDatabase;

    public function test_pages_use_the_matcha_theme_by_default(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertSee('data-theme="matcha"', false)
            ->assertSee('data-theme-choice="raspberry"', false);
    }

    public function test_the_theme_cookie_selects_the_raspberry_theme(): void
    {
        $this->withUnencryptedCookie(Theme::COOKIE, 'raspberry')
            ->get(route('home'))
            ->assertOk()
            ->assertSee('data-theme="raspberry"', false)
            ->assertSee('content="'.Theme::Raspberry->metaColor().'"', false);
    }

    public function test_the_admin_portal_follows_the_chosen_theme(): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->withUnencryptedCookie(Theme::COOKIE, 'raspberry')
            ->get(route('admin.vouchers.index'))
            ->assertOk()
            ->assertSee('data-theme="raspberry"', false);
    }

    public function test_every_theme_can_be_selected(): void
    {
        foreach (Theme::cases() as $theme) {
            $this->withUnencryptedCookie(Theme::COOKIE, $theme->value)
                ->get(route('products.index'))
                ->assertOk()
                ->assertSee('data-theme="'.$theme->value.'"', false)
                ->assertSee($theme->label());
        }
    }

    public function test_the_profile_page_offers_a_detailed_design_picker(): void
    {
        $response = $this->actingAs(User::factory()->create())->get(route('profile.edit'))->assertOk();

        $response->assertSee('Choose your design');

        foreach (Theme::cases() as $theme) {
            $response->assertSee($theme->description());
        }
    }

    public function test_an_unknown_theme_falls_back_to_the_default(): void
    {
        $this->withUnencryptedCookie(Theme::COOKIE, 'neon"><script>')
            ->get(route('login'))
            ->assertOk()
            ->assertSee('data-theme="matcha"', false);
    }
}
