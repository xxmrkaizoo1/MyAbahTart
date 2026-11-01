<?php

namespace App\Http\Resources;

use App\Models\Voucher;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Voucher
 */
class VoucherResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'description' => $this->description,
            'type' => $this->type,
            'value' => (float) $this->value,
            'value_label' => $this->valueLabel(),
            'min_subtotal' => (float) $this->min_subtotal,
            'is_active' => $this->is_active,
            'is_usable' => $this->isUsable(),
            'expires_at' => $this->expires_at,
            'created_at' => $this->created_at,
        ];
    }
}
