<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    protected $commands = [
        \App\Console\Commands\CarwashCsvImportStatsCommand::class,
        \App\Console\Commands\CarwashGenerateInvoicesCommand::class,
    ];

    protected function schedule(Schedule $schedule)
    {
        // Используем exec с полным путем к PHP, так как 'php' может быть недоступен в системном PATH для cron.
        // Это гарантирует, что обработчик очереди запускается корректно.
        $schedule->exec('/opt/alt/php82/usr/bin/php ' . base_path('artisan') . ' queue:work --stop-when-empty')
            ->everyMinute()
            ->withoutOverlapping();

        $schedule->command('carwash:csv-import-stats')->dailyAt('01:00');
        $schedule->command('carwash:invoices-generate')->dailyAt('03:00');
    }

    protected function commands()
    {
        $this->load(__DIR__.'/Commands');
        require base_path('routes/console.php');
    }
}
