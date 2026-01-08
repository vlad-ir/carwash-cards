<?php

namespace App\Console\Commands;

use App\Services\CarwashCsvImportService;
use Illuminate\Console\Command;

class CarwashCsvImportStatsCommand extends Command
{
    protected $signature = 'carwash:csv-import-stats';
    protected $description = 'Импортирует статистику бонусных карт из CSV-файлов';

    public function handle()
    {
        $this->info('Начало импорта статистики...');

        $service = new CarwashCsvImportService();
        $processed = $service->processCsvFiles();

        // Логируем вызов
        $logFile = storage_path('logs/cron_debug.log');
        $message = date('Y-m-d H:i:s') . " - Cron команда вызвана\n";
        $message .= " - Обработано файлов: {$processed}\n";
        file_put_contents($logFile, $message, FILE_APPEND);

        $this->info("Обработано файлов: {$processed}");
    }
}

