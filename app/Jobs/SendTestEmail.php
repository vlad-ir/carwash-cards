<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Mail\Transport\TransportException;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Exception;

class SendTestEmail implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 1;
    public int $backoff = 0;

    protected string $toEmail;
    protected int $index;

    protected int $emailsPerBatch;
    protected int $delayBetweenEmails;
    protected int $delayAfterBatch;

    public function __construct(string $toEmail, int $index)
    {
        $this->toEmail = $toEmail;
        $this->index = $index;

        $this->emailsPerBatch = config('mail.yandex_rate_limit.emails_per_batch', 10);
        $this->delayBetweenEmails = config('mail.yandex_rate_limit.delay_between_emails', 60);
        $this->delayAfterBatch = config('mail.yandex_rate_limit.delay_after_batch', 3600);
    }

    public function handle(): void
    {
        $rateLimitKey = 'yandex_email_rate_limit_test';
        $emailsSentInBatch = Cache::get($rateLimitKey, 0);
        $batchStartTime = Cache::get($rateLimitKey . '_start_time');

        // Начало новой пачки
        if ($batchStartTime === null) {
            Cache::put($rateLimitKey . '_start_time', now()->timestamp, now()->addHours(2));
            $batchStartTime = now()->timestamp;
        }

        // Проверка лимита пачки (10 писем → пауза 1 час)
        if ($emailsSentInBatch >= $this->emailsPerBatch) {
            $elapsed = now()->timestamp - $batchStartTime;

            if ($elapsed < $this->delayAfterBatch) {
                $wait = $this->delayAfterBatch - $elapsed;
                Log::info("[TEST] Batch limit reached. Waiting {$wait}s.", ['index' => $this->index]);
                $this->release($wait);
                return;
            }

            Cache::put($rateLimitKey, 0, now()->addHours(2));
            Cache::put($rateLimitKey . '_start_time', now()->timestamp, now()->addHours(2));
            $emailsSentInBatch = 0;
        }

        // Проверка интервала между письмами (1 минута)
        $lastEmailTime = Cache::get('yandex_last_email_time_test');
        if ($lastEmailTime !== null) {
            $diff = now()->timestamp - $lastEmailTime;
            if ($diff < $this->delayBetweenEmails) {
                $wait = $this->delayBetweenEmails - $diff;
                Log::info("[TEST] Waiting {$wait}s between emails.", ['index' => $this->index]);
                $this->release($wait);
                return;
            }
        }

        // Отправка письма
        try {
            Mail::raw("Тестовое письмо #{$this->index}\n\nОтправлено: " . now()->format('Y-m-d H:i:s'), function ($message) {
                $message->to($this->toEmail)
                    ->subject("Yandex Rate Limit Test #{$this->index}");
            });

            Cache::increment($rateLimitKey);
            Cache::put('yandex_last_email_time_test', now()->timestamp, now()->addHours(2));

            Log::info("[TEST] Email #{$this->index} sent successfully.", [
                'to' => $this->toEmail,
                'from' => config('mail.from.address', config('mail.username')),
                'batch_count' => $emailsSentInBatch + 1,
            ]);

        } catch (TransportExceptionInterface | TransportException $e) {
            if ($this->isTemporaryError($e->getMessage())) {
                Log::warning("[TEST] Temporary error: {$e->getMessage()}", ['index' => $this->index]);
                $this->release($this->delayBetweenEmails);
                return;
            }
            Log::error("[TEST] Permanent error: {$e->getMessage()}", ['index' => $this->index]);

        } catch (Exception $e) {
            Log::error("[TEST] Unexpected error: {$e->getMessage()}", ['index' => $this->index]);
        }
    }

    private function isTemporaryError(string $message): bool
    {
        $patterns = ['too many requests', 'rate limit', 'temporarily unavailable', 'try again', 'timeout', '421', '450', '451'];
        foreach ($patterns as $pattern) {
            if (str_contains(strtolower($message), $pattern)) return true;
        }
        return false;
    }
}
