<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreCarwashClientRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize(): bool
    {
        return true; // или установите соответствующие правила авторизации
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules(): array
    {
        return [
            'short_name' => 'required|string|max:100',
            'full_name' => 'required|string|max:255',
            'email' => 'required|email|max:100',
            'phone' => 'required|string|max:20',
            'unp' => 'required|string|max:9',
            'bank_account_number' => 'required|string|max:50',
            'bank_bic' => 'required|string|max:20',
            'status' => 'required|in:active,inactive,blocked',
            'invoice_email_required' => 'required|boolean',
            'invoice_email_date' => 'nullable|date',
            'postal_address' => 'required|string|max:255',
            'bank_postal_address' => 'required|string|max:255',
            'bonus_cards' => 'nullable|array',
            'bonus_cards.*.card_number' => 'required|string|max:20|unique:carwash_bonus_cards,card_number',
            'bonus_cards.*.name' => 'required|string|max:100',
            'bonus_cards.*.discount_percentage' => 'required|decimal:0,2',
            'bonus_cards.*.balance' => 'required|date_format:H:i:s',
            'bonus_cards.*.status' => 'required|in:active,inactive,blocked',
            'bonus_cards.*.car_license_plate' => 'nullable|string|max:20',
            'bonus_cards.*.rate_per_minute' => 'required|decimal:0,2',
            'bonus_cards.*.invoice_required' => 'required|boolean',
        ];
    }
}
