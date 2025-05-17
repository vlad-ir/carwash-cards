<?php

namespace App\Console;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Console\Kernel as ConsoleKernel;

class Kernel extends ConsoleKernel
{
    protected $commands = [
        \App\Console\Commands\CarwashCsvImportStatsCommand::class
    ];

    protected function schedule(Schedule $schedule)
    {
        $schedule->command('carwash:csv-import-stats')->dailyAt('01:00');
        $schedule->command('carwash:invoices-generate')->daily();
    }

    protected function commands()
    {
        $this->load(__DIR__.'/Commands');
        require base_path('routes/console.php');
    }
}
