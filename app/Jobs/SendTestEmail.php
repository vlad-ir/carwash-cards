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

    // Много попыток, т.к. release() увеличивает attempts в БД
    public int $tries = 100;
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
        $this->onQueue('emails');

        $this->emailsPerBatch = config('mail.yandex_rate_limit.emails_per_batch', 10);
        $this->delayBetweenEmails = config('mail.yandex_rate_limit.delay_between_emails', 60);
        $this->delayAfterBatch = config('mail.yandex_rate_limit.delay_after_batch', 3600);
    }

    public function handle(): void
    {
        $rateLimitKey = 'yandex_email_rate_limit_test';
        $batchStartTimeKey = $rateLimitKey . '_start_time';
        $lastEmailTimeKey = 'yandex_last_email_time_test';

        $emailsSentInBatch = (int) Cache::get($rateLimitKey, 0);
        $batchStartTime = Cache::get($batchStartTimeKey);

        // Начало новой пачки
        if ($batchStartTime === null) {
            Cache::put($batchStartTimeKey, now()->timestamp, now()->addHours(2));
            $batchStartTime = now()->timestamp;
        }

        // Проверка лимита пачки (10 писем → пауза 1 час)
        if ($emailsSentInBatch >= $this->emailsPerBatch) {
            $elapsed = now()->timestamp - (int) $batchStartTime;

            if ($elapsed < $this->delayAfterBatch) {
                $wait = $this->delayAfterBatch - $elapsed;
                Log::info("[TEST #{$this->index}] Batch limit reached ({$emailsSentInBatch}/{$this->emailsPerBatch}). Waiting {$wait}s.", [
                    'index' => $this->index,
                    'batch_count' => $emailsSentInBatch,
                ]);
                $this->release($wait);
                return;
            }

            // Пауза закончилась — новая пачка
            Cache::put($rateLimitKey, 0, now()->addHours(2));
            Cache::put($batchStartTimeKey, now()->timestamp, now()->addHours(2));
            $emailsSentInBatch = 0;
        }

        // Проверка интервала между письмами (1 минута)
        $lastEmailTime = Cache::get($lastEmailTimeKey);
        if ($lastEmailTime !== null) {
            $diff = now()->timestamp - (int) $lastEmailTime;
            if ($diff < $this->delayBetweenEmails) {
                $wait = $this->delayBetweenEmails - $diff;
                Log::info("[TEST #{$this->index}] Waiting {$wait}s between emails.", [
                    'index' => $this->index,
                    'last_email_ago' => $diff,
                ]);
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

            // Успешно — обновляем счётчики
            $newCount = Cache::increment($rateLimitKey);
            Cache::put($lastEmailTimeKey, now()->timestamp, now()->addHours(2));

            Log::info("[TEST #{$this->index}] Email sent successfully.", [
                'to' => $this->toEmail,
                'from' => config('mail.from.address', config('mail.username')),
                'batch_count' => $newCount,
            ]);

        } catch (TransportExceptionInterface | TransportException $e) {
            $msg = $e->getMessage();
            if ($this->isTemporaryError($msg)) {
                Log::warning("[TEST #{$this->index}] Temporary error: {$msg}", ['index' => $this->index]);
                $this->release($this->delayBetweenEmails);
                return;
            }
            Log::error("[TEST #{$this->index}] Permanent error: {$msg}", ['index' => $this->index]);

        } catch (Exception $e) {
            Log::error("[TEST #{$this->index}] Unexpected error: {$e->getMessage()}", ['index' => $this->index]);
        }
    }

    private function isTemporaryError(string $message): bool
    {
        $patterns = ['too many requests', 'rate limit', 'temporarily unavailable', 'try again', 'timeout', '421', '450', '451'];
        foreach ($patterns as $pattern) {
            if (str_contains(strtolower($message), $pattern)) {
                return true;
            }
        }
        return false;
    }

    public function failed(Exception $exception): void
    {
        Log::critical("[TEST #{$this->index}] Job failed after {$this->tries} attempts.", [
            'index' => $this->index,
            'exception' => $exception->getMessage(),
        ]);
    }
}
