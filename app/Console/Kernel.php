<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    protected $commands = [
        \App\Console\Commands\CarwashCsvImportStatsCommand::class,
        \App\Console\Commands\CarwashGenerateInvoicesCommand::class,
        \App\Console\Commands\CheckStuckEmailsCommand::class,
    ];

    protected function schedule(Schedule $schedule)
    {
        // Очередь писем — каждую минуту (только очередь emails)
        $schedule->command('queue:work --queue=emails --stop-when-empty')
            ->everyMinute()
            ->withoutOverlapping();

        // Импорт статистики — каждые 3 часа
        $schedule->command('carwash:csv-import-stats')
            ->cron('0 */3 * * *')
            ->withoutOverlapping();

        // Генерация счетов — в 9:00 и 12:00 по будням (Пн-Пт)
        $schedule->command('carwash:invoices-generate')
            ->cron('0 9,12 * * 1-5')
            ->withoutOverlapping();

        // Проверка каждые 6 часов зависших писем
        $schedule->command('emails:check-stuck')
            ->everySixHours()
            ->withoutOverlapping()
            ->runInBackground();

        // Или ежедневно в 12:00
        // $schedule->command('emails:check-stuck')->dailyAt('12:00');
    }

    protected function commands()
    {
        $this->load(__DIR__.'/Commands');
        require base_path('routes/console.php');
    }
}
