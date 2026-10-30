<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Storefront;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class StorefrontController extends Controller
{
    public function edit(Storefront $storefront): View
    {
        return view('admin.storefront.edit', ['storefront' => $storefront]);
    }

    public function update(Request $request, Storefront $storefront): RedirectResponse
    {
        $validated = $request->validate([
            'topbar_enabled' => ['sometimes', 'boolean'],
            'topbar_free_delivery' => ['sometimes', 'boolean'],
            'delivery_free_over' => ['sometimes', 'nullable', 'numeric', 'min:0', 'max:100000'],
            'announcement' => ['nullable', 'string', 'max:120'],
            'hero_badge' => ['nullable', 'string', 'max:60'],
            'hero_title_start' => ['nullable', 'string', 'max:40'],
            'hero_title_highlight' => ['required', 'string', 'max:30'],
            'hero_title_end' => ['nullable', 'string', 'max:40'],
            'hero_intro' => ['nullable', 'string', 'max:300'],
            'hero_button' => ['required', 'string', 'max:30'],
            'hero_image_file' => ['nullable', 'image', 'max:10240'],
            'remove_hero_image' => ['boolean'],
            'ticker' => ['nullable', 'string', 'max:1000'],
            'steps_title' => ['required', 'string', 'max:60'],
            'step_1_title' => ['required', 'string', 'max:40'],
            'step_1_text' => ['required', 'string', 'max:160'],
            'step_2_title' => ['required', 'string', 'max:40'],
            'step_2_text' => ['required', 'string', 'max:160'],
            'step_3_title' => ['required', 'string', 'max:40'],
            'step_3_text' => ['required', 'string', 'max:160'],
            'footer_about' => ['nullable', 'string', 'max:300'],
            'address' => ['nullable', 'string', 'max:200'],
            'opening_hours' => ['nullable', 'string', 'max:80'],
        ]);

        foreach (['topbar_enabled', 'topbar_free_delivery'] as $toggle) {
            if ($request->has($toggle)) {
                $validated[$toggle] = $request->boolean($toggle) ? '1' : '0';
            }
        }

        if ($request->has('delivery_free_over')) {
            $validated['delivery_free_over'] = number_format((float) $validated['delivery_free_over'], 2, '.', '');
        }

        if ($request->boolean('remove_hero_image') || $request->hasFile('hero_image_file')) {
            if ($storefront->get('hero_image') !== '') {
                Storage::disk('public')->delete($storefront->get('hero_image'));
            }

            $validated['hero_image'] = $request->hasFile('hero_image_file')
                ? $request->file('hero_image_file')->store('storefront', 'public')
                : '';
        }

        $storefront->update($validated);

        return back()->with('status', 'Storefront updated. Customers see the changes straight away.');
    }
}
