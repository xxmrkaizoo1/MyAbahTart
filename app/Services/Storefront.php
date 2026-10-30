<?php

namespace App\Services;

use App\Models\SiteSetting;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

/**
 * Shop-front wording and pictures that admins can edit, with built-in defaults.
 */
class Storefront
{
    /**
     * @var array<string, string>
     */
    public const DEFAULTS = [
        'announcement' => 'Every order prepared fresh in Bangsar',
        'hero_badge' => 'Prepared fresh for every order',
        'hero_title_start' => 'Tarts worth',
        'hero_title_highlight' => 'crossing',
        'hero_title_end' => 'town for.',
        'hero_intro' => 'Quality treats and snacks, carefully prepared and packed for every order. Get them delivered or pick them up from our shop.',
        'hero_button' => 'Shop the menu',
        'hero_image' => '',
        'ticker' => '',
        'steps_title' => 'From our shop to your table',
        'step_1_title' => 'Pick your favourites',
        'step_1_text' => 'Mix and match singles and boxes from the daily menu.',
        'step_2_title' => 'We prepare your order',
        'step_2_text' => 'Our team prepares and packs your order, and you can follow every step live.',
        'step_3_title' => 'Delivered or collected',
        'step_3_text' => 'Same-day express delivery or pick up from our shop.',
        'footer_about' => 'Tarts, kerepek and more, prepared with care for every order. Order online for delivery across Klang Valley or pick up from our shop.',
        'address' => "18, Jalan Telawi 3\nBangsar Baru, 59100 Kuala Lumpur",
        'opening_hours' => 'Daily, 9 am – 9 pm',
        'delivery_standard_label' => 'Standard Delivery',
        'delivery_standard_description' => 'Arrives in 2–3 working days',
        'delivery_standard_fee' => '5.00',
        'delivery_standard_enabled' => '1',
        'delivery_express_label' => 'Express Delivery',
        'delivery_express_description' => 'Same day when ordered before 4 pm',
        'delivery_express_fee' => '15.00',
        'delivery_express_enabled' => '1',
        'delivery_pickup_label' => 'Store Pickup',
        'delivery_pickup_description' => 'Collect from our Bangsar shop',
        'delivery_pickup_fee' => '0.00',
        'delivery_pickup_enabled' => '1',
        'delivery_free_over' => '150.00',
        'delivery_express_live' => '0',
        'lalamove_service_type' => 'MOTORCYCLE',
        'lalamove_pickup_address' => '',
    ];

    /**
     * @var array<string, string>|null
     */
    private ?array $values = null;

    public function get(string $key): string
    {
        return $this->all()[$key] ?? '';
    }

    /**
     * @return array<string, string>
     */
    public function all(): array
    {
        return $this->values ??= array_merge(
            self::DEFAULTS,
            SiteSetting::query()->whereIn('key', array_keys(self::DEFAULTS))->pluck('value', 'key')->map(fn (?string $value) => (string) $value)->all(),
        );
    }

    /**
     * @param  array<string, string|null>  $values
     */
    public function update(array $values): void
    {
        foreach (array_intersect_key($values, self::DEFAULTS) as $key => $value) {
            SiteSetting::query()->updateOrCreate(['key' => $key], ['value' => (string) $value]);
        }

        $this->values = null;
    }

    public function heroImageUrl(): ?string
    {
        $path = $this->get('hero_image');

        return $path !== '' ? Storage::disk('public')->url($path) : null;
    }

    /**
     * Custom ticker phrases, one per line. Empty when the shop should show product names instead.
     *
     * @return Collection<int, string>
     */
    public function tickerItems(): Collection
    {
        return collect(preg_split('/\R/', $this->get('ticker')))
            ->map(fn (string $line) => trim($line))
            ->filter()
            ->values();
    }

    /**
     * @return list<array{icon: string, title: string, text: string}>
     */
    public function steps(): array
    {
        return array_map(fn (int $step, string $icon) => [
            'icon' => $icon,
            'title' => $this->get("step_{$step}_title"),
            'text' => $this->get("step_{$step}_text"),
        ], [1, 2, 3], ['bag', 'whisk', 'heart-hand']);
    }
}
