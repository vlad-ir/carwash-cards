<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCarwashClientRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $clientId = $this->route('carwash_client');

        return [
            'short_name' => ['required', 'string', 'max:100'],
            'full_name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('carwash_clients', 'email')->ignore($clientId),
            ],
            'unp' => [
                'nullable',
                'string',
                'max:20',
                Rule::unique('carwash_clients', 'unp')->ignore($clientId),
            ],
            'bank_account_number' => ['nullable', 'string', 'max:50'],
            'bank_bic' => ['nullable', 'string', 'max:20'],
            'status' => ['required', Rule::in(['active', 'blocked'])],
            'invoice_email_required' => ['boolean'],
            'invoice_email_day' => ['nullable', 'integer', 'min:1', 'max:31'],
            'postal_address' => ['nullable', 'string', 'max:255'],
            'bank_postal_address' => ['nullable', 'string', 'max:255'],
            'contract' => ['nullable', 'string', 'max:255'],
        ];
    }

    public function messages(): array
    {
        return [
            'short_name.required' => 'Краткое имя обязательно для заполнения.',
            'short_name.max' => 'Краткое имя не должно превышать 100 символов.',
            'full_name.required' => 'Полное имя обязательно для заполнения.',
            'full_name.max' => 'Полное имя не должно превышать 255 символов.',
            'email.required' => 'Email обязателен для заполнения.',
            'email.email' => 'Введите корректный email.',
            'email.max' => 'Email не должен превышать 255 символов.',
            'email.unique' => 'Этот email уже зарегистрирован.',
            'unp.max' => 'УНП не должен превышать 20 символов.',
            'unp.unique' => 'Этот УНП уже зарегистрирован.',
            'bank_account_number.max' => 'Номер банковского счета не должен превышать 50 символов.',
            'bank_bic.max' => 'БИК банка не должен превышать 20 символов.',
            'status.required' => 'Статус обязателен для выбора.',
            'status.in' => 'Статус должен быть "Активен" или "Заблокирован".',
            'invoice_email_day.integer' => 'День для отправки счета должен быть числом.',
            'invoice_email_day.min' => 'День для отправки счета должен быть не менее 1.',
            'invoice_email_day.max' => 'День для отправки счета должен быть не более 31.',
            'postal_address.max' => 'Почтовый адрес не должен превышать 255 символов.',
            'bank_postal_address.max' => 'Банковский почтовый адрес не должен превышать 255 символов.',
            'contract.max' => 'Договор не должен превышать 255 символов.',
        ];
    }
}
