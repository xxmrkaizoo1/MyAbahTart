<?php

namespace App\Services;

use App\Enums\DeliveryMethod;
use App\Exceptions\DeliveryQuoteException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

/**
 * Live delivery prices from the Lalamove API (v3) for the shop's Express option.
 */
class Lalamove
{
    /**
     * Vehicles admins can choose from, keyed by Lalamove's service type.
     *
     * @var array<string, string>
     */
    public const SERVICE_TYPES = [
        'MOTORCYCLE' => 'Motorcycle',
        'CAR' => 'Car',
        'MPV' => 'MPV',
        'VAN' => 'Van',
    ];

    public function __construct(private Storefront $storefront, private Geocoder $geocoder) {}

    /**
     * Whether the API key and secret are set in the .env file.
     */
    public function isConfigured(): bool
    {
        return filled(config('services.lalamove.key')) && filled(config('services.lalamove.secret'));
    }

    /**
     * Whether Express delivery is currently priced live by Lalamove.
     */
    public function isLive(): bool
    {
        return $this->isConfigured()
            && $this->storefront->get('delivery_express_live') === '1'
            && DeliveryMethod::Express->isEnabled();
    }

    public function isSandbox(): bool
    {
        return (bool) config('services.lalamove.sandbox');
    }

    /**
     * Where the rider collects the order: the pickup address set on the Delivery page, or the shop address.
     */
    public function pickupAddress(): string
    {
        $address = $this->storefront->get('lalamove_pickup_address') ?: $this->storefront->get('address');

        return trim(preg_replace('/\s+/', ' ', str_replace("\n", ', ', $address)));
    }

    /**
     * The price of delivering from the shop to the given address right now.
     *
     * @throws DeliveryQuoteException
     */
    public function quote(string $dropOffAddress): LalamoveQuote
    {
        $dropOffAddress = trim(preg_replace('/\s+/', ' ', $dropOffAddress));
        $serviceType = $this->serviceType();
        $cacheKey = 'lalamove-quote:'.md5(implode('|', [$this->pickupAddress(), Str::lower($dropOffAddress), $serviceType]));

        if (is_array($cached = Cache::get($cacheKey))) {
            return new LalamoveQuote($cached['id'], $cached['price'], $cached['distance_km'], Carbon::parse($cached['expires_at']));
        }

        $pickup = $this->geocoder->locate($this->pickupAddress())
            ?? throw new DeliveryQuoteException('Live delivery prices aren’t available right now. Please choose another option.');
        $dropOff = $this->geocoder->locate($dropOffAddress)
            ?? throw new DeliveryQuoteException('We couldn’t find that address on the map. Please add the postcode and city.');

        $quote = $this->requestQuote($serviceType, [
            ['coordinates' => ['lat' => (string) $pickup['lat'], 'lng' => (string) $pickup['lng']], 'address' => $this->pickupAddress()],
            ['coordinates' => ['lat' => (string) $dropOff['lat'], 'lng' => (string) $dropOff['lng']], 'address' => $dropOffAddress],
        ]);

        Cache::put($cacheKey, [
            'id' => $quote->quotationId,
            'price' => $quote->price,
            'distance_km' => $quote->distanceKm,
            'expires_at' => $quote->expiresAt->toIso8601String(),
        ], $quote->expiresAt->copy()->subMinute()->min(now()->addMinutes(4)));

        return $quote;
    }

    private function serviceType(): string
    {
        $serviceType = $this->storefront->get('lalamove_service_type');

        return array_key_exists($serviceType, self::SERVICE_TYPES) ? $serviceType : 'MOTORCYCLE';
    }

    /**
     * @param  list<array{coordinates: array{lat: string, lng: string}, address: string}>  $stops
     *
     * @throws DeliveryQuoteException
     */
    private function requestQuote(string $serviceType, array $stops): LalamoveQuote
    {
        $response = $this->send('POST', '/v3/quotations', [
            'data' => [
                'serviceType' => $serviceType,
                'language' => 'en_'.config('services.lalamove.market'),
                'stops' => $stops,
            ],
        ]);

        if ($response->failed()) {
            $code = (string) $response->json('errors.0.id');

            throw new DeliveryQuoteException(match (true) {
                Str::contains($code, ['OUT_OF_SERVICE_AREA', 'INVALID_STOP', 'DISTANCE']) => 'Lalamove doesn’t deliver to this address yet. Please choose another option.',
                default => 'Live delivery prices aren’t available right now. Please choose another option.',
            });
        }

        $distance = $response->json('data.distance');

        return new LalamoveQuote(
            quotationId: (string) $response->json('data.quotationId'),
            price: round((float) $response->json('data.priceBreakdown.total'), 2),
            distanceKm: isset($distance['value']) ? round((float) $distance['value'] / (($distance['unit'] ?? 'm') === 'm' ? 1000 : 1), 1) : null,
            expiresAt: Carbon::parse($response->json('data.expiresAt') ?? now()->addMinutes(5)),
        );
    }

    /**
     * Send a signed request. Lalamove signs "time\r\nMETHOD\r\npath\r\n\r\nbody" with HMAC-SHA256.
     *
     * @param  array<string, mixed>  $payload
     *
     * @throws DeliveryQuoteException
     */
    private function send(string $method, string $path, array $payload): Response
    {
        $body = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $time = (string) now()->getTimestampMs();
        $signature = hash_hmac('sha256', "{$time}\r\n{$method}\r\n{$path}\r\n\r\n{$body}", (string) config('services.lalamove.secret'));

        try {
            return Http::baseUrl($this->isSandbox() ? 'https://rest.sandbox.lalamove.com' : 'https://rest.lalamove.com')
                ->withHeaders([
                    'Authorization' => 'hmac '.config('services.lalamove.key').":{$time}:{$signature}",
                    'Market' => config('services.lalamove.market'),
                    'Request-ID' => (string) Str::uuid(),
                ])
                ->timeout(10)
                ->withBody($body, 'application/json')
                ->send($method, $path);
        } catch (ConnectionException) {
            throw new DeliveryQuoteException('Live delivery prices aren’t available right now. Please choose another option.');
        }
    }
}
