<?php

namespace App\Http\Controllers;

use App\Enums\OrderStatus;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class ProfileController extends Controller
{
    public function edit(Request $request): View
    {
        $user = $request->user();

        return view('shop.profile', [
            'user' => $user,
            'orderCount' => $user->orders()->where('status', '!=', OrderStatus::Cancelled)->count(),
            'totalSpent' => (float) $user->orders()->where('status', '!=', OrderStatus::Cancelled)->sum('total'),
            'reviewCount' => $user->reviews()->count(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $user = $request->user();

        $request->merge(['phone' => preg_replace('/\D/', '', (string) $request->input('phone'))]);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users')->ignore($user)],
            'phone' => ['required', 'regex:/^01\d{8,9}$/', Rule::unique('users')->ignore($user)],
            'address' => ['nullable', 'string', 'max:500'],
            'current_password' => ['nullable', 'required_with:password', 'current_password'],
            'password' => ['nullable', 'confirmed', Password::min(8)],
        ]);

        $user->fill(collect($validated)->only(['name', 'email', 'phone', 'address'])->all());

        if (! empty($validated['password'])) {
            $user->password = $validated['password'];
        }

        $user->save();

        return back()->with('status', 'Your profile has been updated.');
    }
}
