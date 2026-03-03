<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Лимитер для отправки счетов: 30 писем в час
        RateLimiter::for('invoice-emails', function ($job) {
            $maxPerHour = config('mail.invoice_email_limit_per_hour', 30);
            return Limit::perHour($maxPerHour)->by('invoice-emails');
        });
    }
}
