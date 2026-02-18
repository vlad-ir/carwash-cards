<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use DateTime;

class CarwashClientResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        // Парсим номер и дату договора, если поле contract заполнено
        $parsedContract = $this->contract ? $this->extractContractNumberAndDate($this->contract) : null;

        return [
            'unp' => $this->unp ?? '',
            'full_name' => $this->full_name ?? '',
            'short_name' => $this->short_name ?? '',
            'contract_number' => $parsedContract['contract_number'] ?? '',
            'contract_date' => $parsedContract['date_string'] ?? '',
            'bank_account_number' => $this->bank_account_number ?? '',
            'bank_bic' => $this->bank_bic ?? '',
            'postal_address' => $this->postal_address ?? '',
            'bank_postal_address' => $this->bank_postal_address ?? '',
            'email' => $this->email ?? '',
        ];
    }

    /**
     * Извлекает номер договора и дату из строки вида "№ ДМ-8-2025 от 06.01.2025"
     *
     * @param string $input
     * @return array|null
     */
    private function extractContractNumberAndDate(string $input): ?array
    {
        // 1. Разделяем по " от "
        $parts = explode(" от ", $input);
        if (count($parts) !== 2) {
            return null; // Неверный формат строки
        }

        $leftPart = trim($parts[0]);       // например: "№ ДМ-8-2025" или "ДМ-8-2025"
        $dateStr  = trim($parts[1]);       // "06.01.2025"

        // 2. Список возможных префиксов (регистронезависимые)
        $prefixes = ['№', 'номер', 'договор', 'дог', 'контракт'];

        foreach ($prefixes as $prefix) {
            // Если строка начинается с префикса (с учётом регистра)
            if (mb_stripos($leftPart, $prefix) === 0) {
                // Удаляем префикс
                $leftPart = mb_substr($leftPart, mb_strlen($prefix));
                // Удаляем следующие пробелы
                $leftPart = ltrim($leftPart);
                break; // Предполагаем, что префикс только один
            }
        }

        // 3. Если после этого остался символ "№" в начале (например, если префикса не было, но был "№")
        if (mb_substr($leftPart, 0, 1) === '№') {
            $leftPart = mb_substr($leftPart, 1);
            $leftPart = ltrim($leftPart);
        }

        // 4. Оставшаяся часть — номер договора
        $contractNumber = $leftPart;

        // 5. Преобразование даты
        $date = DateTime::createFromFormat('d.m.Y', $dateStr);
        if (!$date) {
            return null; // Неверный формат даты
        }

        return [
            'contract_number' => $contractNumber,
            'date'            => $date,                // объект DateTime
            'date_string'     => $date->format('Y-m-d') // для БД или API
        ];
    }
}
