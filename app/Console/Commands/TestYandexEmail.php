<?php

namespace App\Console\Commands;

use App\Jobs\SendTestEmail;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class TestYandexEmail extends Command
{
    protected $signature = 'yandex:test-email {--count=12 : Количество писем}';
    protected $description = 'Отправляет тестовые письма через Yandex SMTP с rate limiting';

    public function handle(): int
    {
        // ЖЕСТКО прописанный получатель
        $toEmail = 'vlad_ir@mail.ru';
        $count = (int) $this->option('count');

        $this->info("📧 Тестовая отправка {$count} писем");
        $this->info("From: " . config('mail.from.address', config('mail.username')));
        $this->info("To:   {$toEmail}");
        $this->newLine();

        // Сброс тестовых счётчиков
        Cache::forget('yandex_email_rate_limit_test');
        Cache::forget('yandex_email_rate_limit_test_start_time');
        Cache::forget('yandex_last_email_time_test');
        $this->info('✅ Тестовые счётчики сброшены');

        // Диспатчим задачи
        for ($i = 1; $i <= $count; $i++) {
            SendTestEmail::dispatch($toEmail, $i);
        }

        $this->newLine();
        $this->info("✅ {$count} задач добавлено в очередь");
        $this->newLine();
        $this->comment('Запустите worker:');
        $this->line('  php artisan queue:work --queue=default --sleep=3 --tries=1');
        $this->newLine();
        $this->comment('Следите за логами:');
        $this->line('  tail -f storage/logs/laravel.log');

        return self::SUCCESS;
    }
}
