<?php

namespace App\Http\Controllers;

use App\Exceptions\DeliveryQuoteException;
use App\Services\Lalamove;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DeliveryQuoteController extends Controller
{
    /**
     * The live Lalamove price to the address typed at checkout.
     */
    public function __invoke(Request $request, Lalamove $lalamove): JsonResponse
    {
        $validated = $request->validate(['address' => ['required', 'string', 'min:8', 'max:500']]);

        if (! $lalamove->isLive()) {
            return response()->json(['message' => 'Live delivery prices are switched off.'], 404);
        }

        try {
            return response()->json($lalamove->quote($validated['address'])->toArray());
        } catch (DeliveryQuoteException $exception) {
            return response()->json(['message' => $exception->getMessage()], 422);
        }
    }
}
