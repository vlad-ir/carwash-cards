<?php

namespace App\Services;

use App\Models\CarwashBonusCard;
use App\Models\CarwashBonusCardStat;
use Illuminate\Support\Facades\Log;
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

        $csvContent = mb_convert_encoding(Storage::disk('public')->get($filePath), 'UTF-8', 'UTF-8,Windows-1251');

        $rows = [];
        foreach (explode("\n", $csvContent) as $line) {
            $rows[] = str_getcsv($line, ';');
        }
        $header = array_shift($rows);


        foreach ($rows as $row) {
            if (count($row) < count($header) || empty($row[0])) {
                continue; // Пропускаем пустые или некорректные строки
            }

            // Извлечение ID карты
            $cardIdRaw = $row[1] ?? '';
            $cardId = Str::after($cardIdRaw, 'Id=');

            // Поиск карты
            $card = CarwashBonusCard::where('card_number', trim($cardId))->first();
            if (!$card) {
                // Log::warning("Card not found: " . $cardId);
                continue;
            }

            // Поиск номера поста. Если номер поста 4 - химчистка, то пропускаем эту запись
            if ((int)$row[3] === 4) {
                // Log::warning("Пост 4 - химчистка. Импорт пропущен.");
                continue;
            }

            $card = CarwashBonusCard::where('card_number', trim($cardId))->first();
            if (!$card) {
                // Log::warning("Card not found: " . $cardId);
                continue;
            }

            // Обработка времени начала
            $startTimeRaw = trim($row[4] ?? '');
            if (empty($startTimeRaw) || $startTimeRaw === '--') {
                // Log::error("Empty or invalid start time: " . $startTimeRaw);
                continue;
            }

            // Проверка на дубликаты
            $existingStat = CarwashBonusCardStat::where('card_id', $card->id)
                ->where('start_time', $startTimeRaw)
                ->first();

            if ($existingStat) {
                //Log::info("Skipping duplicate stat entry for card_id: {$card->id} {$card->name} {$card->card_number}, start_time: {$startTimeRaw}");
                continue; // Пропускаем создание этой записи
            }

            // Обработка длительности
            $durationSeconds = (int)$row[5];

            // Обработка остатка
            $remainingBalance = $row[6] === '--' ? null : (int)$row[6];

            // Сохранение статистики
            $stat = CarwashBonusCardStat::create([
                'card_id' => $card->id,
                'start_time' => $startTimeRaw,
                'duration_seconds' => $durationSeconds,
                'remaining_balance_seconds' => $remainingBalance,
                'import_date' => $importDate,
            ]);

            if (!$stat) {
                Log::error("Failed to save stat for card ID: {$card->id}");
            }
        }

        // Архивируем обработанный файл
        Storage::disk('public')->move($filePath, 'import_stat/processed/' . basename($filePath));
    }

}
