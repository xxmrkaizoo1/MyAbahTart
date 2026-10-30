<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProductRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return (bool) $this->user()?->isAdmin();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'category_id' => ['required', 'exists:categories,id'],
            'name' => ['required', 'string', 'max:255', Rule::unique('products')->ignore($this->route('product'))],
            'tagline' => ['nullable', 'string', 'max:255'],
            'description' => ['required', 'string', 'max:2000'],
            'price' => ['nullable', 'required_without:size_prices', 'numeric', 'min:0.5', 'max:9999'],
            'size_prices' => ['nullable', 'array:S,M,L'],
            'size_prices.*' => ['numeric', 'min:0.5', 'max:9999'],
            'unit_label' => ['required', 'string', 'max:60'],
            'weight_grams' => ['nullable', 'integer', 'min:1', 'max:100000'],
            'image' => ['nullable', 'image', 'max:10240'],
            'remove_image' => ['boolean'],
            'size_images' => ['nullable', 'array:S,M,L'],
            'size_images.*' => ['image', 'max:10240'],
            'remove_size_images' => ['nullable', 'array:S,M,L'],
            'remove_size_images.*' => ['boolean'],
            'stock' => ['nullable', 'required_without:size_prices', 'integer', 'min:0', 'max:100000'],
            'size_stock' => ['nullable', 'array:S,M,L'],
            'size_stock.*' => ['integer', 'min:0', 'max:100000'],
            'size_pieces' => ['nullable', 'array:S,M,L'],
            'size_pieces.*' => ['integer', 'min:1', 'max:1000'],
            'size_grams' => ['nullable', 'array:S,M,L'],
            'size_grams.*' => ['integer', 'min:1', 'max:100000'],
            'low_stock_threshold' => ['required', 'integer', 'min:0', 'max:10000'],
            'is_active' => ['boolean'],
            'is_bestseller' => ['boolean'],
            'is_seasonal' => ['boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'price.required_without' => 'Enter a price, or a price for at least one size.',
            'size_prices.*.min' => 'Size prices must be at least RM0.50.',
            'image.max' => 'The photo must be 10 MB or smaller.',
            'size_images.*.max' => 'Each size photo must be 10 MB or smaller.',
            'size_images.*.image' => 'Each size photo must be an image (JPG, PNG, WebP…).',
            'size_pieces.*' => 'Pieces per size must be a whole number from 1 to 1000.',
            'size_grams.*' => 'Weight per size must be a whole number of grams, from 1 to 100000.',
        ];
    }

    /**
     * Unchecked checkboxes are not submitted, so default them to false.
     * Sizes left blank are not offered.
     */
    protected function prepareForValidation(): void
    {
        $sizePrices = array_filter((array) $this->input('size_prices', []), fn ($price) => $price !== null && $price !== '');
        $sizeStock = array_map(
            fn ($count) => $count === null || $count === '' ? 0 : $count,
            array_intersect_key((array) $this->input('size_stock', []) + array_fill_keys(array_keys($sizePrices), 0), $sizePrices),
        );
        $filledForOfferedSizes = fn (string $field) => array_intersect_key(
            array_filter((array) $this->input($field, []), fn ($amount) => $amount !== null && $amount !== ''),
            $sizePrices,
        ) ?: null;

        $this->merge([
            'size_prices' => $sizePrices === [] ? null : $sizePrices,
            'size_stock' => $sizePrices === [] ? null : $sizeStock,
            'size_pieces' => $filledForOfferedSizes('size_pieces'),
            'size_grams' => $filledForOfferedSizes('size_grams'),
            'is_active' => $this->boolean('is_active'),
            'is_bestseller' => $this->boolean('is_bestseller'),
            'is_seasonal' => $this->boolean('is_seasonal'),
            'remove_image' => $this->boolean('remove_image'),
        ]);
    }
}
