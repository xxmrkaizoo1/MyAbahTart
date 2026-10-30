<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Throwable;

/**
 * Turns a Malaysian street address into map coordinates using OpenStreetMap.
 */
class Geocoder
{
    /**
     * The coordinates of an address, or null when it can't be found.
     *
     * Unit numbers and house numbers often aren't on the map, so leading parts of the
     * address are dropped one at a time until a match is found.
     *
     * @return array{lat: float, lng: float}|null
     */
    public function locate(string $address): ?array
    {
        $address = trim(preg_replace('/\s+/', ' ', $address));

        if ($address === '') {
            return null;
        }

        return Cache::remember('geocode:'.md5(Str::lower($address)), now()->addDays(30), function () use ($address) {
            $parts = array_values(array_filter(array_map('trim', explode(',', $address))));

            for ($start = 0; $start < count($parts); $start++) {
                if ($coordinates = $this->search(implode(', ', array_slice($parts, $start)))) {
                    return $coordinates;
                }
            }

            return null;
        });
    }

    /**
     * @return array{lat: float, lng: float}|null
     */
    private function search(string $query): ?array
    {
        try {
            $result = Http::baseUrl(config('services.nominatim.url'))
                ->withHeaders(['User-Agent' => config('app.name').' delivery pricing ('.config('app.url').')'])
                ->timeout(8)
                ->get('/search', ['q' => $query, 'format' => 'json', 'limit' => 1, 'countrycodes' => 'my'])
                ->json('0');
        } catch (Throwable) {
            return null;
        }

        return isset($result['lat'], $result['lon']) ? ['lat' => (float) $result['lat'], 'lng' => (float) $result['lon']] : null;
    }
}
