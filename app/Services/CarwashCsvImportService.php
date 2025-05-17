<?php

namespace App\Services;

use App\Models\CarwashBonusCard;
use App\Models\CarwashBonusCardStat;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class CarwashCsvImportService
{
    public function processCsvFiles()
    {
        $files = Storage::disk('public')->files('import_stat');
        $processed = 0;

        foreach ($files as $file) {
            if (preg_match('/(\d{4}-\d{2}-\d{2})\.csv$/', $file, $matches)) {
                $importDate = $matches[1];
                $this->importCsv($file, $importDate);
                $processed++;
            }
        }

        return $processed;
    }

    public function importCsv($filePath, $importDate)
    {
        $csvContent = Storage::disk('public')->get($filePath);
        $rows = array_map('str_getcsv', explode("\n", $csvContent));
        $header = array_shift($rows);

        foreach ($rows as $row) {
            if (count($row) < count($header) || empty($row[0])) {
                continue; // Пропускаем пустые или некорректные строки
            }

            $data = array_combine($header, $row);

            // Извлечение ID карты
            $cardIdRaw = $data['ID карты'] ?? '';
            $cardId = Str::after($cardIdRaw, 'Id=');

            // Поиск карты по card_number
            $card = CarwashBonusCard::where('card_number', $cardId)->first();
            if (!$card) {
                continue; // Пропускаем, если карта не найдена
            }

            // Обработка остатка
            $remainingBalance = $data['Остаток'] === '--' ? null : (int)$data['Остаток'];

            CarwashBonusCardStat::create([
                'card_id' => $card->id,
                'card_name' => $data['Название карты'] ?? null,
                'card_type' => $data['Тип карты'],
                'post' => (int)$data['Пост'],
                'start_time' => $data['Время начала'],
                'duration_seconds' => (int)$data['Длительность'],
                'remaining_balance_seconds' => $remainingBalance,
                'import_date' => $importDate,
            ]);
        }

        // Архивируем обработанный файл
        Storage::disk('public')->move($filePath, 'import_stat/processed/' . basename($filePath));
    }
}
