<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCarwashBonusCardRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $bonusCardId = $this->route('carwash_bonus_card');

        return [
            'name' => 'required|string|max:100',
            'card_number' => [
                'required',
                'string',
                'max:20',
                Rule::unique('carwash_bonus_cards', 'card_number')->ignore($bonusCardId),
            ],
            'status' => 'required|in:active,blocked',
            'rate_per_minute' => 'required|numeric|min:0',
            'client_id' => 'required|exists:carwash_clients,id',
        ];
    }
}
