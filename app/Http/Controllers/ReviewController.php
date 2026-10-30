<?php

namespace App\Http\Controllers;

use App\Models\Product;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ReviewController extends Controller
{
    public function store(Request $request, Product $product): RedirectResponse
    {
        $user = $request->user();

        abort_unless(
            $user->orders()->whereHas('items', fn ($query) => $query->where('product_id', $product->id))->exists(),
            403,
            'Only customers who bought this product can review it.',
        );

        $validated = $request->validate([
            'rating' => ['required', 'integer', 'between:1,5'],
            'comment' => ['required', 'string', 'min:5', 'max:1000'],
        ]);

        $product->reviews()->updateOrCreate(['user_id' => $user->id], $validated);

        return back()->with('status', 'Thanks for sharing your review!');
    }
}
