<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreCarwashBonusCardRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'name' => 'required|string|max:100',
            'card_number' => 'required|string|max:20|unique:carwash_bonus_cards',
            'discount_percentage' => 'required|numeric|min:0|max:100',
            'balance' => 'required|date_format:H:i:s',
            'status' => 'required|in:active,inactive,blocked',
            'car_license_plate' => 'nullable|string|max:20',
            'rate_per_minute' => 'required|numeric|min:0',
            'invoice_required' => 'boolean',
            'client_id' => 'required|exists:carwash_clients,id',
        ];
    }
}
