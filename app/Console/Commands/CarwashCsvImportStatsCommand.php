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

        $this->info("Обработано файлов: {$processed}");
    }
}
