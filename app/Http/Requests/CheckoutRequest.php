<?php

namespace App\Http\Requests;

use App\Enums\DeliveryMethod;
use App\Enums\PaymentMethod;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CheckoutRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'delivery_method' => ['required', Rule::enum(DeliveryMethod::class)->only(DeliveryMethod::enabledCases())],
            'payment_method' => ['nullable', Rule::enum(PaymentMethod::class)->only(PaymentMethod::forCheckout())],
            'phone' => ['required', 'string', 'max:20'],
            'address' => [
                Rule::requiredIf(fn () => DeliveryMethod::tryFrom((string) $this->input('delivery_method'))?->requiresAddress() ?? false),
                'nullable',
                'string',
                'max:500',
            ],
            'notes' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'address.required' => 'Please enter a delivery address.',
            'delivery_method.enum' => 'That delivery option isn’t available right now. Please pick another.',
            'payment_method.enum' => 'That payment option isn’t available right now. Please pick another.',
        ];
    }

    public function deliveryMethod(): DeliveryMethod
    {
        return DeliveryMethod::from($this->validated('delivery_method'));
    }

    /**
     * How the customer chose to pay, or the first available option when none was sent.
     */
    public function paymentMethod(): PaymentMethod
    {
        return PaymentMethod::tryFrom((string) $this->validated('payment_method')) ?? PaymentMethod::forCheckout()[0];
    }
}
