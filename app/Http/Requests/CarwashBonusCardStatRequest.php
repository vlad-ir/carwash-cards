<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class CarwashBonusCardStatRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return true; // Разрешить всем авторизованным пользователям
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        return [
            'card_id' => 'required|exists:carwash_bonus_cards,id',
            'start_time' => 'required|date',
            'duration_seconds' => 'required|integer|min:0',
            'remaining_balance_seconds' => 'nullable|integer|min:0',
            'import_date' => 'required|date',
        ];
    }

    /**
     * Get custom messages for validation errors.
     *
     * @return array
     */
    public function messages()
    {
        return [
            'card_id.required' => 'Поле "Бонусная карта" обязательно.',
            'card_id.exists' => 'Выбранная карта не существует.',
            'start_time.required' => 'Поле "Время начала" обязательно.',
            'start_time.date' => 'Недопустимый формат даты для времени начала.',
            'duration_seconds.required' => 'Поле "Длительность" обязательно.',
            'duration_seconds.integer' => 'Длительность должна быть целым числом.',
            'duration_seconds.min' => 'Длительность не может быть отрицательной.',
            'remaining_balance_seconds.integer' => 'Остаток должен быть целым числом.',
            'remaining_balance_seconds.min' => 'Остаток не может быть отрицательным.',
            'import_date.required' => 'Поле "Дата импорта" обязательно.',
            'import_date.date' => 'Недопустимый формат даты импорта.',
        ];
    }
}
